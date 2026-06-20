@extends('layouts.app')

@section('title', 'Warehouse Details - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Warehouse Details</h1>
            <p class="text-muted mb-0">Review warehouse information.</p>
        </div>

        <div class="d-flex gap-2">
            @can('permission', 'warehouses.update')
                <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-primary">Edit</a>
            @endcan

            <a href="{{ route('warehouses.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Code</dt>
                <dd class="col-sm-9"><code>{{ $warehouse->code }}</code></dd>

                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ $warehouse->name }}</dd>

                <dt class="col-sm-3">Contact Person</dt>
                <dd class="col-sm-9">{{ $warehouse->contact_person ?: 'N/A' }}</dd>

                <dt class="col-sm-3">Phone</dt>
                <dd class="col-sm-9">{{ $warehouse->phone ?: 'N/A' }}</dd>

                <dt class="col-sm-3">Email</dt>
                <dd class="col-sm-9">{{ $warehouse->email ?: 'N/A' }}</dd>

                <dt class="col-sm-3">City</dt>
                <dd class="col-sm-9">{{ $warehouse->city ?: 'N/A' }}</dd>

                <dt class="col-sm-3">Address</dt>
                <dd class="col-sm-9">{{ $warehouse->address ?: 'N/A' }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if ($warehouse->is_active)
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-secondary">Inactive</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Created At</dt>
                <dd class="col-sm-9">{{ $warehouse->created_at?->format('M d, Y h:i A') }}</dd>

                <dt class="col-sm-3">Updated At</dt>
                <dd class="col-sm-9">{{ $warehouse->updated_at?->format('M d, Y h:i A') }}</dd>
            </dl>
        </div>
    </div>
@endsection
