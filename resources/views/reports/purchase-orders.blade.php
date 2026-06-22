@extends('layouts.app')

@section('title', 'Purchase Order Report - ' . config('app.name'))

@section('content')
    @php
        $purchaseOrderRows = $purchaseOrderRows ?? collect();
        $suppliers = $suppliers ?? collect();
        $warehouses = $warehouses ?? collect();
        $statuses = $statuses ?? [];
        $filters = $filters ?? [];

        $statusBadgeClasses = [
            'draft' => 'text-bg-secondary',
            'approved' => 'text-bg-primary',
            'partially_received' => 'text-bg-warning',
            'received' => 'text-bg-success',
            'cancelled' => 'text-bg-danger',
        ];
    @endphp

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Purchase Order Report</h1>
            <p class="text-muted mb-0">Review read-only purchase order history, totals, and receiving progress.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.purchase-orders') }}" class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="supplier_id" class="form-label">Supplier</label>
                    <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) data_get($filters, 'supplier_id', '') === (string) $supplier->id)>
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
                            <option value="{{ $warehouse->id }}" @selected((string) data_get($filters, 'warehouse_id', '') === (string) $warehouse->id)>
                                {{ $warehouse->name }} ({{ $warehouse->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('warehouse_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                        <option value="">All Statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(data_get($filters, 'status') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
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

                <div class="col-md-2">
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

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('reports.purchase-orders') }}" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Purchase Orders</h2>
        </div>
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
                            <th scope="col" class="text-end">Items</th>
                            <th scope="col" class="text-end">Ordered Qty</th>
                            <th scope="col" class="text-end">Received Qty</th>
                            <th scope="col" class="text-end">Pending Qty</th>
                            <th scope="col" class="text-end">Total Amount</th>
                            <th scope="col">Created By</th>
                            <th scope="col">Approved By</th>
                            <th scope="col">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchaseOrderRows as $purchaseOrder)
                            @php
                                $canReadLoadedRelations = is_object($purchaseOrder) && method_exists($purchaseOrder, 'relationLoaded');
                                $supplier = $canReadLoadedRelations && $purchaseOrder->relationLoaded('supplier') ? $purchaseOrder->supplier : null;
                                $warehouse = $canReadLoadedRelations && $purchaseOrder->relationLoaded('warehouse') ? $purchaseOrder->warehouse : null;
                                $createdBy = $canReadLoadedRelations && $purchaseOrder->relationLoaded('createdBy') ? $purchaseOrder->createdBy : null;
                                $approvedBy = $canReadLoadedRelations && $purchaseOrder->relationLoaded('approvedBy') ? $purchaseOrder->approvedBy : null;
                                $status = data_get($purchaseOrder, 'status');
                            @endphp
                            <tr>
                                <td><code>{{ data_get($purchaseOrder, 'po_number', '-') }}</code></td>
                                <td class="fw-semibold">{{ $supplier?->name ?? '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $warehouse?->name ?? '-' }}</div>
                                    <div class="small text-muted">{{ $warehouse?->code ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadgeClasses[$status] ?? 'text-bg-secondary' }}">
                                        {{ $statuses[$status] ?? ucfirst(str_replace('_', ' ', (string) $status)) }}
                                    </span>
                                </td>
                                <td>{{ $purchaseOrder->order_date?->format('M d, Y') ?? '-' }}</td>
                                <td>{{ $purchaseOrder->expected_date?->format('M d, Y') ?? '-' }}</td>
                                <td class="text-end">{{ number_format((float) data_get($purchaseOrder, 'items_count', 0)) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($purchaseOrder, 'ordered_quantity', 0), 3) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($purchaseOrder, 'received_quantity', 0), 3) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($purchaseOrder, 'pending_quantity', 0), 3) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($purchaseOrder, 'total_amount', 0), 2) }}</td>
                                <td>{{ $createdBy?->name ?? 'System' }}</td>
                                <td>{{ $approvedBy?->name ?? '-' }}</td>
                                <td>{{ data_get($purchaseOrder, 'notes') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center text-muted py-4">No purchase orders found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (method_exists($purchaseOrderRows, 'hasPages') && $purchaseOrderRows->hasPages())
            <div class="card-footer bg-white">
                {{ $purchaseOrderRows->links() }}
            </div>
        @endif
    </div>
@endsection
