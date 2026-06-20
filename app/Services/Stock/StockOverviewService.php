<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class StockOverviewService
{
    /**
     * @param  array{warehouse_id?: int|string|null, product_id?: int|string|null}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = WarehouseStock::query()
            ->with(['warehouse', 'product.category', 'product.unit'])
            ->join('warehouses', 'warehouse_stocks.warehouse_id', '=', 'warehouses.id')
            ->join('products', 'warehouse_stocks.product_id', '=', 'products.id')
            ->select('warehouse_stocks.*')
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_stocks.warehouse_id', $warehouseId);
            })
            ->when($filters['product_id'] ?? null, function ($query, $productId) {
                $query->where('warehouse_stocks.product_id', $productId);
            })
            ->orderBy('warehouses.name')
            ->orderBy('warehouses.code')
            ->orderBy('products.name');

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
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function filledFilters(array $filters): array
    {
        return array_filter($filters, fn ($value): bool => $value !== null && $value !== '');
    }
}
