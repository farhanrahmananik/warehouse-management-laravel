@extends('layouts.app')

@section('title', 'Inventory Report - ' . config('app.name'))

@section('content')
    @php
        $inventoryRows = $inventoryRows ?? collect();
        $warehouses = $warehouses ?? collect();
        $products = $products ?? collect();
        $categories = $categories ?? collect();
        $filters = $filters ?? [];

        $stockStatusLabels = [
            'in_stock' => 'In Stock',
            'low_stock' => 'Low Stock',
            'out_of_stock' => 'Out of Stock',
        ];

        $stockStatusBadgeClasses = [
            'in_stock' => 'text-bg-success',
            'low_stock' => 'text-bg-warning',
            'out_of_stock' => 'text-bg-danger',
        ];
    @endphp

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Inventory Report</h1>
            <p class="text-muted mb-0">Review read-only warehouse inventory balances.</p>
        </div>
        @can('permission', 'reports.export')
            <a href="{{ route('reports.inventory.export', request()->query()) }}" class="btn btn-outline-primary">
                Export CSV
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('reports.inventory') }}" class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="warehouse_id" class="form-label">Warehouse</label>
                    <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror">
                        <option value="">All Warehouses</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) data_get($filters, 'warehouse_id', '') === (string) $warehouse->id)>
                                {{ $warehouse->name }} ({{ $warehouse->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('warehouse_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) data_get($filters, 'category_id', '') === (string) $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label for="product_id" class="form-label">Product</label>
                    <select name="product_id" id="product_id" class="form-select @error('product_id') is-invalid @enderror">
                        <option value="">All Products</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) data_get($filters, 'product_id', '') === (string) $product->id)>
                                {{ $product->name }} ({{ $product->sku }})
                            </option>
                        @endforeach
                    </select>
                    @error('product_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label for="stock_status" class="form-label">Stock Status</label>
                    <select name="stock_status" id="stock_status" class="form-select @error('stock_status') is-invalid @enderror">
                        <option value="">All Statuses</option>
                        @foreach ($stockStatusLabels as $status => $label)
                            <option value="{{ $status }}" @selected(data_get($filters, 'stock_status') === $status)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('stock_status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('reports.inventory') }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Inventory Balances</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Warehouse</th>
                            <th scope="col">Product</th>
                            <th scope="col">SKU</th>
                            <th scope="col">Category</th>
                            <th scope="col">Unit</th>
                            <th scope="col" class="text-end">Quantity</th>
                            <th scope="col" class="text-end">Reserved</th>
                            <th scope="col" class="text-end">Available</th>
                            <th scope="col" class="text-end">Low Stock Level</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($inventoryRows as $inventoryRow)
                            @php
                                $canReadLoadedRelations = is_object($inventoryRow) && method_exists($inventoryRow, 'relationLoaded');
                                $warehouse = $canReadLoadedRelations && $inventoryRow->relationLoaded('warehouse') ? $inventoryRow->warehouse : null;
                                $product = $canReadLoadedRelations && $inventoryRow->relationLoaded('product') ? $inventoryRow->product : null;
                                $category = $product && $product->relationLoaded('category') ? $product->category : null;
                                $unit = $product && $product->relationLoaded('unit') ? $product->unit : null;
                                $stockStatus = data_get($inventoryRow, 'stock_status', 'out_of_stock');
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $warehouse?->name ?? 'N/A' }}</div>
                                    <div class="small text-muted">{{ $warehouse?->code ?? 'N/A' }}</div>
                                </td>
                                <td class="fw-semibold">{{ $product?->name ?? 'N/A' }}</td>
                                <td><code>{{ $product?->sku ?? 'N/A' }}</code></td>
                                <td>{{ $category?->name ?? 'N/A' }}</td>
                                <td>{{ $unit?->short_name ?? $unit?->name ?? 'N/A' }}</td>
                                <td class="text-end">{{ number_format((float) data_get($inventoryRow, 'quantity', 0), 4) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($inventoryRow, 'reserved_quantity', 0), 4) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($inventoryRow, 'available_quantity', 0), 4) }}</td>
                                <td class="text-end">{{ number_format((float) ($product?->reorder_level ?? 0), 2) }}</td>
                                <td>
                                    <span class="badge {{ $stockStatusBadgeClasses[$stockStatus] ?? 'text-bg-secondary' }}">
                                        {{ $stockStatusLabels[$stockStatus] ?? ucfirst(str_replace('_', ' ', $stockStatus)) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No inventory records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (method_exists($inventoryRows, 'hasPages') && $inventoryRows->hasPages())
            <div class="card-footer bg-white">
                {{ $inventoryRows->links() }}
            </div>
        @endif
    </div>
@endsection
