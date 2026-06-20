@extends('layouts.app')

@section('title', 'Warehouses - ' . config('app.name'))

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Warehouses</h1>
            <p class="text-muted mb-0">Manage warehouse locations.</p>
        </div>

        @can('permission', 'warehouses.create')
            <a href="{{ route('warehouses.create') }}" class="btn btn-primary">Add Warehouse</a>
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
                            <th scope="col">Code</th>
                            <th scope="col">Name</th>
                            <th scope="col">City</th>
                            <th scope="col">Contact Person</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($warehouses as $warehouse)
                            <tr>
                                <td>{{ $warehouses->firstItem() + $loop->index }}</td>
                                <td><code>{{ $warehouse->code }}</code></td>
                                <td class="fw-semibold">{{ $warehouse->name }}</td>
                                <td>{{ $warehouse->city ?: '-' }}</td>
                                <td>{{ $warehouse->contact_person ?: '-' }}</td>
                                <td>
                                    @if ($warehouse->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        @can('permission', 'warehouses.view')
                                            <a href="{{ route('warehouses.show', $warehouse) }}" class="btn btn-outline-secondary btn-sm">
                                                View
                                            </a>
                                        @endcan

                                        @can('permission', 'warehouses.update')
                                            <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-outline-primary btn-sm">
                                                Edit
                                            </a>
                                        @endcan

                                        @can('permission', 'warehouses.delete')
                                            <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" onsubmit="return confirm('Delete this warehouse?');">
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
                                <td colspan="7" class="text-center text-muted py-4">No warehouses found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($warehouses->hasPages())
            <div class="card-footer bg-white">
                {{ $warehouses->links() }}
            </div>
        @endif
    </div>
@endsection
