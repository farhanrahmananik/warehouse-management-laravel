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

                    @can('role', 'super-admin')
                        <span class="badge text-bg-primary align-self-start my-3">Super Admin</span>
                    @endcan

                    @can('permission', 'reports.view')
                        <div class="small text-muted mt-2">Reports access available</div>
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
