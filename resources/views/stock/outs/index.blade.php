@extends('layouts.app')

@section('title', 'Stock Out - ' . config('app.name'))

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Stock Out</h1>
            <p class="text-muted mb-0">Review issued stock out documents.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary">Stock Overview</a>

            @can('permission', 'stock-out.create')
                <a href="{{ route('stock-outs.create') }}" class="btn btn-primary">Create Stock Out</a>
            @endcan
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <form method="GET" action="{{ route('stock-outs.index') }}" class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
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

                <div class="col-md-3">
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

                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input
                        type="date"
                        name="date_from"
                        id="date_from"
                        value="{{ $filters['date_from'] ?? '' }}"
                        class="form-control @error('date_from') is-invalid @enderror"
                    >
                    @error('date_from')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input
                        type="date"
                        name="date_to"
                        id="date_to"
                        value="{{ $filters['date_to'] ?? '' }}"
                        class="form-control @error('date_to') is-invalid @enderror"
                    >
                    @error('date_to')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                    <a href="{{ route('stock-outs.index') }}" class="btn btn-outline-secondary">Reset</a>
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
                            <th scope="col">Document No</th>
                            <th scope="col">Date</th>
                            <th scope="col">Warehouse</th>
                            <th scope="col">Created By</th>
                            <th scope="col" class="text-end">Total Items</th>
                            <th scope="col" class="text-end">Total Quantity</th>
                            <th scope="col">Created At</th>
                            <th scope="col" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stockOuts as $stockOut)
                            <tr>
                                <td><code>{{ $stockOut->document_no }}</code></td>
                                <td>{{ $stockOut->stock_date?->format('M d, Y') ?? '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $stockOut->warehouse?->name ?? 'N/A' }}</div>
                                    <div class="small text-muted">{{ $stockOut->warehouse?->code ?? 'N/A' }}</div>
                                </td>
                                <td>{{ $stockOut->creator?->name ?? 'System' }}</td>
                                <td class="text-end">{{ $stockOut->items->count() }}</td>
                                <td class="text-end">{{ number_format((float) $stockOut->items->sum('quantity'), 4) }}</td>
                                <td>{{ $stockOut->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>
                                    <div class="d-flex justify-content-end">
                                        @can('permission', 'stock-out.view')
                                            <a href="{{ route('stock-outs.show', $stockOut) }}" class="btn btn-outline-secondary btn-sm">
                                                View
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No stock out documents found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($stockOuts->hasPages())
            <div class="card-footer bg-white">
                {{ $stockOuts->links() }}
            </div>
        @endif
    </div>
@endsection
