<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\Category;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

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
     * @param  array{warehouse_id?: int|string|null, product_id?: int|string|null, category_id?: int|string|null, stock_status?: string|null}  $filters
     * @return LazyCollection<int, list<string>>
     */
    public function exportRows(array $filters): LazyCollection
    {
        return $this->baseInventoryQuery($this->normalizeFilters($filters))
            ->lazy()
            ->map(function (WarehouseStock $stock): array {
                $warehouse = $stock->warehouse;
                $product = $stock->product;

                return [
                    $warehouse?->name ?? '',
                    $product?->sku ?? '',
                    $product?->name ?? '',
                    $product?->category?->name ?? '',
                    $this->formatDecimal($stock->quantity, 4),
                    $this->formatDecimal($stock->reserved_quantity, 4),
                    $this->formatDecimal($stock->available_quantity, 4),
                    $this->formatDecimal($product?->reorder_level, 2),
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function inventoryRows(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseInventoryQuery($filters)
            ->paginate($perPage)
            ->appends($this->filledFilters($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseInventoryQuery(array $filters): Builder
    {
        $availableQuantityExpression = '(warehouse_stocks.quantity - warehouse_stocks.reserved_quantity)';

        return WarehouseStock::query()
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

    private function formatDecimal(mixed $value, int $decimals): string
    {
        return number_format((float) ($value ?? 0), $decimals, '.', '');
    }
}
