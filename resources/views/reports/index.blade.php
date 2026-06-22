@extends('layouts.app')

@section('title', 'Reports - ' . config('app.name'))

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Reports</h1>
            <p class="text-muted mb-0">Access read-only warehouse management reports.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h2 class="h5 mb-2">Reports Foundation</h2>
            <p class="text-muted mb-0">This is a read-only reports landing page. Report filters and exports will be added in a later step.</p>
        </div>
    </div>
@endsection
