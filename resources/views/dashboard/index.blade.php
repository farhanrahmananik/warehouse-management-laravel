@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))

@section('content')
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h3 mb-2">Warehouse Management Dashboard</h1>
            <p class="text-muted mb-4">This is a protected placeholder page.</p>

            <dl class="row mb-4">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ auth()->user()->name }}</dd>

                <dt class="col-sm-3">Email</dt>
                <dd class="col-sm-9">{{ auth()->user()->email }}</dd>
            </dl>

            <div class="d-flex flex-column gap-2">
                @can('permission', 'dashboard.view')
                    <div class="alert alert-success mb-0">Dashboard permission verified</div>
                @endcan

                @can('role', 'super-admin')
                    <div class="alert alert-primary mb-0">Super Admin access enabled</div>
                @endcan
            </div>
        </div>
    </div>
@endsection
