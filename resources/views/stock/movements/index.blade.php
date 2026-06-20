@extends('layouts.app')

@section('title', 'Stock Movement Ledger - ' . config('app.name'))

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Stock Movement Ledger</h1>
            <p class="text-muted mb-0">Review historical stock movements across warehouses and products.</p>
        </div>

        <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary">Back to Stock Overview</a>
    </div>

    <form method="GET" action="{{ route('stock-movements.index') }}" class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
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

                <div class="col-md-4">
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

                <div class="col-md-4">
                    <label for="movement_type" class="form-label">Movement Type</label>
                    <select name="movement_type" id="movement_type" class="form-select @error('movement_type') is-invalid @enderror">
                        <option value="">All Movement Types</option>
                        @foreach ($movementTypes as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['movement_type'] ?? '') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('movement_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
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

                <div class="col-md-3">
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

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                    <a href="{{ route('stock-movements.index') }}" class="btn btn-outline-secondary">Reset</a>
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
                            <th scope="col">Date</th>
                            <th scope="col">Warehouse</th>
                            <th scope="col">Product</th>
                            <th scope="col">SKU</th>
                            <th scope="col">Movement Type</th>
                            <th scope="col" class="text-end">Quantity</th>
                            <th scope="col" class="text-end">Balance After</th>
                            <th scope="col">Created By</th>
                            <th scope="col">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr>
                                <td>{{ $movement->created_at?->format('Y-m-d H:i') ?? 'N/A' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $movement->warehouse?->name ?? 'N/A' }}</div>
                                    <div class="small text-muted">{{ $movement->warehouse?->code ?? 'N/A' }}</div>
                                </td>
                                <td class="fw-semibold">{{ $movement->product?->name ?? 'N/A' }}</td>
                                <td><code>{{ $movement->product?->sku ?? 'N/A' }}</code></td>
                                <td>{{ $movementTypes[$movement->movement_type] ?? str_replace('_', ' ', ucfirst($movement->movement_type)) }}</td>
                                <td class="text-end">{{ number_format((float) $movement->quantity, 4) }}</td>
                                <td class="text-end">{{ number_format((float) $movement->balance_after, 4) }}</td>
                                <td>{{ $movement->creator?->name ?? 'System' }}</td>
                                <td>{{ $movement->remarks ?: 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No stock movements found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($movements->hasPages())
            <div class="card-footer bg-white">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
@endsection
