@extends('layouts.app')

@section('title', 'Stock Movement Report - ' . config('app.name'))

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Stock Movement Report</h1>
            <p class="text-muted mb-0">Review stock movement reporting information.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h2 class="h5 mb-2">Read-Only Stock Movement Report</h2>
            <p class="text-muted mb-0">This placeholder page is reserved for stock movement report results. No report query, filter, or export action is implemented yet.</p>
        </div>
    </div>
@endsection
