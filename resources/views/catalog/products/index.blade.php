@extends('layouts.app')

@section('title', 'Products - ' . config('app.name'))

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Products</h1>
            <p class="text-muted mb-0">Manage product master records.</p>
        </div>

        @can('permission', 'products.create')
            <a href="{{ route('products.create') }}" class="btn btn-primary">Add Product</a>
        @endcan
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Name</th>
                            <th scope="col">SKU</th>
                            <th scope="col">Barcode</th>
                            <th scope="col">Category</th>
                            <th scope="col">Unit</th>
                            <th scope="col">Purchase Price</th>
                            <th scope="col">Selling Price</th>
                            <th scope="col">Reorder Level</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr>
                                <td>{{ $products->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $product->name }}</td>
                                <td><code>{{ $product->sku }}</code></td>
                                <td>{{ $product->barcode ?: '-' }}</td>
                                <td>{{ $product->category?->name ?? '-' }}</td>
                                <td>{{ $product->unit?->short_name ?? $product->unit?->name ?? '-' }}</td>
                                <td>{{ number_format((float) $product->purchase_price, 2) }}</td>
                                <td>{{ number_format((float) $product->selling_price, 2) }}</td>
                                <td>{{ number_format((float) $product->reorder_level, 2) }}</td>
                                <td>
                                    @if ($product->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        @can('permission', 'products.view')
                                            <a href="{{ route('products.show', $product) }}" class="btn btn-outline-secondary btn-sm">
                                                View
                                            </a>
                                        @endcan

                                        @can('permission', 'products.update')
                                            <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-primary btn-sm">
                                                Edit
                                            </a>
                                        @endcan

                                        @can('permission', 'products.delete')
                                            <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('Delete this product?');">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($products->hasPages())
            <div class="card-footer bg-white">
                {{ $products->links() }}
            </div>
        @endif
    </div>
@endsection
