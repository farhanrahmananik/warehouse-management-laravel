@extends('layouts.app')

@section('title', 'Create Purchase Order - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Create Purchase Order</h1>
            <p class="text-muted mb-0">Create a draft supplier purchase order.</p>
        </div>

        <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form method="POST" action="{{ route('purchase-orders.store') }}" class="card shadow-sm border-0">
        @csrf

        <div class="card-body">
            @include('purchase-orders._form')
        </div>

        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Purchase Order</button>
        </div>
    </form>
@endsection
