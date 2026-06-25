@php
    $brandIcon = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 10.5 12 6l8 4.5v7L12 22l-8-4.5v-7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M4.5 10.75 12 15l7.5-4.25M12 15v6.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
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
<body class="auth-body">
    <main class="auth-shell">
        <div class="auth-visual">
            <a href="{{ route('login') }}" class="auth-brand">
                <span class="brand-mark">{!! $brandIcon !!}</span>
                <span>
                    <span class="brand-title">WarehousePro</span>
                    <span class="brand-subtitle">Inventory Suite</span>
                </span>
            </a>

            <div class="auth-copy">
                <span class="auth-kicker">Warehouse Operations</span>
                <h1>Manage inventory with clarity.</h1>
                <p>Track products, warehouses, stock movements, purchase orders, reports, and audit history from one focused workspace.</p>
            </div>

            <div class="auth-highlights">
                <span>Stock Ledger</span>
                <span>Purchase Orders</span>
                <span>Audit Logs</span>
            </div>
        </div>

        <div class="auth-panel">
            <div class="auth-card-wrap">
                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>
