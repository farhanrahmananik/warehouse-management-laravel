@extends('layouts.app')

@section('title', 'Edit Supplier - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Edit Supplier</h1>
            <p class="text-muted mb-0">Update supplier details.</p>
        </div>

        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="card shadow-sm border-0">
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('catalog.suppliers._form', ['supplier' => $supplier])
        </div>

        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Supplier</button>
        </div>
    </form>
@endsection
