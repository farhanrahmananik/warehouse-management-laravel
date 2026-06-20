@extends('layouts.app')

@section('title', 'Edit Warehouse - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Edit Warehouse</h1>
            <p class="text-muted mb-0">Update warehouse details.</p>
        </div>

        <a href="{{ route('warehouses.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form method="POST" action="{{ route('warehouses.update', $warehouse) }}" class="card shadow-sm border-0">
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('warehouse.warehouses._form', ['warehouse' => $warehouse])
        </div>

        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('warehouses.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Warehouse</button>
        </div>
    </form>
@endsection
