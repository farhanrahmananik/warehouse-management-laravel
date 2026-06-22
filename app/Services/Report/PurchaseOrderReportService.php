<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PurchaseOrderReportService
{
    /**
     * @param  array{supplier_id?: int|string|null, warehouse_id?: int|string|null, status?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return array{
     *     purchaseOrderRows: LengthAwarePaginator,
     *     suppliers: Collection<int, Supplier>,
     *     warehouses: Collection<int, Warehouse>,
     *     statuses: array<string, string>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        return [
            'purchaseOrderRows' => $this->purchaseOrderRows($normalizedFilters),
            'suppliers' => $this->activeSuppliers(),
            'warehouses' => $this->activeWarehouses(),
            'statuses' => self::statuses(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            PurchaseOrder::STATUS_DRAFT => 'Draft',
            PurchaseOrder::STATUS_APPROVED => 'Approved',
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'Partially Received',
            PurchaseOrder::STATUS_RECEIVED => 'Received',
            PurchaseOrder::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function purchaseOrderRows(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()
            ->with(['supplier', 'warehouse', 'createdBy', 'approvedBy'])
            ->select('purchase_orders.*')
            ->selectSub(function ($query) {
                $query->from('purchase_order_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('purchase_order_items.purchase_order_id', 'purchase_orders.id');
            }, 'items_count')
            ->selectSub(function ($query) {
                $query->from('purchase_order_items')
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('purchase_order_items.purchase_order_id', 'purchase_orders.id');
            }, 'ordered_quantity')
            ->selectSub(function ($query) {
                $query->from('purchase_order_items')
                    ->selectRaw('COALESCE(SUM(received_quantity), 0)')
                    ->whereColumn('purchase_order_items.purchase_order_id', 'purchase_orders.id');
            }, 'received_quantity')
            ->selectSub(function ($query) {
                $query->from('purchase_order_items')
                    ->selectRaw('COALESCE(SUM(quantity - received_quantity), 0)')
                    ->whereColumn('purchase_order_items.purchase_order_id', 'purchase_orders.id');
            }, 'pending_quantity')
            ->when($filters['supplier_id'] ?? null, function ($query, $supplierId) {
                $query->where('supplier_id', $supplierId);
            })
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->when($filters['status'] ?? null, function ($query, string $status) {
                $query->where('status', $status);
            })
            ->when($filters['date_from'] ?? null, function ($query, string $dateFrom) {
                $query->whereDate('order_date', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, string $dateTo) {
                $query->whereDate('order_date', '<=', $dateTo);
            })
            ->latest('order_date')
            ->latest('id');

        return $query->paginate($perPage)->appends($this->filledFilters($filters));
    }

    private function activeSuppliers(): Collection
    {
        return Supplier::active()
            ->orderBy('name')
            ->get();
    }

    private function activeWarehouses(): Collection
    {
        return Warehouse::active()
            ->orderBy('name')
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $expectedFilters = [
            'supplier_id' => null,
            'warehouse_id' => null,
            'status' => null,
            'date_from' => null,
            'date_to' => null,
        ];

        foreach ($expectedFilters as $key => $defaultValue) {
            $value = $filters[$key] ?? $defaultValue;
            $expectedFilters[$key] = $value === '' ? null : $value;
        }

        return $expectedFilters;
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
