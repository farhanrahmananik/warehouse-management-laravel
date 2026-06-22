@extends('layouts.app')

@section('title', 'Low Stock Report - ' . config('app.name'))

@section('content')
    @php
        $lowStockRows = $lowStockRows ?? collect();
        $warehouses = $warehouses ?? collect();
        $categories = $categories ?? collect();
        $filters = $filters ?? [];

        $stockStatusLabels = [
            'low_stock' => 'Low Stock',
            'out_of_stock' => 'Out of Stock',
        ];

        $stockStatusBadgeClasses = [
            'low_stock' => 'text-bg-warning',
            'out_of_stock' => 'text-bg-danger',
        ];
    @endphp

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Low Stock Report</h1>
            <p class="text-muted mb-0">Review read-only products that are at or below their reorder level.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.low-stock') }}" class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
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

                <div class="col-md-4">
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

                <div class="col-md-4">
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
                    <a href="{{ route('reports.low-stock') }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Low Stock Items</h2>
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
                            <th scope="col" class="text-end">Shortage</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lowStockRows as $lowStockRow)
                            @php
                                $canReadLoadedRelations = is_object($lowStockRow) && method_exists($lowStockRow, 'relationLoaded');
                                $warehouse = $canReadLoadedRelations && $lowStockRow->relationLoaded('warehouse') ? $lowStockRow->warehouse : null;
                                $product = $canReadLoadedRelations && $lowStockRow->relationLoaded('product') ? $lowStockRow->product : null;
                                $category = $product && $product->relationLoaded('category') ? $product->category : null;
                                $unit = $product && $product->relationLoaded('unit') ? $product->unit : null;
                                $stockStatus = data_get($lowStockRow, 'stock_status', 'low_stock');
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $warehouse?->name ?? '-' }}</div>
                                    <div class="small text-muted">{{ $warehouse?->code ?? '-' }}</div>
                                </td>
                                <td class="fw-semibold">{{ $product?->name ?? '-' }}</td>
                                <td><code>{{ $product?->sku ?? '-' }}</code></td>
                                <td>{{ $category?->name ?? '-' }}</td>
                                <td>{{ $unit?->short_name ?? $unit?->name ?? '-' }}</td>
                                <td class="text-end">{{ number_format((float) data_get($lowStockRow, 'quantity', 0), 4) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($lowStockRow, 'reserved_quantity', 0), 4) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($lowStockRow, 'available_quantity', 0), 4) }}</td>
                                <td class="text-end">{{ number_format((float) ($product?->reorder_level ?? 0), 2) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($lowStockRow, 'shortage_quantity', 0), 4) }}</td>
                                <td>
                                    <span class="badge {{ $stockStatusBadgeClasses[$stockStatus] ?? 'text-bg-secondary' }}">
                                        {{ $stockStatusLabels[$stockStatus] ?? ucfirst(str_replace('_', ' ', (string) $stockStatus)) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No low stock records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (method_exists($lowStockRows, 'hasPages') && $lowStockRows->hasPages())
            <div class="card-footer bg-white">
                {{ $lowStockRows->links() }}
            </div>
        @endif
    </div>
@endsection
