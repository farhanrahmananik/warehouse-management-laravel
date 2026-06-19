@extends('layouts.app')

@section('title', 'Edit Product - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Edit Product</h1>
            <p class="text-muted mb-0">Update product master details.</p>
        </div>

        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form method="POST" action="{{ route('products.update', $product) }}" class="card shadow-sm border-0">
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('catalog.products._form', ['product' => $product])
        </div>

        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Product</button>
        </div>
    </form>
@endsection
