@extends('layouts.app')

@section('title', 'Supplier Details - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Supplier Details</h1>
            <p class="text-muted mb-0">Review supplier information.</p>
        </div>

        <div class="d-flex gap-2">
            @can('permission', 'suppliers.update')
                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary">Edit</a>
            @endcan

            <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ $supplier->name }}</dd>

                <dt class="col-sm-3">Company Name</dt>
                <dd class="col-sm-9">{{ $supplier->company_name ?: 'N/A' }}</dd>

                <dt class="col-sm-3">Email</dt>
                <dd class="col-sm-9">{{ $supplier->email ?: 'N/A' }}</dd>

                <dt class="col-sm-3">Phone</dt>
                <dd class="col-sm-9">{{ $supplier->phone ?: 'N/A' }}</dd>

                <dt class="col-sm-3">Address</dt>
                <dd class="col-sm-9">{{ $supplier->address ?: 'N/A' }}</dd>

                <dt class="col-sm-3">Tax Number</dt>
                <dd class="col-sm-9">{{ $supplier->tax_number ?: 'N/A' }}</dd>

                <dt class="col-sm-3">Opening Balance</dt>
                <dd class="col-sm-9">{{ number_format((float) $supplier->opening_balance, 2) }}</dd>

                <dt class="col-sm-3">Current Balance</dt>
                <dd class="col-sm-9">{{ number_format((float) $supplier->current_balance, 2) }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if ($supplier->is_active)
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-secondary">Inactive</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Created At</dt>
                <dd class="col-sm-9">{{ $supplier->created_at?->format('M d, Y h:i A') }}</dd>

                <dt class="col-sm-3">Updated At</dt>
                <dd class="col-sm-9">{{ $supplier->updated_at?->format('M d, Y h:i A') }}</dd>
            </dl>
        </div>
    </div>
@endsection
