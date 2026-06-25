@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))
@section('page-title', 'Dashboard')

@section('content')
    @php
        $dashboardData = $dashboardData ?? [];
        $totals = $dashboardData['totals'] ?? [];
        $purchaseOrders = $dashboardData['purchaseOrders'] ?? [];
        $stockWorkflows = $dashboardData['stockWorkflows'] ?? [];
        $lowStockProducts = collect($dashboardData['lowStockProducts'] ?? []);
        $recentStockMovements = collect($dashboardData['recentStockMovements'] ?? []);

        $summaryIcons = [
            'products' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.5 8.5 12 4l7.5 4.5v8.8L12 21.5l-7.5-4.2V8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M4.8 8.7 12 12.8l7.2-4.1M12 12.8v8.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'categories' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 4 8 4-8 4-8-4 8-4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m4 12 8 4 8-4M4 16l8 4 8-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'suppliers' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.5 7.5h10v8h-10v-8ZM13.5 10h3.8l3.2 3.2v2.3h-7V10Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M6.5 18.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM17.5 18.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 15.5h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'warehouses' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.5 10 12 5l8.5 5v9.5h-17V10Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 19.5v-6h10v6M9.5 13.5v6M14.5 13.5v6M7 10h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'total-stock' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7.5 12 4l7 3.5-7 3.5-7-3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M5 12.5 12 16l7-3.5M5 16.5 12 20l7-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'reserved-stock' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M6.5 10h11A1.5 1.5 0 0 1 19 11.5v7A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-7A1.5 1.5 0 0 1 6.5 10Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 14v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'available-stock' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.5 8.5 12 4l7.5 4.5v8.8L12 21.5l-7.5-4.2V8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m9 13.2 2 2 4-4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];

        $summaryCards = [
            ['label' => 'Products', 'value' => data_get($totals, 'products', 0), 'format' => 'integer', 'icon' => 'products', 'tone' => 'primary'],
            ['label' => 'Categories', 'value' => data_get($totals, 'categories', 0), 'format' => 'integer', 'icon' => 'categories', 'tone' => 'info'],
            ['label' => 'Suppliers', 'value' => data_get($totals, 'suppliers', 0), 'format' => 'integer', 'icon' => 'suppliers', 'tone' => 'success'],
            ['label' => 'Warehouses', 'value' => data_get($totals, 'warehouses', 0), 'format' => 'integer', 'icon' => 'warehouses', 'tone' => 'warning'],
            ['label' => 'Total Stock', 'value' => data_get($totals, 'stock_quantity', 0), 'format' => 'decimal4', 'icon' => 'total-stock', 'tone' => 'primary'],
            ['label' => 'Reserved Stock', 'value' => data_get($totals, 'reserved_quantity', 0), 'format' => 'decimal4', 'icon' => 'reserved-stock', 'tone' => 'danger'],
            ['label' => 'Available Stock', 'value' => data_get($totals, 'available_quantity', 0), 'format' => 'decimal4', 'icon' => 'available-stock', 'tone' => 'success'],
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

    <section class="dashboard-hero mb-4">
        <div>
            <span class="dashboard-kicker">Operations Overview</span>
            <h1 class="dashboard-title">Warehouse Management Dashboard</h1>
            <p class="dashboard-subtitle">Monitor catalog, purchasing, and stock activity with a quick read on current inventory health.</p>
        </div>

        <div class="dashboard-hero-panel">
            <div class="small text-white-50">Signed in as</div>
            <div class="fw-semibold">{{ auth()->user()->name }}</div>
            <div class="small text-white-50">{{ auth()->user()->email }}</div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        @foreach ($summaryCards as $card)
            <div class="col-sm-6 col-xl-3">
                <div class="metric-card metric-card-{{ $card['tone'] }} h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <div class="metric-label">{{ $card['label'] }}</div>
                                <div class="metric-value">
                                    @if ($card['format'] === 'decimal4')
                                        {{ number_format((float) $card['value'], 4) }}
                                    @else
                                        {{ number_format((int) $card['value']) }}
                                    @endif
                                </div>
                            </div>
                            <span class="metric-icon" aria-hidden="true">{!! $summaryIcons[$card['icon']] ?? '' !!}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card app-card h-100">
                <div class="card-header app-card-header">
                    <div>
                        <h2 class="h5 mb-0">Purchase Orders</h2>
                        <p class="small text-muted mb-0">Status distribution across active purchasing work.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($purchaseOrderStatusLabels as $status => $label)
                            <div class="col-sm-6">
                                <div class="status-tile h-100">
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
            <div class="card app-card h-100">
                <div class="card-header app-card-header">
                    <div>
                        <h2 class="h5 mb-0">Stock Workflows</h2>
                        <p class="small text-muted mb-0">Document activity for warehouse stock movement.</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($stockWorkflowLabels as $workflow => $label)
                            <div class="col-sm-4">
                                <div class="workflow-tile h-100">
                                    <div class="small text-muted mb-1">{{ $label }}</div>
                                    <div class="h3 mb-0">{{ number_format((int) data_get($stockWorkflows, $workflow, 0)) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="access-status mt-4">
                        <div class="access-status-title">System access</div>
                        <div class="d-flex flex-wrap gap-2">
                            @can('permission', 'dashboard.view')
                                <span class="access-chip access-chip-success">Dashboard permission verified</span>
                            @endcan

                            @can('role', 'super-admin')
                                <span class="access-chip access-chip-primary">Super Admin access enabled</span>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card app-card dashboard-table-card mb-4">
        <div class="card-header app-card-header">
            <div>
                <h2 class="h5 mb-0">Low Stock Products</h2>
                <p class="small text-muted mb-0">Products at or below reorder threshold.</p>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive dashboard-table-wrap">
                <table class="table modern-table dashboard-table align-middle mb-0">
                    <thead>
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
                                <td colspan="6" class="empty-state-cell">
                                    <div class="dashboard-empty-state">
                                        <span class="dashboard-empty-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="m12 4 8.5 15h-17L12 4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                                <path d="M12 9v4.5M12 17h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                            </svg>
                                        </span>
                                        <span class="dashboard-empty-title">No low stock products found.</span>
                                        <span class="dashboard-empty-subtitle">Inventory is currently above the configured reorder thresholds.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card app-card dashboard-table-card">
        <div class="card-header app-card-header">
            <div>
                <h2 class="h5 mb-0">Recent Stock Movements</h2>
                <p class="small text-muted mb-0">Latest ledger activity across products and warehouses.</p>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive dashboard-table-wrap">
                <table class="table modern-table dashboard-table align-middle mb-0">
                    <thead>
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
                                <td colspan="6" class="empty-state-cell">
                                    <div class="dashboard-empty-state">
                                        <span class="dashboard-empty-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M6 6.5h12M6 12h12M6 17.5h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                <path d="m15.5 15.5 2 2 3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <span class="dashboard-empty-title">No recent stock movements found.</span>
                                        <span class="dashboard-empty-subtitle">New warehouse activity will appear here as stock moves.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
