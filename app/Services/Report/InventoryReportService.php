<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class InventoryReportService
{
    /**
     * @param  array{warehouse_id?: int|string|null, product_id?: int|string|null, category_id?: int|string|null, stock_status?: string|null}  $filters
     * @return array{
     *     inventoryRows: LengthAwarePaginator,
     *     warehouses: Collection<int, Warehouse>,
     *     products: Collection<int, Product>,
     *     categories: Collection<int, Category>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);

        return [
            'inventoryRows' => $this->inventoryRows($normalizedFilters),
            'warehouses' => $this->activeWarehouses(),
            'products' => $this->activeProducts(),
            'categories' => $this->activeCategories(),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function inventoryRows(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $availableQuantityExpression = '(warehouse_stocks.quantity - warehouse_stocks.reserved_quantity)';

        $query = WarehouseStock::query()
            ->with(['warehouse', 'product.category', 'product.unit'])
            ->join('warehouses', 'warehouse_stocks.warehouse_id', '=', 'warehouses.id')
            ->join('products', 'warehouse_stocks.product_id', '=', 'products.id')
            ->select('warehouse_stocks.*')
            ->selectRaw($availableQuantityExpression.' as available_quantity')
            ->selectRaw(
                "CASE
                    WHEN {$availableQuantityExpression} <= 0 THEN 'out_of_stock'
                    WHEN {$availableQuantityExpression} <= products.reorder_level THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status"
            )
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_stocks.warehouse_id', $warehouseId);
            })
            ->when($filters['product_id'] ?? null, function ($query, $productId) {
                $query->where('warehouse_stocks.product_id', $productId);
            })
            ->when($filters['category_id'] ?? null, function ($query, $categoryId) {
                $query->where('products.category_id', $categoryId);
            })
            ->when($filters['stock_status'] ?? null, function ($query, string $stockStatus) use ($availableQuantityExpression) {
                match ($stockStatus) {
                    'in_stock' => $query->whereRaw($availableQuantityExpression.' > products.reorder_level'),
                    'low_stock' => $query
                        ->whereRaw($availableQuantityExpression.' > 0')
                        ->whereRaw($availableQuantityExpression.' <= products.reorder_level'),
                    'out_of_stock' => $query->whereRaw($availableQuantityExpression.' <= 0'),
                    default => null,
                };
            })
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

    private function activeProducts(): Collection
    {
        return Product::active()
            ->with(['category', 'unit'])
            ->orderBy('name')
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
            'product_id' => null,
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
