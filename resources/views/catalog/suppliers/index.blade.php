@extends('layouts.app')

@section('title', 'Suppliers - ' . config('app.name'))

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Suppliers</h1>
            <p class="text-muted mb-0">Manage supplier records.</p>
        </div>

        @can('permission', 'suppliers.create')
            <a href="{{ route('suppliers.create') }}" class="btn btn-primary">Add Supplier</a>
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
                            <th scope="col">Company</th>
                            <th scope="col">Email</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Balance</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suppliers as $supplier)
                            <tr>
                                <td>{{ $suppliers->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $supplier->name }}</td>
                                <td>{{ $supplier->company_name ?: 'N/A' }}</td>
                                <td>{{ $supplier->email ?: 'N/A' }}</td>
                                <td>{{ $supplier->phone ?: 'N/A' }}</td>
                                <td>{{ number_format((float) $supplier->current_balance, 2) }}</td>
                                <td>
                                    @if ($supplier->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        @can('permission', 'suppliers.view')
                                            <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-outline-secondary btn-sm">
                                                View
                                            </a>
                                        @endcan

                                        @can('permission', 'suppliers.update')
                                            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-primary btn-sm">
                                                Edit
                                            </a>
                                        @endcan

                                        @can('permission', 'suppliers.delete')
                                            <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" onsubmit="return confirm('Delete this supplier?');">
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
                                <td colspan="8" class="text-center text-muted py-4">No suppliers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($suppliers->hasPages())
            <div class="card-footer bg-white">
                {{ $suppliers->links() }}
            </div>
        @endif
    </div>
@endsection
