<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * @return array{
     *     totals: array<string, int|float>,
     *     purchaseOrders: array<string, int>,
     *     stockWorkflows: array<string, int>,
     *     lowStockProducts: Collection<int, array<string, int|float|string|null>>,
     *     recentStockMovements: EloquentCollection<int, StockMovement>
     * }
     */
    public function getDashboardData(): array
    {
        return [
            'totals' => $this->totals(),
            'purchaseOrders' => $this->purchaseOrders(),
            'stockWorkflows' => $this->stockWorkflows(),
            'lowStockProducts' => $this->lowStockProducts(),
            'recentStockMovements' => $this->recentStockMovements(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function totals(): array
    {
        $stockTotals = WarehouseStock::query()
            ->selectRaw('COALESCE(SUM(quantity), 0) as stock_quantity')
            ->selectRaw('COALESCE(SUM(reserved_quantity), 0) as reserved_quantity')
            ->selectRaw('COALESCE(SUM(quantity - reserved_quantity), 0) as available_quantity')
            ->first();

        return [
            'products' => Product::query()->count(),
            'categories' => Category::query()->count(),
            'suppliers' => Supplier::query()->count(),
            'warehouses' => Warehouse::query()->count(),
            'stock_quantity' => round((float) ($stockTotals?->stock_quantity ?? 0), 4),
            'reserved_quantity' => round((float) ($stockTotals?->reserved_quantity ?? 0), 4),
            'available_quantity' => round((float) ($stockTotals?->available_quantity ?? 0), 4),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function purchaseOrders(): array
    {
        $counts = PurchaseOrder::query()
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        $summary = [];

        foreach (PurchaseOrder::allowedStatuses() as $status) {
            $summary[$status] = (int) ($counts[$status] ?? 0);
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function stockWorkflows(): array
    {
        return [
            'stock_in' => StockIn::query()->count(),
            'stock_out' => StockOut::query()->count(),
            'stock_transfer' => StockTransfer::query()->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, int|float|string|null>>
     */
    private function lowStockProducts(): Collection
    {
        return Product::query()
            ->leftJoin('warehouse_stocks', 'products.id', '=', 'warehouse_stocks.product_id')
            ->select('products.id', 'products.name', 'products.sku', 'products.reorder_level')
            ->selectRaw('COALESCE(SUM(warehouse_stocks.quantity), 0) as current_stock')
            ->selectRaw('COALESCE(SUM(warehouse_stocks.reserved_quantity), 0) as reserved_quantity')
            ->selectRaw('COALESCE(SUM(warehouse_stocks.quantity - warehouse_stocks.reserved_quantity), 0) as available_quantity')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.reorder_level')
            ->havingRaw('COALESCE(SUM(warehouse_stocks.quantity - warehouse_stocks.reserved_quantity), 0) <= products.reorder_level')
            ->orderByRaw('(products.reorder_level - COALESCE(SUM(warehouse_stocks.quantity - warehouse_stocks.reserved_quantity), 0)) DESC')
            ->orderBy('products.name')
            ->limit(10)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'current_stock' => round((float) $product->current_stock, 4),
                'reserved_quantity' => round((float) $product->reserved_quantity, 4),
                'available_quantity' => round((float) $product->available_quantity, 4),
                'threshold_field' => 'reorder_level',
                'reorder_level' => round((float) $product->reorder_level, 2),
            ]);
    }

    /**
     * @return EloquentCollection<int, StockMovement>
     */
    private function recentStockMovements(): EloquentCollection
    {
        return StockMovement::query()
            ->with(['product', 'warehouse', 'creator'])
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get();
    }
}
