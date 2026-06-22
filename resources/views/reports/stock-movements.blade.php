@extends('layouts.app')

@section('title', 'Stock Movement Report - ' . config('app.name'))

@section('content')
    @php
        $movementRows = $movementRows ?? collect();
        $warehouses = $warehouses ?? collect();
        $products = $products ?? collect();
        $movementTypes = $movementTypes ?? [];
        $filters = $filters ?? [];
    @endphp

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Stock Movement Report</h1>
            <p class="text-muted mb-0">Review read-only stock movement history across warehouses and products.</p>
        </div>
        @can('permission', 'reports.export')
            <a href="{{ route('reports.stock-movements.export', request()->query()) }}" class="btn btn-outline-primary">
                Export CSV
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('reports.stock-movements') }}" class="card shadow-sm border-0 mb-4">
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

                <div class="col-md-4">
                    <label for="movement_type" class="form-label">Movement Type</label>
                    <select name="movement_type" id="movement_type" class="form-select @error('movement_type') is-invalid @enderror">
                        <option value="">All Movement Types</option>
                        @foreach ($movementTypes as $value => $label)
                            <option value="{{ $value }}" @selected(data_get($filters, 'movement_type') === $value)>
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
                        value="{{ data_get($filters, 'date_from') }}"
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
                        value="{{ data_get($filters, 'date_to') }}"
                        class="form-control @error('date_to') is-invalid @enderror"
                    >
                    @error('date_to')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 d-flex justify-content-end gap-2">
                    <a href="{{ route('reports.stock-movements') }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Stock Movements</h2>
        </div>
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
                            <th scope="col">Reference / Source</th>
                            <th scope="col">Created By</th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movementRows as $movement)
                            @php
                                $canReadLoadedRelations = is_object($movement) && method_exists($movement, 'relationLoaded');
                                $warehouse = $canReadLoadedRelations && $movement->relationLoaded('warehouse') ? $movement->warehouse : null;
                                $product = $canReadLoadedRelations && $movement->relationLoaded('product') ? $movement->product : null;
                                $creator = $canReadLoadedRelations && $movement->relationLoaded('creator') ? $movement->creator : null;
                                $movementType = data_get($movement, 'movement_type');
                                $referenceType = data_get($movement, 'reference_type');
                                $referenceId = data_get($movement, 'reference_id');
                            @endphp
                            <tr>
                                <td>{{ $movement->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $warehouse?->name ?? '-' }}</div>
                                    <div class="small text-muted">{{ $warehouse?->code ?? '-' }}</div>
                                </td>
                                <td class="fw-semibold">{{ $product?->name ?? '-' }}</td>
                                <td><code>{{ $product?->sku ?? '-' }}</code></td>
                                <td>{{ $movementTypes[$movementType] ?? ucfirst(str_replace('_', ' ', (string) $movementType)) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($movement, 'quantity', 0), 4) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($movement, 'balance_after', 0), 4) }}</td>
                                <td>
                                    @if ($referenceType && $referenceId)
                                        {{ class_basename($referenceType) }} #{{ $referenceId }}
                                    @elseif ($referenceType)
                                        {{ class_basename($referenceType) }}
                                    @elseif ($referenceId)
                                        #{{ $referenceId }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $creator?->name ?? 'System' }}</td>
                                <td>{{ data_get($movement, 'remarks') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No stock movements found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (method_exists($movementRows, 'hasPages') && $movementRows->hasPages())
            <div class="card-footer bg-white">
                {{ $movementRows->links() }}
            </div>
        @endif
    </div>
@endsection
