@extends('layouts.app')

@section('title', 'Audit Logs - ' . config('app.name'))

@section('content')
    @php
        $auditLogs = $auditLogs ?? collect();
        $users = $users ?? collect();
        $modules = $modules ?? collect();
        $events = $events ?? collect();
        $filters = $filters ?? [];
    @endphp

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Audit Logs</h1>
            <p class="text-muted mb-0">Review read-only system activity and change history.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('audit-logs.index') }}" class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="user_id" class="form-label">User</label>
                    <select name="user_id" id="user_id" class="form-select @error('user_id') is-invalid @enderror">
                        <option value="">All Users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) data_get($filters, 'user_id', '') === (string) $user->id)>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label for="module" class="form-label">Module</label>
                    <select name="module" id="module" class="form-select @error('module') is-invalid @enderror">
                        <option value="">All Modules</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}" @selected(data_get($filters, 'module') === $module)>
                                {{ ucfirst(str_replace('_', ' ', $module)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('module')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label for="event" class="form-label">Event</label>
                    <select name="event" id="event" class="form-select @error('event') is-invalid @enderror">
                        <option value="">All Events</option>
                        @foreach ($events as $event)
                            <option value="{{ $event }}" @selected(data_get($filters, 'event') === $event)>
                                {{ ucfirst(str_replace('_', ' ', $event)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('event')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input
                        type="date"
                        name="date_from"
                        id="date_from"
                        value="{{ data_get($filters, 'date_from') }}"
                        class="form-control @error('date_from') is-invalid @enderror"
                    >
                    @error('date_from')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input
                        type="date"
                        name="date_to"
                        id="date_to"
                        value="{{ data_get($filters, 'date_to') }}"
                        class="form-control @error('date_to') is-invalid @enderror"
                    >
                    @error('date_to')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <a href="{{ route('audit-logs.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Date/Time</th>
                            <th scope="col">User</th>
                            <th scope="col">Event</th>
                            <th scope="col">Module</th>
                            <th scope="col">Description</th>
                            <th scope="col">IP Address</th>
                            <th scope="col" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($auditLogs as $auditLog)
                            <tr>
                                <td>{{ $auditLog->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td>
                                    @if ($auditLog->user)
                                        <div class="fw-semibold">{{ $auditLog->user->name }}</div>
                                        <div class="small text-muted">{{ $auditLog->user->email }}</div>
                                    @else
                                        <span class="text-muted">System</span>
                                    @endif
                                </td>
                                <td>{{ ucfirst(str_replace('_', ' ', $auditLog->event)) }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $auditLog->module)) }}</td>
                                <td>{{ $auditLog->description ?: '-' }}</td>
                                <td>{{ $auditLog->ip_address ?: '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('audit-logs.show', $auditLog) }}" class="btn btn-outline-secondary btn-sm">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No audit logs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if (method_exists($auditLogs, 'hasPages') && $auditLogs->hasPages())
            <div class="card-footer bg-white">
                {{ $auditLogs->links() }}
            </div>
        @endif
    </div>
@endsection
