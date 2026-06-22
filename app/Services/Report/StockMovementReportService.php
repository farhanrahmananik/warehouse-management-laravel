<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class StockMovementReportService
{
    /**
     * @param  array{warehouse_id?: int|string|null, product_id?: int|string|null, movement_type?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return array{
     *     movementRows: LengthAwarePaginator,
     *     warehouses: Collection<int, Warehouse>,
     *     products: Collection<int, Product>,
     *     movementTypes: array<string, string>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        return [
            'movementRows' => $this->movementRows($normalizedFilters),
            'warehouses' => $this->activeWarehouses(),
            'products' => $this->activeProducts(),
            'movementTypes' => self::movementTypes(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function movementTypes(): array
    {
        return [
            'opening_balance' => 'Opening Balance',
            'adjustment_in' => 'Adjustment In',
            'adjustment_out' => 'Adjustment Out',
            'purchase_in' => 'Purchase In',
            'stock_in' => 'Stock In',
            'stock_out' => 'Stock Out',
            'transfer_in' => 'Transfer In',
            'transfer_out' => 'Transfer Out',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function movementRows(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = StockMovement::query()
            ->with(['warehouse', 'product.category', 'product.unit', 'creator'])
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->when($filters['product_id'] ?? null, function ($query, $productId) {
                $query->where('product_id', $productId);
            })
            ->when($filters['movement_type'] ?? null, function ($query, $movementType) {
                $query->where('movement_type', $movementType);
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
            })
            ->latest('created_at')
            ->latest('id');

        return $query->paginate($perPage)->appends($this->filledFilters($filters));
    }

    private function activeWarehouses(): Collection
    {
        return Warehouse::active()
            ->orderBy('name')
            ->orderBy('code')
            ->get();
    }

    private function activeProducts(): Collection
    {
        return Product::active()
            ->with(['category', 'unit'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $expectedFilters = [
            'warehouse_id' => null,
            'product_id' => null,
            'movement_type' => null,
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
