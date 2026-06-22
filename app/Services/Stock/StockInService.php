<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockInService
{
    private const DOCUMENT_NUMBER_ATTEMPTS = 100;

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param  array{warehouse_id?: int|string|null, product_id?: int|string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = StockIn::query()
            ->with(['warehouse', 'creator', 'items.product'])
            ->when($filters['warehouse_id'] ?? null, function (Builder $query, int|string $warehouseId): Builder {
                return $query->where('warehouse_id', $warehouseId);
            })
            ->when($filters['product_id'] ?? null, function (Builder $query, int|string $productId): Builder {
                return $query->whereHas('items', function (Builder $query) use ($productId): Builder {
                    return $query->where('product_id', $productId);
                });
            })
            ->when($filters['date_from'] ?? null, function (Builder $query, string $dateFrom): Builder {
                return $query->whereDate('stock_date', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $dateTo): Builder {
                return $query->whereDate('stock_date', '<=', $dateTo);
            })
            ->latest('stock_date')
            ->latest('id');

        return $query->paginate($perPage)->appends($this->filledFilters($filters));
    }

    public function activeWarehouses(): Collection
    {
        return Warehouse::active()
            ->orderBy('name')
            ->orderBy('code')
            ->get();
    }

    public function activeProducts(): Collection
    {
        return Product::active()
            ->with(['category', 'unit'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{warehouse_id: int|string, stock_date: string, remarks?: string|null, items: list<array{product_id: int|string, quantity: int|float|string, remarks?: string|null}>}  $data
     */
    public function create(array $data, User $user): StockIn
    {
        $result = DB::transaction(function () use ($data, $user): array {
            $stockIn = StockIn::query()->create([
                'document_no' => $this->generateDocumentNo(),
                'warehouse_id' => $data['warehouse_id'],
                'stock_date' => $data['stock_date'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $user->id,
            ]);
            $movementIds = [];

            foreach ($data['items'] as $item) {
                $quantity = round((float) $item['quantity'], 4);
                $itemRemarks = $item['remarks'] ?? null;

                $stockIn->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'remarks' => $itemRemarks,
                ]);

                $stock = $this->lockedWarehouseStock((int) $stockIn->warehouse_id, (int) $item['product_id']);
                $balanceAfter = round((float) $stock->quantity + $quantity, 4);

                $stock->update([
                    'quantity' => $balanceAfter,
                ]);

                $movement = StockMovement::query()->create([
                    'warehouse_id' => $stockIn->warehouse_id,
                    'product_id' => $item['product_id'],
                    'movement_type' => 'stock_in',
                    'quantity' => $quantity,
                    'balance_after' => $balanceAfter,
                    'reference_type' => StockIn::class,
                    'reference_id' => $stockIn->id,
                    'remarks' => $itemRemarks ?: ($data['remarks'] ?? null),
                    'created_by' => $user->id,
                ]);

                $movementIds[] = $movement->id;
            }

            return [
                'stock_in' => $this->loadForShow($stockIn),
                'movement_ids' => $movementIds,
            ];
        });

        /** @var StockIn $stockIn */
        $stockIn = $result['stock_in'];

        $this->auditLogService->record(
            event: 'stock_in_created',
            module: 'stock_ins',
            auditable: $stockIn,
            description: sprintf(
                'Stock in "%s" was created for warehouse "%s".',
                $stockIn->document_no,
                $stockIn->warehouse?->name ?? 'Unknown Warehouse',
            ),
            newValues: $this->auditValues($stockIn),
            metadata: [
                'model' => 'stock_in',
                'stock_in_id' => $stockIn->id,
                'warehouse_id' => $stockIn->warehouse_id,
                'movement_ids' => $result['movement_ids'],
            ],
        );

        return $stockIn;
    }

    public function loadForShow(StockIn $stockIn): StockIn
    {
        return $stockIn->refresh()->load(['warehouse', 'creator', 'items.product']);
    }

    public function movements(StockIn $stockIn): Collection
    {
        return StockMovement::query()
            ->with(['product', 'creator'])
            ->where('reference_type', StockIn::class)
            ->where('reference_id', $stockIn->id)
            ->latest('id')
            ->get();
    }

    private function generateDocumentNo(): string
    {
        $prefix = 'SI-'.now()->format('Ymd').'-';
        $lastDocumentNo = StockIn::query()
            ->where('document_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('document_no')
            ->value('document_no');

        $nextNumber = 1;

        if (is_string($lastDocumentNo) && preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', $lastDocumentNo, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        }

        for ($attempt = 1; $attempt <= self::DOCUMENT_NUMBER_ATTEMPTS; $attempt++) {
            $documentNo = $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

            if (! StockIn::query()->where('document_no', $documentNo)->exists()) {
                return $documentNo;
            }

            $nextNumber++;
        }

        throw ValidationException::withMessages([
            'document_no' => 'Unable to generate a unique stock in document number.',
        ]);
    }

    private function lockedWarehouseStock(int $warehouseId, int $productId): WarehouseStock
    {
        $stock = WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        WarehouseStock::query()->create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        return WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(StockIn $stockIn): array
    {
        return [
            'stock_in_id' => $stockIn->id,
            'reference_no' => $stockIn->document_no,
            'warehouse_id' => $stockIn->warehouse_id,
            'warehouse_name' => $stockIn->warehouse?->name,
            'stock_in_date' => $stockIn->stock_date?->toDateString(),
            'total_items' => $stockIn->items->count(),
            'total_quantity' => $this->formatQuantity((float) $stockIn->items->sum(
                fn ($item): float => (float) $item->quantity
            )),
            'remarks' => $stockIn->remarks,
            'items' => $stockIn->items->map(function ($item): array {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'quantity' => $item->quantity,
                    'remarks' => $item->remarks,
                ];
            })->values()->all(),
        ];
    }

    private function formatQuantity(float $quantity): string
    {
        return number_format($quantity, 4, '.', '');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function filledFilters(array $filters): array
    {
        return array_filter($filters, fn ($value): bool => $value !== null && $value !== '');
    }
}
