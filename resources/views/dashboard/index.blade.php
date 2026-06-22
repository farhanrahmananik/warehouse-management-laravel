@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))

@section('content')
    @php
        $dashboardData = $dashboardData ?? [];
        $totals = $dashboardData['totals'] ?? [];
        $purchaseOrders = $dashboardData['purchaseOrders'] ?? [];
        $stockWorkflows = $dashboardData['stockWorkflows'] ?? [];
        $lowStockProducts = collect($dashboardData['lowStockProducts'] ?? []);
        $recentStockMovements = collect($dashboardData['recentStockMovements'] ?? []);

        $summaryCards = [
            ['label' => 'Products', 'value' => data_get($totals, 'products', 0), 'format' => 'integer'],
            ['label' => 'Categories', 'value' => data_get($totals, 'categories', 0), 'format' => 'integer'],
            ['label' => 'Suppliers', 'value' => data_get($totals, 'suppliers', 0), 'format' => 'integer'],
            ['label' => 'Warehouses', 'value' => data_get($totals, 'warehouses', 0), 'format' => 'integer'],
            ['label' => 'Total Stock', 'value' => data_get($totals, 'stock_quantity', 0), 'format' => 'decimal4'],
            ['label' => 'Reserved Stock', 'value' => data_get($totals, 'reserved_quantity', 0), 'format' => 'decimal4'],
            ['label' => 'Available Stock', 'value' => data_get($totals, 'available_quantity', 0), 'format' => 'decimal4'],
        ];

        $purchaseOrderStatusLabels = [
            'draft' => 'Draft',
            'approved' => 'Approved',
            'partially_received' => 'Partially Received',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
        ];

        $purchaseOrderStatusClasses = [
            'draft' => 'text-bg-secondary',
            'approved' => 'text-bg-primary',
            'partially_received' => 'text-bg-warning',
            'received' => 'text-bg-success',
            'cancelled' => 'text-bg-danger',
        ];

        $stockWorkflowLabels = [
            'stock_in' => 'Stock In',
            'stock_out' => 'Stock Out',
            'stock_transfer' => 'Stock Transfer',
        ];

        $movementTypeLabels = [
            'opening_balance' => 'Opening Balance',
            'adjustment_in' => 'Adjustment In',
            'adjustment_out' => 'Adjustment Out',
            'purchase_in' => 'Purchase In',
            'stock_in' => 'Stock In',
            'stock_out' => 'Stock Out',
            'transfer_in' => 'Transfer In',
            'transfer_out' => 'Transfer Out',
        ];
    @endphp

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Warehouse Management Dashboard</h1>
            <p class="text-muted mb-0">Monitor catalog, purchasing, and stock activity.</p>
        </div>

        <div class="text-md-end">
            <div class="fw-semibold">{{ auth()->user()->name }}</div>
            <div class="small text-muted">{{ auth()->user()->email }}</div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($summaryCards as $card)
            <div class="col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1">{{ $card['label'] }}</div>
                        <div class="h4 mb-0">
                            @if ($card['format'] === 'decimal4')
                                {{ number_format((float) $card['value'], 4) }}
                            @else
                                {{ number_format((int) $card['value']) }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Purchase Orders</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($purchaseOrderStatusLabels as $status => $label)
                            <div class="col-sm-6">
                                <div class="border rounded p-3 h-100">
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <span class="badge {{ $purchaseOrderStatusClasses[$status] ?? 'text-bg-secondary' }}">
                                            {{ $label }}
                                        </span>
                                        <span class="h5 mb-0">{{ number_format((int) data_get($purchaseOrders, $status, 0)) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Stock Workflows</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($stockWorkflowLabels as $workflow => $label)
                            <div class="col-sm-4">
                                <div class="border rounded p-3 h-100">
                                    <div class="small text-muted mb-1">{{ $label }}</div>
                                    <div class="h4 mb-0">{{ number_format((int) data_get($stockWorkflows, $workflow, 0)) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex flex-column gap-2 mt-4">
                        @can('permission', 'dashboard.view')
                            <div class="alert alert-success mb-0">Dashboard permission verified</div>
                        @endcan

                        @can('role', 'super-admin')
                            <div class="alert alert-primary mb-0">Super Admin access enabled</div>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Low Stock Products</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Product</th>
                            <th scope="col">SKU</th>
                            <th scope="col" class="text-end">Current Stock</th>
                            <th scope="col" class="text-end">Reserved Quantity</th>
                            <th scope="col" class="text-end">Available Quantity</th>
                            <th scope="col" class="text-end">Reorder Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lowStockProducts as $product)
                            <tr>
                                <td class="fw-semibold">{{ data_get($product, 'name', 'N/A') }}</td>
                                <td><code>{{ data_get($product, 'sku', 'N/A') }}</code></td>
                                <td class="text-end">{{ number_format((float) data_get($product, 'current_stock', 0), 4) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($product, 'reserved_quantity', 0), 4) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($product, 'available_quantity', 0), 4) }}</td>
                                <td class="text-end">{{ number_format((float) data_get($product, 'reorder_level', 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No low stock products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Recent Stock Movements</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Product</th>
                            <th scope="col">Warehouse</th>
                            <th scope="col">Type</th>
                            <th scope="col" class="text-end">Quantity</th>
                            <th scope="col">Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentStockMovements as $movement)
                            @php
                                $canReadLoadedRelations = is_object($movement) && method_exists($movement, 'relationLoaded');
                                $product = $canReadLoadedRelations && $movement->relationLoaded('product') ? $movement->product : null;
                                $warehouse = $canReadLoadedRelations && $movement->relationLoaded('warehouse') ? $movement->warehouse : null;
                                $creator = $canReadLoadedRelations && $movement->relationLoaded('creator') ? $movement->creator : null;
                                $movementDate = data_get($movement, 'created_at');
                                $movementType = data_get($movement, 'movement_type', '');
                            @endphp
                            <tr>
                                <td>
                                    {{ is_object($movementDate) && method_exists($movementDate, 'format') ? $movementDate->format('Y-m-d H:i') : ($movementDate ?: '-') }}
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $product?->name ?? 'N/A' }}</div>
                                    <div class="small text-muted">{{ $product?->sku ?? 'N/A' }}</div>
                                </td>
                                <td>
                                    <div>{{ $warehouse?->name ?? 'N/A' }}</div>
                                    <div class="small text-muted">{{ $warehouse?->code ?? 'N/A' }}</div>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border">
                                        {{ $movementTypeLabels[$movementType] ?? ucfirst(str_replace('_', ' ', $movementType)) }}
                                    </span>
                                </td>
                                <td class="text-end">{{ number_format((float) data_get($movement, 'quantity', 0), 4) }}</td>
                                <td>{{ $creator?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No recent stock movements found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
