@extends('layouts.app')

@section('title', 'Product Details - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Product Details</h1>
            <p class="text-muted mb-0">Review product master information.</p>
        </div>

        <div class="d-flex gap-2">
            @can('permission', 'products.update')
                <a href="{{ route('products.edit', $product) }}" class="btn btn-primary">Edit</a>
            @endcan

            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9">{{ $product->name }}</dd>

                <dt class="col-sm-3">Slug</dt>
                <dd class="col-sm-9"><code>{{ $product->slug }}</code></dd>

                <dt class="col-sm-3">SKU</dt>
                <dd class="col-sm-9"><code>{{ $product->sku }}</code></dd>

                <dt class="col-sm-3">Barcode</dt>
                <dd class="col-sm-9">{{ $product->barcode ?: '-' }}</dd>

                <dt class="col-sm-3">Category</dt>
                <dd class="col-sm-9">{{ $product->category?->name ?? '-' }}</dd>

                <dt class="col-sm-3">Unit</dt>
                <dd class="col-sm-9">
                    {{ $product->unit?->name ?? '-' }}
                    @if ($product->unit?->short_name)
                        <span class="text-muted">({{ $product->unit->short_name }})</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Description</dt>
                <dd class="col-sm-9">{{ $product->description ?: 'No description provided.' }}</dd>

                <dt class="col-sm-3">Purchase Price</dt>
                <dd class="col-sm-9">{{ number_format((float) $product->purchase_price, 2) }}</dd>

                <dt class="col-sm-3">Selling Price</dt>
                <dd class="col-sm-9">{{ number_format((float) $product->selling_price, 2) }}</dd>

                <dt class="col-sm-3">Reorder Level</dt>
                <dd class="col-sm-9">{{ number_format((float) $product->reorder_level, 2) }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    @if ($product->is_active)
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-secondary">Inactive</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Created At</dt>
                <dd class="col-sm-9">{{ $product->created_at?->format('M d, Y h:i A') }}</dd>

                <dt class="col-sm-3">Updated At</dt>
                <dd class="col-sm-9">{{ $product->updated_at?->format('M d, Y h:i A') }}</dd>
            </dl>
        </div>
    </div>
@endsection
