@php
    $authenticatedUser = auth()->user() ?? abort(403);
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg bg-white border-bottom shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">
                {{ config('app.name') }}
            </a>

            <div class="d-flex align-items-center gap-3 ms-auto">
                <div class="text-end d-none d-sm-block">
                    <div class="fw-semibold">{{ $authenticatedUser->name }}</div>
                    <div class="small text-muted">{{ $authenticatedUser->email }}</div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm">Log out</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row min-vh-100">
            <aside class="col-12 col-md-3 col-lg-2 bg-white border-end p-3">
                <div class="list-group list-group-flush">
                    @can('permission', 'dashboard.view')
                        <a class="list-group-item list-group-item-action px-0" href="{{ route('dashboard') }}">
                            Dashboard
                        </a>
                    @endcan

                    @if (
                        auth()->user()->can('permission', 'categories.view') ||
                        auth()->user()->can('permission', 'suppliers.view') ||
                        auth()->user()->can('permission', 'products.view')
                    )
                        <div class="small fw-semibold text-uppercase text-muted mt-4 mb-2">Catalog</div>

                        @can('permission', 'categories.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('categories.*') ? 'active' : '' }}"
                                href="{{ route('categories.index') }}"
                            >
                                Categories
                            </a>
                        @endcan

                        @can('permission', 'suppliers.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"
                                href="{{ route('suppliers.index') }}"
                            >
                                Suppliers
                            </a>
                        @endcan

                        @can('permission', 'products.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('products.*') ? 'active' : '' }}"
                                href="{{ route('products.index') }}"
                            >
                                Products
                            </a>
                        @endcan
                    @endif

                    @can('permission', 'warehouses.view')
                        <div class="small fw-semibold text-uppercase text-muted mt-4 mb-2">Warehouse</div>

                        <a
                            class="list-group-item list-group-item-action px-0 {{ request()->routeIs('warehouses.*') ? 'active' : '' }}"
                            href="{{ route('warehouses.index') }}"
                        >
                            Warehouses
                        </a>
                    @endcan

                    @can('permission', 'purchase-orders.view')
                        <div class="small fw-semibold text-uppercase text-muted mt-4 mb-2">Purchasing</div>

                        <a
                            class="list-group-item list-group-item-action px-0 {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}"
                            href="{{ route('purchase-orders.index') }}"
                        >
                            Purchase Orders
                        </a>
                    @endcan

                    @if (
                        auth()->user()->can('permission', 'stock.view') ||
                        auth()->user()->can('permission', 'stock-in.view') ||
                        auth()->user()->can('permission', 'stock-out.view') ||
                        auth()->user()->can('permission', 'stock-transfer.view') ||
                        auth()->user()->can('permission', 'stock-adjustments.create')
                    )
                        <div class="small fw-semibold text-uppercase text-muted mt-4 mb-2">Stock</div>

                        @can('permission', 'stock.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('stock.*') ? 'active' : '' }}"
                                href="{{ route('stock.index') }}"
                            >
                                Stock Overview
                            </a>

                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('stock-movements.*') ? 'active' : '' }}"
                                href="{{ route('stock-movements.index') }}"
                            >
                                Stock Movement Ledger
                            </a>
                        @endcan

                        @can('permission', 'stock-in.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('stock-ins.*') ? 'active' : '' }}"
                                href="{{ route('stock-ins.index') }}"
                            >
                                Stock In
                            </a>
                        @endcan

                        @can('permission', 'stock-out.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('stock-outs.*') ? 'active' : '' }}"
                                href="{{ route('stock-outs.index') }}"
                            >
                                Stock Out
                            </a>
                        @endcan

                        @can('permission', 'stock-transfer.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}"
                                href="{{ route('stock-transfers.index') }}"
                            >
                                Stock Transfers
                            </a>
                        @endcan

                        @can('permission', 'stock-adjustments.create')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('stock-adjustments.*') ? 'active' : '' }}"
                                href="{{ route('stock-adjustments.create') }}"
                            >
                                Stock Adjustment
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
                        <div class="small fw-semibold text-uppercase text-muted mt-4 mb-2">Reports</div>

                        @can('permission', 'reports.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('reports.index') ? 'active' : '' }}"
                                href="{{ route('reports.index') }}"
                            >
                                Reports Overview
                            </a>
                        @endcan

                        @can('permission', 'reports.inventory.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('reports.inventory') ? 'active' : '' }}"
                                href="{{ route('reports.inventory') }}"
                            >
                                Inventory Report
                            </a>
                        @endcan

                        @can('permission', 'reports.stock-movements.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('reports.stock-movements') ? 'active' : '' }}"
                                href="{{ route('reports.stock-movements') }}"
                            >
                                Stock Movement Report
                            </a>
                        @endcan

                        @can('permission', 'reports.low-stock.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('reports.low-stock') ? 'active' : '' }}"
                                href="{{ route('reports.low-stock') }}"
                            >
                                Low Stock Report
                            </a>
                        @endcan

                        @can('permission', 'reports.purchase-orders.view')
                            <a
                                class="list-group-item list-group-item-action px-0 {{ request()->routeIs('reports.purchase-orders') ? 'active' : '' }}"
                                href="{{ route('reports.purchase-orders') }}"
                            >
                                Purchase Order Report
                            </a>
                        @endcan
                    @endif

                    @can('permission', 'audit_logs.view')
                        <div class="small fw-semibold text-uppercase text-muted mt-4 mb-2">System</div>

                        <a
                            class="list-group-item list-group-item-action px-0 {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}"
                            href="{{ route('audit-logs.index') }}"
                        >
                            Audit Logs
                        </a>
                    @endcan

                    @can('role', 'super-admin')
                        <span class="badge text-bg-primary align-self-start my-3">Super Admin</span>
                    @endcan
                </div>
            </aside>

            <main class="col-12 col-md-9 col-lg-10 p-4">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
