@php
    $authenticatedUser = auth()->user() ?? abort(403);
    $appName = config('app.name');
    $pageTitle = trim($__env->yieldContent('page-title'));

    if ($pageTitle === '') {
        $rawTitle = trim($__env->yieldContent('title', $appName));
        $titleSuffix = ' - '.$appName;
        $pageTitle = str_ends_with($rawTitle, $titleSuffix)
            ? substr($rawTitle, 0, -strlen($titleSuffix))
            : $rawTitle;
    }

    $brandIcon = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10.5 12 6l8 4.5v7L12 22l-8-4.5v-7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M4.5 10.75 12 15l7.5-4.25M12 15v6.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    $sidebarIcons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h4A1.5 1.5 0 0 1 11 5.5v4A1.5 1.5 0 0 1 9.5 11h-4A1.5 1.5 0 0 1 4 9.5v-4ZM13 5.5A1.5 1.5 0 0 1 14.5 4h4A1.5 1.5 0 0 1 20 5.5v4a1.5 1.5 0 0 1-1.5 1.5h-4A1.5 1.5 0 0 1 13 9.5v-4ZM4 14.5A1.5 1.5 0 0 1 5.5 13h4a1.5 1.5 0 0 1 1.5 1.5v4A1.5 1.5 0 0 1 9.5 20h-4A1.5 1.5 0 0 1 4 18.5v-4ZM13 14.5a1.5 1.5 0 0 1 1.5-1.5h4a1.5 1.5 0 0 1 1.5 1.5v4a1.5 1.5 0 0 1-1.5 1.5h-4a1.5 1.5 0 0 1-1.5-1.5v-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>',
        'categories' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 4 8 4-8 4-8-4 8-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m4 12 8 4 8-4M4 16l8 4 8-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'suppliers' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.5 7.5h10v8h-10v-8ZM13.5 10h3.8l3.2 3.2v2.3h-7V10Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M6.5 18.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM17.5 18.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" stroke="currentColor" stroke-width="1.7"/><path d="M8.5 15.5h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
        'products' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.5 8.5 12 4l7.5 4.5v8.8L12 21.5l-7.5-4.2V8.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M4.8 8.7 12 12.8l7.2-4.1M12 12.8v8.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'warehouses' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.5 10 12 5l8.5 5v9.5h-17V10Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M7 19.5v-6h10v6M9.5 13.5v6M14.5 13.5v6M7 10h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
        'purchase-orders' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8.5 4.5h7L17 6.8h1.5A1.5 1.5 0 0 1 20 8.3v10.2a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5V8.3a1.5 1.5 0 0 1 1.5-1.5H7l1.5-2.3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 11h8M8 14.5h8M8 18h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
        'stock-overview' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4.5 9 9 6.5 13.5 9 9 11.5 4.5 9ZM10.5 14.5 15 12l4.5 2.5L15 17l-4.5-2.5ZM4.5 16 9 13.5l4.5 2.5L9 18.5 4.5 16Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>',
        'stock-movements' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6.5h12M6 12h12M6 17.5h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="m15.5 15.5 2 2 3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'stock-in' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4v9.5m0 0 3.5-3.5M12 13.5 8.5 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 14.5v4h14v-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'stock-out' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 14V4.5m0 0 3.5 3.5M12 4.5 8.5 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 14.5v4h14v-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'stock-transfers' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 7h12m0 0-3-3m3 3-3 3M17 17H5m0 0 3 3m-3-3 3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'stock-adjustments' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h8M17 7h2M5 12h2M11 12h8M5 17h10M19 17h0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M15 5v4M9 10v4M17 15v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'reports' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6.5 4.5h8L18.5 8v11.5h-12v-15Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14.5 4.8V8h3.4M9 17v-4M12.5 17v-7M16 17v-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'inventory-report' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7.5 12 4l7 3.5-7 3.5-7-3.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M5 12.5 12 16l7-3.5M5 16.5 12 20l7-3.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'stock-report' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 18h14M7 15v-4M12 15V6M17 15V9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5 5h4l2 3 2-3h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'low-stock-report' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 4 8.5 15h-17L12 4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 9v4.5M12 17h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>',
        'purchase-report' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 4.5h8l3 3v12h-11v-15Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M15 4.8V8h2.8M9.5 12h6M9.5 15.5h6M9.5 18.5H13" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
        'audit-logs' => '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4.5 18.5 7v5.2c0 4-2.5 6.8-6.5 8.3-4-1.5-6.5-4.3-6.5-8.3V7L12 4.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 12h6M9 15h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
    ];
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $appName)</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
    <div class="app-shell">
        <aside class="app-sidebar" id="app-sidebar" aria-label="Main navigation">
            <div class="sidebar-brand">
                <a href="{{ route('dashboard') }}" class="brand-link">
                    <span class="brand-mark">{!! $brandIcon !!}</span>
                    <span>
                        <span class="brand-title">WarehousePro</span>
                        <span class="brand-subtitle">Inventory Suite</span>
                    </span>
                </a>

                <button type="button" class="btn sidebar-close d-lg-none" data-sidebar-close aria-label="Close navigation">
                    x
                </button>
            </div>

            <nav class="sidebar-nav">
                @can('permission', 'dashboard.view')
                    <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                        <span class="sidebar-icon">{!! $sidebarIcons['dashboard'] !!}</span>
                        <span>Dashboard</span>
                    </a>
                @endcan

                @if (
                    auth()->user()->can('permission', 'categories.view') ||
                    auth()->user()->can('permission', 'suppliers.view') ||
                    auth()->user()->can('permission', 'products.view')
                )
                    <div class="sidebar-section">Catalog</div>

                    @can('permission', 'categories.view')
                        <a class="sidebar-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['categories'] !!}</span>
                            <span>Categories</span>
                        </a>
                    @endcan

                    @can('permission', 'suppliers.view')
                        <a class="sidebar-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['suppliers'] !!}</span>
                            <span>Suppliers</span>
                        </a>
                    @endcan

                    @can('permission', 'products.view')
                        <a class="sidebar-link {{ request()->routeIs('products.*') ? 'active' : '' }}" href="{{ route('products.index') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['products'] !!}</span>
                            <span>Products</span>
                        </a>
                    @endcan
                @endif

                @can('permission', 'warehouses.view')
                    <div class="sidebar-section">Warehouse</div>

                    <a class="sidebar-link {{ request()->routeIs('warehouses.*') ? 'active' : '' }}" href="{{ route('warehouses.index') }}">
                        <span class="sidebar-icon">{!! $sidebarIcons['warehouses'] !!}</span>
                        <span>Warehouses</span>
                    </a>
                @endcan

                @can('permission', 'purchase-orders.view')
                    <div class="sidebar-section">Purchasing</div>

                    <a class="sidebar-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}" href="{{ route('purchase-orders.index') }}">
                        <span class="sidebar-icon">{!! $sidebarIcons['purchase-orders'] !!}</span>
                        <span>Purchase Orders</span>
                    </a>
                @endcan

                @if (
                    auth()->user()->can('permission', 'stock.view') ||
                    auth()->user()->can('permission', 'stock-in.view') ||
                    auth()->user()->can('permission', 'stock-out.view') ||
                    auth()->user()->can('permission', 'stock-transfer.view') ||
                    auth()->user()->can('permission', 'stock-adjustments.create')
                )
                    <div class="sidebar-section">Stock</div>

                    @can('permission', 'stock.view')
                        <a class="sidebar-link {{ request()->routeIs('stock.*') ? 'active' : '' }}" href="{{ route('stock.index') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['stock-overview'] !!}</span>
                            <span>Stock Overview</span>
                        </a>

                        <a class="sidebar-link {{ request()->routeIs('stock-movements.*') ? 'active' : '' }}" href="{{ route('stock-movements.index') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['stock-movements'] !!}</span>
                            <span>Stock Movement Ledger</span>
                        </a>
                    @endcan

                    @can('permission', 'stock-in.view')
                        <a class="sidebar-link {{ request()->routeIs('stock-ins.*') ? 'active' : '' }}" href="{{ route('stock-ins.index') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['stock-in'] !!}</span>
                            <span>Stock In</span>
                        </a>
                    @endcan

                    @can('permission', 'stock-out.view')
                        <a class="sidebar-link {{ request()->routeIs('stock-outs.*') ? 'active' : '' }}" href="{{ route('stock-outs.index') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['stock-out'] !!}</span>
                            <span>Stock Out</span>
                        </a>
                    @endcan

                    @can('permission', 'stock-transfer.view')
                        <a class="sidebar-link {{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}" href="{{ route('stock-transfers.index') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['stock-transfers'] !!}</span>
                            <span>Stock Transfers</span>
                        </a>
                    @endcan

                    @can('permission', 'stock-adjustments.create')
                        <a class="sidebar-link {{ request()->routeIs('stock-adjustments.*') ? 'active' : '' }}" href="{{ route('stock-adjustments.create') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['stock-adjustments'] !!}</span>
                            <span>Stock Adjustment</span>
                        </a>
                    @endcan
                @endif

                @if (
                    auth()->user()->can('permission', 'reports.view') ||
                    auth()->user()->can('permission', 'reports.inventory.view') ||
                    auth()->user()->can('permission', 'reports.stock-movements.view') ||
                    auth()->user()->can('permission', 'reports.low-stock.view') ||
                    auth()->user()->can('permission', 'reports.purchase-orders.view')
                )
                    <div class="sidebar-section">Reports</div>

                    @can('permission', 'reports.view')
                        <a class="sidebar-link {{ request()->routeIs('reports.index') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['reports'] !!}</span>
                            <span>Reports Overview</span>
                        </a>
                    @endcan

                    @can('permission', 'reports.inventory.view')
                        <a class="sidebar-link {{ request()->routeIs('reports.inventory') ? 'active' : '' }}" href="{{ route('reports.inventory') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['inventory-report'] !!}</span>
                            <span>Inventory Report</span>
                        </a>
                    @endcan

                    @can('permission', 'reports.stock-movements.view')
                        <a class="sidebar-link {{ request()->routeIs('reports.stock-movements') ? 'active' : '' }}" href="{{ route('reports.stock-movements') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['stock-report'] !!}</span>
                            <span>Stock Movement Report</span>
                        </a>
                    @endcan

                    @can('permission', 'reports.low-stock.view')
                        <a class="sidebar-link {{ request()->routeIs('reports.low-stock') ? 'active' : '' }}" href="{{ route('reports.low-stock') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['low-stock-report'] !!}</span>
                            <span>Low Stock Report</span>
                        </a>
                    @endcan

                    @can('permission', 'reports.purchase-orders.view')
                        <a class="sidebar-link {{ request()->routeIs('reports.purchase-orders') ? 'active' : '' }}" href="{{ route('reports.purchase-orders') }}">
                            <span class="sidebar-icon">{!! $sidebarIcons['purchase-report'] !!}</span>
                            <span>Purchase Order Report</span>
                        </a>
                    @endcan
                @endif

                @can('permission', 'audit_logs.view')
                    <div class="sidebar-section">System</div>

                    <a class="sidebar-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}">
                        <span class="sidebar-icon">{!! $sidebarIcons['audit-logs'] !!}</span>
                        <span>Audit Logs</span>
                    </a>
                @endcan
            </nav>

            <div class="sidebar-footer">
                @can('role', 'super-admin')
                    <span class="sidebar-badge">Super Admin</span>
                @endcan

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sidebar-logout w-100">Log out</button>
                </form>
            </div>
        </aside>

        <div class="app-content">
            <header class="app-topbar">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-light topbar-toggle d-lg-none" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false">
                        Menu
                    </button>

                    <div class="topbar-heading">
                        <div class="topbar-eyebrow">Warehouse Management</div>
                        <h1 class="topbar-title">{{ $pageTitle }}</h1>
                    </div>
                </div>

                <div class="topbar-user">
                    <div class="user-avatar" aria-hidden="true">
                        {{ strtoupper(substr($authenticatedUser->name, 0, 1)) }}
                    </div>
                    <div class="d-none d-sm-block">
                        <div class="fw-semibold text-dark">{{ $authenticatedUser->name }}</div>
                        <div class="small text-muted">{{ $authenticatedUser->email }}</div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}" class="d-none d-md-block">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Log out</button>
                    </form>
                </div>
            </header>

            <main class="app-main">
                @yield('content')
            </main>
        </div>
    </div>

    <button type="button" class="sidebar-backdrop" data-sidebar-close aria-label="Close navigation"></button>
</body>
</html>
