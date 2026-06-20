@extends('layouts.app')

@section('title', 'Stock Overview - ' . config('app.name'))

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Stock Overview</h1>
            <p class="text-muted mb-0">Review current warehouse stock balances.</p>
        </div>

        @can('permission', 'stock-adjustments.create')
            <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary">Stock Adjustment</a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" action="{{ route('stock.index') }}" class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="warehouse_id" class="form-label">Warehouse</label>
                    <select name="warehouse_id" id="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror">
                        <option value="">All Warehouses</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>
                                {{ $warehouse->name }} ({{ $warehouse->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('warehouse_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-5">
                    <label for="product_id" class="form-label">Product</label>
                    <select name="product_id" id="product_id" class="form-select @error('product_id') is-invalid @enderror">
                        <option value="">All Products</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) ($filters['product_id'] ?? '') === (string) $product->id)>
                                {{ $product->name }} ({{ $product->sku }})
                            </option>
                        @endforeach
                    </select>
                    @error('product_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                    <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
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
                            <th scope="col" class="text-end">Reserved Quantity</th>
                            <th scope="col" class="text-end">Available Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stocks as $stock)
                            @php
                                $availableQuantity = (float) $stock->quantity - (float) $stock->reserved_quantity;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $stock->warehouse?->name ?? 'N/A' }}</div>
                                    <div class="small text-muted">{{ $stock->warehouse?->code ?? 'N/A' }}</div>
                                </td>
                                <td class="fw-semibold">{{ $stock->product?->name ?? 'N/A' }}</td>
                                <td><code>{{ $stock->product?->sku ?? 'N/A' }}</code></td>
                                <td>{{ $stock->product?->category?->name ?? 'N/A' }}</td>
                                <td>{{ $stock->product?->unit?->short_name ?? $stock->product?->unit?->name ?? 'N/A' }}</td>
                                <td class="text-end">{{ number_format((float) $stock->quantity, 4) }}</td>
                                <td class="text-end">{{ number_format((float) $stock->reserved_quantity, 4) }}</td>
                                <td class="text-end">{{ number_format($availableQuantity, 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No stock records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($stocks->hasPages())
            <div class="card-footer bg-white">
                {{ $stocks->links() }}
            </div>
        @endif
    </div>
@endsection
