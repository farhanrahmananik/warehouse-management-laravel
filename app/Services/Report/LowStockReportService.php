<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Category;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LowStockReportService
{
    /**
     * @param  array{warehouse_id?: int|string|null, category_id?: int|string|null, stock_status?: string|null}  $filters
     * @return array{
     *     lowStockRows: LengthAwarePaginator,
     *     warehouses: Collection<int, Warehouse>,
     *     categories: Collection<int, Category>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        return [
            'lowStockRows' => $this->lowStockRows($normalizedFilters),
            'warehouses' => $this->activeWarehouses(),
            'categories' => $this->activeCategories(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function lowStockRows(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $availableQuantityExpression = '(warehouse_stocks.quantity - warehouse_stocks.reserved_quantity)';
        $shortageQuantityExpression = "CASE
            WHEN products.reorder_level - {$availableQuantityExpression} > 0 THEN products.reorder_level - {$availableQuantityExpression}
            ELSE 0
        END";

        $query = WarehouseStock::query()
            ->with(['warehouse', 'product.category', 'product.unit'])
            ->join('warehouses', 'warehouse_stocks.warehouse_id', '=', 'warehouses.id')
            ->join('products', 'warehouse_stocks.product_id', '=', 'products.id')
            ->select('warehouse_stocks.*')
            ->selectRaw($availableQuantityExpression.' as available_quantity')
            ->selectRaw($shortageQuantityExpression.' as shortage_quantity')
            ->selectRaw(
                "CASE
                    WHEN {$availableQuantityExpression} <= 0 THEN 'out_of_stock'
                    ELSE 'low_stock'
                END as stock_status"
            )
            ->where(function ($query) use ($availableQuantityExpression) {
                $query->whereRaw($availableQuantityExpression.' <= products.reorder_level')
                    ->orWhereRaw($availableQuantityExpression.' <= 0');
            })
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_stocks.warehouse_id', $warehouseId);
            })
            ->when($filters['category_id'] ?? null, function ($query, $categoryId) {
                $query->where('products.category_id', $categoryId);
            })
            ->when($filters['stock_status'] ?? null, function ($query, string $stockStatus) use ($availableQuantityExpression) {
                match ($stockStatus) {
                    'low_stock' => $query
                        ->whereRaw($availableQuantityExpression.' > 0')
                        ->whereRaw($availableQuantityExpression.' <= products.reorder_level'),
                    'out_of_stock' => $query->whereRaw($availableQuantityExpression.' <= 0'),
                    default => null,
                };
            })
            ->orderByRaw($shortageQuantityExpression.' DESC')
            ->orderBy('warehouses.name')
            ->orderBy('warehouses.code')
            ->orderBy('products.name');

        return $query->paginate($perPage)->appends($this->filledFilters($filters));
    }

    private function activeWarehouses(): Collection
    {
        return Warehouse::active()
            ->orderBy('name')
            ->orderBy('code')
            ->get();
    }

    private function activeCategories(): Collection
    {
        return Category::active()
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
            'category_id' => null,
            'stock_status' => null,
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
