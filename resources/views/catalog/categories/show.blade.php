@extends('layouts.app')

@section('title', 'Category Details - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Category Details</h1>
            <p class="text-muted mb-0">Review category information.</p>
        </div>

        <div class="d-flex gap-2">
            @can('permission', 'categories.update')
                <a href="{{ route('categories.edit', $category) }}" class="btn btn-primary">Edit</a>
            @endcan

            <a href="{{ route('categories.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ $category->name }}</dd>

                <dt class="col-sm-3">Slug</dt>
                <dd class="col-sm-9"><code>{{ $category->slug }}</code></dd>

                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9">{{ $category->description ?: 'No description provided.' }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if ($category->is_active)
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-secondary">Inactive</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Created At</dt>
                <dd class="col-sm-9">{{ $category->created_at?->format('M d, Y h:i A') }}</dd>

                <dt class="col-sm-3">Updated At</dt>
                <dd class="col-sm-9">{{ $category->updated_at?->format('M d, Y h:i A') }}</dd>
            </dl>
        </div>
    </div>
@endsection
