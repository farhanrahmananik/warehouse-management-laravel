@extends('layouts.app')

@section('title', 'Purchase Orders - ' . config('app.name'))

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Purchase Orders</h1>
            <p class="text-muted mb-0">Manage supplier purchase orders.</p>
        </div>

        @can('permission', 'purchase-orders.create')
            <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary">Create Purchase Order</a>
        @endcan
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

    @php
        $statusLabels = [
            'draft' => 'Draft',
            'approved' => 'Approved',
            'partially_received' => 'Partially Received',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
        ];
        $statusBadgeClasses = [
            'draft' => 'text-bg-secondary',
            'approved' => 'text-bg-primary',
            'partially_received' => 'text-bg-warning',
            'received' => 'text-bg-success',
            'cancelled' => 'text-bg-danger',
        ];
    @endphp

    <form method="GET" action="{{ route('purchase-orders.index') }}" class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="">All Statuses</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label for="supplier_id" class="form-label">Supplier</label>
                    <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) ($filters['supplier_id'] ?? '') === (string) $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

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

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary">Filter</button>
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
                            <th scope="col">PO Number</th>
                            <th scope="col">Supplier</th>
                            <th scope="col">Warehouse</th>
                            <th scope="col">Status</th>
                            <th scope="col">Order Date</th>
                            <th scope="col">Expected Date</th>
                            <th scope="col" class="text-end">Total</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchaseOrders as $purchaseOrder)
                            @php
                                $status = $purchaseOrder->status;
                            @endphp
                            <tr>
                                <td><code>{{ $purchaseOrder->po_number }}</code></td>
                                <td class="fw-semibold">{{ $purchaseOrder->supplier?->name ?? '-' }}</td>
                                <td>
                                    <div>{{ $purchaseOrder->warehouse?->name ?? '-' }}</div>
                                    <div class="small text-muted">{{ $purchaseOrder->warehouse?->code ?? '' }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadgeClasses[$status] ?? 'text-bg-secondary' }}">
                                        {{ $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
                                    </span>
                                </td>
                                <td>{{ $purchaseOrder->order_date?->format('M d, Y') ?? '-' }}</td>
                                <td>{{ $purchaseOrder->expected_date?->format('M d, Y') ?? '-' }}</td>
                                <td class="text-end">{{ number_format((float) $purchaseOrder->total_amount, 2) }}</td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        @can('permission', 'purchase-orders.view')
                                            <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-outline-secondary btn-sm">
                                                View
                                            </a>
                                        @endcan

                                        @if ($purchaseOrder->isDraft())
                                            @can('permission', 'purchase-orders.update')
                                                <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn btn-outline-primary btn-sm">
                                                    Edit
                                                </a>
                                            @endcan
                                        @endif

                                        @if ($purchaseOrder->isDraft() || $purchaseOrder->isCancelled())
                                            @can('permission', 'purchase-orders.delete')
                                                <form method="POST" action="{{ route('purchase-orders.destroy', $purchaseOrder) }}" onsubmit="return confirm('Delete this purchase order?');">
                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No purchase orders found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($purchaseOrders->hasPages())
            <div class="card-footer bg-white">
                {{ $purchaseOrders->links() }}
            </div>
        @endif
    </div>
@endsection
