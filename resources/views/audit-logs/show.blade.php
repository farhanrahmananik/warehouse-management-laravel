@extends('layouts.app')

@section('title', 'Audit Log Details - ' . config('app.name'))

@section('content')
    @php
        $prettyJson = function ($value): ?string {
            if ($value === null || $value === []) {
                return null;
            }

            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        };

        $oldValuesJson = $prettyJson($auditLog->old_values);
        $newValuesJson = $prettyJson($auditLog->new_values);
        $metadataJson = $prettyJson($auditLog->metadata);
    @endphp

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Audit Log Details</h1>
            <p class="text-muted mb-0">Review a read-only audit log entry.</p>
        </div>

        <a href="{{ route('audit-logs.index') }}" class="btn btn-outline-secondary">Back to Audit Logs</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Activity</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Event</dt>
                        <dd class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $auditLog->event)) }}</dd>

                        <dt class="col-sm-4">Module</dt>
                        <dd class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $auditLog->module)) }}</dd>

                        <dt class="col-sm-4">User</dt>
                        <dd class="col-sm-8">
                            @if ($auditLog->user)
                                {{ $auditLog->user->name }}
                                <span class="text-muted">({{ $auditLog->user->email }})</span>
                            @else
                                System
                            @endif
                        </dd>

                        <dt class="col-sm-4">Auditable Type</dt>
                        <dd class="col-sm-8">{{ $auditLog->auditable_type ?: '-' }}</dd>

                        <dt class="col-sm-4">Auditable ID</dt>
                        <dd class="col-sm-8">{{ $auditLog->auditable_id ?: '-' }}</dd>

                        <dt class="col-sm-4">Description</dt>
                        <dd class="col-sm-8">{{ $auditLog->description ?: '-' }}</dd>

                        <dt class="col-sm-4">Created At</dt>
                        <dd class="col-sm-8">{{ $auditLog->created_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Request Context</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">IP Address</dt>
                        <dd class="col-sm-8">{{ $auditLog->ip_address ?: '-' }}</dd>

                        <dt class="col-sm-4">User Agent</dt>
                        <dd class="col-sm-8 text-break">{{ $auditLog->user_agent ?: '-' }}</dd>

                        <dt class="col-sm-4">URL</dt>
                        <dd class="col-sm-8 text-break">{{ $auditLog->url ?: '-' }}</dd>

                        <dt class="col-sm-4">Method</dt>
                        <dd class="col-sm-8">{{ $auditLog->method ?: '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Old Values</h2>
                </div>
                <div class="card-body">
                    @if ($oldValuesJson)
                        <pre class="bg-light border rounded p-3 mb-0 small overflow-auto"><code>{{ $oldValuesJson }}</code></pre>
                    @else
                        <p class="text-muted mb-0">No old values recorded.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">New Values</h2>
                </div>
                <div class="card-body">
                    @if ($newValuesJson)
                        <pre class="bg-light border rounded p-3 mb-0 small overflow-auto"><code>{{ $newValuesJson }}</code></pre>
                    @else
                        <p class="text-muted mb-0">No new values recorded.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Metadata</h2>
                </div>
                <div class="card-body">
                    @if ($metadataJson)
                        <pre class="bg-light border rounded p-3 mb-0 small overflow-auto"><code>{{ $metadataJson }}</code></pre>
                    @else
                        <p class="text-muted mb-0">No metadata recorded.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
