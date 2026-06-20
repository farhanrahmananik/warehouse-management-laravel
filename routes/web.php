<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\SupplierController;
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
    Route::get('/dashboard', function () {
        return view('dashboard.index');
    })->middleware('permission:dashboard.view')->name('dashboard');

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
});
