<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    private const DOCUMENT_NUMBER_ATTEMPTS = 100;

    /**
     * @param  array{from_warehouse_id?: int|string|null, to_warehouse_id?: int|string|null, product_id?: int|string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'creator', 'items.product'])
            ->when($filters['from_warehouse_id'] ?? null, function (Builder $query, int|string $warehouseId): Builder {
                return $query->where('from_warehouse_id', $warehouseId);
            })
            ->when($filters['to_warehouse_id'] ?? null, function (Builder $query, int|string $warehouseId): Builder {
                return $query->where('to_warehouse_id', $warehouseId);
            })
            ->when($filters['product_id'] ?? null, function (Builder $query, int|string $productId): Builder {
                return $query->whereHas('items', function (Builder $query) use ($productId): Builder {
                    return $query->where('product_id', $productId);
                });
            })
            ->when($filters['date_from'] ?? null, function (Builder $query, string $dateFrom): Builder {
                return $query->whereDate('transfer_date', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $dateTo): Builder {
                return $query->whereDate('transfer_date', '<=', $dateTo);
            })
            ->latest('transfer_date')
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
     * @param  array{from_warehouse_id: int|string, to_warehouse_id: int|string, transfer_date: string, remarks?: string|null, items: list<array{product_id: int|string, quantity: int|float|string, remarks?: string|null}>}  $data
     */
    public function create(array $data, User $user): StockTransfer
    {
        return DB::transaction(function () use ($data, $user): StockTransfer {
            $fromWarehouseId = (int) $data['from_warehouse_id'];
            $toWarehouseId = (int) $data['to_warehouse_id'];

            if ($fromWarehouseId === $toWarehouseId) {
                throw ValidationException::withMessages([
                    'to_warehouse_id' => 'The destination warehouse must be different from the source warehouse.',
                ]);
            }

            $stockTransfer = StockTransfer::query()->create([
                'document_no' => $this->generateDocumentNo(),
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'transfer_date' => $data['transfer_date'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] as $index => $item) {
                $productId = (int) $item['product_id'];
                $quantity = round((float) $item['quantity'], 4);
                $itemRemarks = $item['remarks'] ?? null;

                [$sourceStock, $destinationStock] = $this->lockedWarehouseStocks(
                    $fromWarehouseId,
                    $toWarehouseId,
                    $productId,
                    $index,
                );

                $sourceQuantity = round((float) $sourceStock->quantity, 4);
                $sourceReservedQuantity = round((float) $sourceStock->reserved_quantity, 4);
                $sourceAvailableQuantity = round($sourceQuantity - $sourceReservedQuantity, 4);

                if ($quantity > $sourceAvailableQuantity) {
                    throw ValidationException::withMessages([
                        'items.'.$index.'.quantity' => 'The requested quantity exceeds available stock in the source warehouse.',
                    ]);
                }

                $sourceBalanceAfter = round($sourceQuantity - $quantity, 4);
                $this->ensureSourceStockCanSettle($sourceBalanceAfter, $sourceReservedQuantity, $index);

                $destinationBalanceAfter = round((float) $destinationStock->quantity + $quantity, 4);

                $stockTransfer->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'remarks' => $itemRemarks,
                ]);

                $sourceStock->update([
                    'quantity' => $sourceBalanceAfter,
                ]);

                $destinationStock->update([
                    'quantity' => $destinationBalanceAfter,
                ]);

                $remarks = $itemRemarks ?: ($data['remarks'] ?? null);

                StockMovement::query()->create([
                    'warehouse_id' => $fromWarehouseId,
                    'product_id' => $productId,
                    'movement_type' => 'transfer_out',
                    'quantity' => $quantity,
                    'balance_after' => $sourceBalanceAfter,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $stockTransfer->id,
                    'remarks' => $remarks,
                    'created_by' => $user->id,
                ]);

                StockMovement::query()->create([
                    'warehouse_id' => $toWarehouseId,
                    'product_id' => $productId,
                    'movement_type' => 'transfer_in',
                    'quantity' => $quantity,
                    'balance_after' => $destinationBalanceAfter,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $stockTransfer->id,
                    'remarks' => $remarks,
                    'created_by' => $user->id,
                ]);
            }

            return $this->loadForShow($stockTransfer);
        });
    }

    public function loadForShow(StockTransfer $stockTransfer): StockTransfer
    {
        return $stockTransfer->refresh()->load(['fromWarehouse', 'toWarehouse', 'creator', 'items.product']);
    }

    public function movements(StockTransfer $stockTransfer): Collection
    {
        return StockMovement::query()
            ->with(['warehouse', 'product', 'creator'])
            ->where('reference_type', StockTransfer::class)
            ->where('reference_id', $stockTransfer->id)
            ->orderBy('id')
            ->get();
    }

    private function generateDocumentNo(): string
    {
        $prefix = 'ST-'.now()->format('Ymd').'-';
        $lastDocumentNo = StockTransfer::query()
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

            if (! StockTransfer::query()->where('document_no', $documentNo)->exists()) {
                return $documentNo;
            }

            $nextNumber++;
        }

        throw ValidationException::withMessages([
            'document_no' => 'Unable to generate a unique stock transfer document number.',
        ]);
    }

    /**
     * @return array{0: WarehouseStock, 1: WarehouseStock}
     */
    private function lockedWarehouseStocks(
        int $fromWarehouseId,
        int $toWarehouseId,
        int $productId,
        int $itemIndex,
    ): array {
        $warehouseIds = [$fromWarehouseId, $toWarehouseId];
        sort($warehouseIds);

        $stocks = WarehouseStock::query()
            ->where('product_id', $productId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->orderBy('warehouse_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('warehouse_id');

        $sourceStock = $stocks->get($fromWarehouseId);

        if (! $sourceStock) {
            throw ValidationException::withMessages([
                'items.'.$itemIndex.'.quantity' => 'No stock balance exists for the selected source warehouse and product.',
            ]);
        }

        $destinationStock = $stocks->get($toWarehouseId);

        if (! $destinationStock) {
            WarehouseStock::query()->create([
                'warehouse_id' => $toWarehouseId,
                'product_id' => $productId,
                'quantity' => 0,
                'reserved_quantity' => 0,
            ]);

            $destinationStock = WarehouseStock::query()
                ->where('warehouse_id', $toWarehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return [$sourceStock, $destinationStock];
    }

    private function ensureSourceStockCanSettle(float $balanceAfter, float $reservedQuantity, int $itemIndex): void
    {
        if ($balanceAfter < 0) {
            throw ValidationException::withMessages([
                'items.'.$itemIndex.'.quantity' => 'Source stock quantity cannot be reduced below zero.',
            ]);
        }

        if ($balanceAfter < $reservedQuantity) {
            throw ValidationException::withMessages([
                'items.'.$itemIndex.'.quantity' => 'Source stock quantity cannot be lower than reserved quantity.',
            ]);
        }
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
