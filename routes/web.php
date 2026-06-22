<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\SupplierController;
use App\Http\Controllers\PurchaseOrder\PurchaseOrderController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Stock\StockAdjustmentController;
use App\Http\Controllers\Stock\StockController;
use App\Http\Controllers\Stock\StockInController;
use App\Http\Controllers\Stock\StockMovementController;
use App\Http\Controllers\Stock\StockOutController;
use App\Http\Controllers\Stock\StockTransferController;
use App\Http\Controllers\Warehouse\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('/categories', [CategoryController::class, 'index'])
        ->middleware('permission:categories.view')
        ->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])
        ->middleware('permission:categories.create')
        ->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])
        ->middleware('permission:categories.create')
        ->name('categories.store');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])
        ->middleware('permission:categories.view')
        ->name('categories.show');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])
        ->middleware('permission:categories.update')
        ->name('categories.edit');
    Route::match(['put', 'patch'], '/categories/{category}', [CategoryController::class, 'update'])
        ->middleware('permission:categories.update')
        ->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
        ->middleware('permission:categories.delete')
        ->name('categories.destroy');

    Route::get('/suppliers', [SupplierController::class, 'index'])
        ->middleware('permission:suppliers.view')
        ->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])
        ->middleware('permission:suppliers.create')
        ->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->middleware('permission:suppliers.create')
        ->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])
        ->middleware('permission:suppliers.view')
        ->name('suppliers.show');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
        ->middleware('permission:suppliers.update')
        ->name('suppliers.edit');
    Route::match(['put', 'patch'], '/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->middleware('permission:suppliers.update')
        ->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])
        ->middleware('permission:suppliers.delete')
        ->name('suppliers.destroy');

    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:products.view')
        ->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])
        ->middleware('permission:products.create')
        ->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('permission:products.create')
        ->name('products.store');
    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->middleware('permission:products.view')
        ->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->middleware('permission:products.update')
        ->name('products.edit');
    Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:products.update')
        ->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:products.delete')
        ->name('products.destroy');

    Route::get('/warehouses', [WarehouseController::class, 'index'])
        ->middleware('permission:warehouses.view')
        ->name('warehouses.index');
    Route::get('/warehouses/create', [WarehouseController::class, 'create'])
        ->middleware('permission:warehouses.create')
        ->name('warehouses.create');
    Route::post('/warehouses', [WarehouseController::class, 'store'])
        ->middleware('permission:warehouses.create')
        ->name('warehouses.store');
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show'])
        ->middleware('permission:warehouses.view')
        ->name('warehouses.show');
    Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])
        ->middleware('permission:warehouses.update')
        ->name('warehouses.edit');
    Route::match(['put', 'patch'], '/warehouses/{warehouse}', [WarehouseController::class, 'update'])
        ->middleware('permission:warehouses.update')
        ->name('warehouses.update');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])
        ->middleware('permission:warehouses.delete')
        ->name('warehouses.destroy');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])
        ->middleware('permission:purchase-orders.view')
        ->name('purchase-orders.index');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])
        ->middleware('permission:purchase-orders.create')
        ->name('purchase-orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware('permission:purchase-orders.create')
        ->name('purchase-orders.store');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
        ->middleware('permission:purchase-orders.view')
        ->name('purchase-orders.show');
    Route::get('/purchase-orders/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])
        ->middleware('permission:purchase-orders.update')
        ->name('purchase-orders.edit');
    Route::match(['put', 'patch'], '/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->middleware('permission:purchase-orders.update')
        ->name('purchase-orders.update');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
        ->middleware('permission:purchase-orders.approve')
        ->name('purchase-orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])
        ->middleware('permission:purchase-orders.receive')
        ->name('purchase-orders.receive');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->middleware('permission:purchase-orders.delete')
        ->name('purchase-orders.cancel');
    Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])
        ->middleware('permission:purchase-orders.delete')
        ->name('purchase-orders.destroy');

    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('reports.index');
    Route::get('/reports/inventory', [ReportController::class, 'inventory'])
        ->middleware('permission:reports.inventory.view')
        ->name('reports.inventory');
    Route::get('/reports/stock-movements', [ReportController::class, 'stockMovements'])
        ->middleware('permission:reports.stock-movements.view')
        ->name('reports.stock-movements');
    Route::get('/reports/low-stock', [ReportController::class, 'lowStock'])
        ->middleware('permission:reports.low-stock.view')
        ->name('reports.low-stock');
    Route::get('/reports/purchase-orders', [ReportController::class, 'purchaseOrders'])
        ->middleware('permission:reports.purchase-orders.view')
        ->name('reports.purchase-orders');

    Route::get('/stock', [StockController::class, 'index'])
        ->middleware('permission:stock.view')
        ->name('stock.index');

    Route::get('/stock-movements', [StockMovementController::class, 'index'])
        ->middleware('permission:stock.view')
        ->name('stock-movements.index');

    Route::get('/stock-ins', [StockInController::class, 'index'])
        ->middleware('permission:stock-in.view')
        ->name('stock-ins.index');
    Route::get('/stock-ins/create', [StockInController::class, 'create'])
        ->middleware('permission:stock-in.create')
        ->name('stock-ins.create');
    Route::post('/stock-ins', [StockInController::class, 'store'])
        ->middleware('permission:stock-in.create')
        ->name('stock-ins.store');
    Route::get('/stock-ins/{stockIn}', [StockInController::class, 'show'])
        ->middleware('permission:stock-in.view')
        ->name('stock-ins.show');

    Route::get('/stock-outs', [StockOutController::class, 'index'])
        ->middleware('permission:stock-out.view')
        ->name('stock-outs.index');
    Route::get('/stock-outs/create', [StockOutController::class, 'create'])
        ->middleware('permission:stock-out.create')
        ->name('stock-outs.create');
    Route::post('/stock-outs', [StockOutController::class, 'store'])
        ->middleware('permission:stock-out.create')
        ->name('stock-outs.store');
    Route::get('/stock-outs/{stockOut}', [StockOutController::class, 'show'])
        ->middleware('permission:stock-out.view')
        ->name('stock-outs.show');

    Route::get('/stock-transfers', [StockTransferController::class, 'index'])
        ->middleware('permission:stock-transfer.view')
        ->name('stock-transfers.index');
    Route::get('/stock-transfers/create', [StockTransferController::class, 'create'])
        ->middleware('permission:stock-transfer.create')
        ->name('stock-transfers.create');
    Route::post('/stock-transfers', [StockTransferController::class, 'store'])
        ->middleware('permission:stock-transfer.create')
        ->name('stock-transfers.store');
    Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])
        ->middleware('permission:stock-transfer.view')
        ->name('stock-transfers.show');

    Route::get('/stock-adjustments/create', [StockAdjustmentController::class, 'create'])
        ->middleware('permission:stock-adjustments.create')
        ->name('stock-adjustments.create');
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])
        ->middleware('permission:stock-adjustments.create')
        ->name('stock-adjustments.store');
});
