@extends('layouts.app')

@section('title', 'Edit Purchase Order - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Edit Purchase Order</h1>
            <p class="text-muted mb-0">Update draft purchase order details.</p>
        </div>

        <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form method="POST" action="{{ route('purchase-orders.update', $purchaseOrder) }}" class="card shadow-sm border-0">
        @csrf
        @method('PUT')

        <div class="card-body">
            @include('purchase-orders._form')
        </div>

        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Purchase Order</button>
        </div>
    </form>
@endsection
