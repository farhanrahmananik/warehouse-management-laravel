<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    private const PO_NUMBER_ATTEMPTS = 25;

    /**
     * @param  array{status?: string|null, supplier_id?: int|string|null, warehouse_id?: int|string|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'warehouse', 'createdBy'])
            ->when($filters['status'] ?? null, function (Builder $query, string $status): Builder {
                return $query->where('status', $status);
            })
            ->when($filters['supplier_id'] ?? null, function (Builder $query, int|string $supplierId): Builder {
                return $query->where('supplier_id', $supplierId);
            })
            ->when($filters['warehouse_id'] ?? null, function (Builder $query, int|string $warehouseId): Builder {
                return $query->where('warehouse_id', $warehouseId);
            })
            ->when($filters['date_from'] ?? null, function (Builder $query, string $dateFrom): Builder {
                return $query->whereDate('order_date', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function (Builder $query, string $dateTo): Builder {
                return $query->whereDate('order_date', '<=', $dateTo);
            })
            ->latest('order_date')
            ->latest('id');

        return $query->paginate(10)->appends($this->filledFilters($filters));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user): PurchaseOrder {
            $totals = $this->calculateTotals($data['items'] ?? [], $data);

            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $this->generatePoNumber(),
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => PurchaseOrder::STATUS_DRAFT,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'subtotal' => $totals['header']['subtotal'],
                'discount_amount' => $totals['header']['discount_amount'],
                'tax_amount' => $totals['header']['tax_amount'],
                'shipping_amount' => $totals['header']['shipping_amount'],
                'total_amount' => $totals['header']['total_amount'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->replaceItems($purchaseOrder, $totals['items']);

            return $this->loadPurchaseOrder($purchaseOrder);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data): PurchaseOrder {
            $lockedPurchaseOrder = $this->lockedPurchaseOrder($purchaseOrder);

            if (! $lockedPurchaseOrder->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft purchase orders can be updated.',
                ]);
            }

            $totals = $this->calculateTotals($data['items'] ?? [], $data);

            $lockedPurchaseOrder->update([
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'subtotal' => $totals['header']['subtotal'],
                'discount_amount' => $totals['header']['discount_amount'],
                'tax_amount' => $totals['header']['tax_amount'],
                'shipping_amount' => $totals['header']['shipping_amount'],
                'total_amount' => $totals['header']['total_amount'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->replaceItems($lockedPurchaseOrder, $totals['items']);

            return $this->loadPurchaseOrder($lockedPurchaseOrder);
        });
    }

    public function approve(PurchaseOrder $purchaseOrder, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $user): PurchaseOrder {
            $lockedPurchaseOrder = $this->lockedPurchaseOrder($purchaseOrder);

            if (! $lockedPurchaseOrder->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft purchase orders can be approved.',
                ]);
            }

            $lockedPurchaseOrder->update([
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $user->id,
            ]);

            return $this->loadPurchaseOrder($lockedPurchaseOrder);
        });
    }

    public function cancel(PurchaseOrder $purchaseOrder, User $user, ?string $notes = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $user, $notes): PurchaseOrder {
            $lockedPurchaseOrder = $this->lockedPurchaseOrder($purchaseOrder);

            if (! $lockedPurchaseOrder->isDraft() && ! $lockedPurchaseOrder->isApproved()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft or approved purchase orders can be cancelled.',
                ]);
            }

            $data = [
                'status' => PurchaseOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
            ];

            $cancellationNotes = trim((string) $notes);

            if ($cancellationNotes !== '') {
                $data['notes'] = $this->appendCancellationNote(
                    $lockedPurchaseOrder->notes,
                    $cancellationNotes,
                );
            }

            $lockedPurchaseOrder->update($data);

            return $this->loadPurchaseOrder($lockedPurchaseOrder);
        });
    }

    public function delete(PurchaseOrder $purchaseOrder): void
    {
        DB::transaction(function () use ($purchaseOrder): void {
            $lockedPurchaseOrder = $this->lockedPurchaseOrder($purchaseOrder);

            if (! $lockedPurchaseOrder->isDraft() && ! $lockedPurchaseOrder->isCancelled()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft or cancelled purchase orders can be deleted.',
                ]);
            }

            $lockedPurchaseOrder->delete();
        });
    }

    private function generatePoNumber(): string
    {
        for ($attempt = 1; $attempt <= self::PO_NUMBER_ATTEMPTS; $attempt++) {
            $poNumber = 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));

            if (! PurchaseOrder::withTrashed()->where('po_number', $poNumber)->exists()) {
                return $poNumber;
            }
        }

        throw ValidationException::withMessages([
            'po_number' => 'Unable to generate a unique purchase order number.',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $data
     * @return array{header: array{subtotal: float, discount_amount: float, tax_amount: float, shipping_amount: float, total_amount: float}, items: list<array<string, mixed>>}
     */
    private function calculateTotals(array $items, array $data): array
    {
        $subtotal = 0.0;
        $normalizedItems = [];

        foreach ($items as $item) {
            $quantity = round((float) ($item['quantity'] ?? 0), 3);
            $unitCost = round((float) ($item['unit_cost'] ?? 0), 2);
            $lineTotal = round($quantity * $unitCost, 2);
            $subtotal = round($subtotal + $lineTotal, 2);

            $normalizedItems[] = [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => $quantity,
                'received_quantity' => 0,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
                'notes' => $item['notes'] ?? null,
            ];
        }

        $discountAmount = round((float) ($data['discount_amount'] ?? 0), 2);
        $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);
        $shippingAmount = round((float) ($data['shipping_amount'] ?? 0), 2);
        $totalAmount = round($subtotal - $discountAmount + $taxAmount + $shippingAmount, 2);

        if ($totalAmount < 0) {
            throw ValidationException::withMessages([
                'total_amount' => 'Purchase order total cannot be negative.',
            ]);
        }

        return [
            'header' => [
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $totalAmount,
            ],
            'items' => $normalizedItems,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function replaceItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $purchaseOrder->items()->delete();

        foreach ($items as $item) {
            PurchaseOrderItem::query()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'received_quantity' => 0,
                'unit_cost' => $item['unit_cost'],
                'line_total' => $item['line_total'],
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    private function loadPurchaseOrder(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder->refresh()->load([
            'supplier',
            'warehouse',
            'items.product',
            'createdBy',
            'approvedBy',
            'receivedBy',
            'cancelledBy',
        ]);
    }

    private function lockedPurchaseOrder(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->whereKey($purchaseOrder->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function appendCancellationNote(?string $existingNotes, string $cancellationNotes): string
    {
        $note = '[Cancelled '.now()->format('Y-m-d H:i:s').'] '.$cancellationNotes;
        $existingNotes = trim((string) $existingNotes);

        if ($existingNotes === '') {
            return $note;
        }

        return $existingNotes.PHP_EOL.PHP_EOL.$note;
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
