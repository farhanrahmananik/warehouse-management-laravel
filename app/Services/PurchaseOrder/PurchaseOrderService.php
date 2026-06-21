<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WarehouseStock;
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

    /**
     * @param  array{items?: list<array{purchase_order_item_id: int|string, received_quantity?: int|float|string|null}>, notes?: string|null}  $data
     */
    public function receive(PurchaseOrder $purchaseOrder, array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $user): PurchaseOrder {
            $lockedPurchaseOrder = $this->lockedPurchaseOrder($purchaseOrder);

            if (! $lockedPurchaseOrder->isApproved() && ! $lockedPurchaseOrder->isPartiallyReceived()) {
                throw ValidationException::withMessages([
                    'status' => 'Only approved or partially received purchase orders can be received.',
                ]);
            }

            $receiveQuantities = $this->receiveQuantities($data['items'] ?? []);
            $lockedItems = PurchaseOrderItem::query()
                ->where('purchase_order_id', $lockedPurchaseOrder->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach (array_keys($receiveQuantities) as $purchaseOrderItemId) {
                if (! $lockedItems->has($purchaseOrderItemId)) {
                    throw ValidationException::withMessages([
                        'items' => 'All receive items must belong to this purchase order.',
                    ]);
                }
            }

            $positiveReceiveQuantities = array_filter(
                $receiveQuantities,
                fn (float $quantity): bool => $quantity > 0,
            );

            if ($positiveReceiveQuantities === []) {
                throw ValidationException::withMessages([
                    'items' => 'At least one item must have a received quantity greater than zero.',
                ]);
            }

            foreach ($positiveReceiveQuantities as $purchaseOrderItemId => $receivedQuantity) {
                /** @var PurchaseOrderItem $item */
                $item = $lockedItems->get($purchaseOrderItemId);
                $remainingQuantity = round((float) $item->quantity - (float) $item->received_quantity, 3);

                if ($receivedQuantity > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        'items' => 'Received quantity cannot exceed remaining quantity.',
                    ]);
                }

                $newReceivedQuantity = round((float) $item->received_quantity + $receivedQuantity, 3);
                $item->update([
                    'received_quantity' => $newReceivedQuantity,
                ]);

                $stock = $this->lockedWarehouseStock((int) $lockedPurchaseOrder->warehouse_id, (int) $item->product_id);
                $balanceAfter = round((float) $stock->quantity + $receivedQuantity, 4);

                $stock->update([
                    'quantity' => $balanceAfter,
                ]);

                StockMovement::create([
                    'warehouse_id' => $lockedPurchaseOrder->warehouse_id,
                    'product_id' => $item->product_id,
                    'movement_type' => 'purchase_in',
                    'quantity' => $receivedQuantity,
                    'balance_after' => $balanceAfter,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => $lockedPurchaseOrder->id,
                    'remarks' => 'Purchase order received: '.$lockedPurchaseOrder->po_number,
                    'created_by' => $user->id,
                ]);
            }

            $updateData = [
                'status' => $lockedItems->every(function (PurchaseOrderItem $item): bool {
                    return round((float) $item->received_quantity, 3) >= round((float) $item->quantity, 3);
                })
                    ? PurchaseOrder::STATUS_RECEIVED
                    : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ];

            if ($updateData['status'] === PurchaseOrder::STATUS_RECEIVED) {
                $updateData['received_at'] = now();
                $updateData['received_by'] = $user->id;
            }

            $receivingNotes = trim((string) ($data['notes'] ?? ''));

            if ($receivingNotes !== '') {
                $updateData['notes'] = $this->appendReceivingNote(
                    $lockedPurchaseOrder->notes,
                    $receivingNotes,
                );
            }

            $lockedPurchaseOrder->update($updateData);

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

        WarehouseStock::create([
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
     * @param  list<array{purchase_order_item_id: int|string, received_quantity?: int|float|string|null}>  $items
     * @return array<int, float>
     */
    private function receiveQuantities(array $items): array
    {
        $receiveQuantities = [];

        foreach ($items as $item) {
            $purchaseOrderItemId = (int) $item['purchase_order_item_id'];
            $receivedQuantity = round((float) ($item['received_quantity'] ?? 0), 3);
            $receiveQuantities[$purchaseOrderItemId] = round(
                ($receiveQuantities[$purchaseOrderItemId] ?? 0) + $receivedQuantity,
                3,
            );
        }

        return $receiveQuantities;
    }

    private function appendReceivingNote(?string $existingNotes, string $receivingNotes): string
    {
        $note = '[Received '.now()->format('Y-m-d H:i:s').'] '.$receivingNotes;
        $existingNotes = trim((string) $existingNotes);

        if ($existingNotes === '') {
            return $note;
        }

        return $existingNotes.PHP_EOL.PHP_EOL.$note;
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
