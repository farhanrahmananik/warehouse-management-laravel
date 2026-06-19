@extends('layouts.app')

@section('title', 'Edit Category - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Edit Category</h1>
            <p class="text-muted mb-0">Update category details.</p>
        </div>

        <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form method="POST" action="{{ route('categories.update', $category) }}" class="card shadow-sm border-0">
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('catalog.categories._form', ['category' => $category])
        </div>

        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Category</button>
        </div>
    </form>
@endsection
