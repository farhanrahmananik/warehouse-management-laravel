@extends('layouts.app')

@section('title', 'Stock Adjustment - ' . config('app.name'))

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Stock Adjustment</h1>
            <p class="text-muted mb-0">Record opening stock or controlled stock corrections.</p>
        </div>

        <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary">Back to Stock Overview</a>
    </div>

    <form method="POST" action="{{ route('stock-adjustments.store') }}" class="card shadow-sm border-0">
        @csrf

        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="warehouse_id" class="form-label">Warehouse <span class="text-danger">*</span></label>
                    <select
                        name="warehouse_id"
                        id="warehouse_id"
                        class="form-select @error('warehouse_id') is-invalid @enderror"
                        required
                    >
                        <option value="">Select Warehouse</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>
                                {{ $warehouse->name }} ({{ $warehouse->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('warehouse_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                    <select
                        name="product_id"
                        id="product_id"
                        class="form-select @error('product_id') is-invalid @enderror"
                        required
                    >
                        <option value="">Select Product</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                                {{ $product->name }} ({{ $product->sku }})
                            </option>
                        @endforeach
                    </select>
                    @error('product_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="movement_type" class="form-label">Movement Type <span class="text-danger">*</span></label>
                    <select
                        name="movement_type"
                        id="movement_type"
                        class="form-select @error('movement_type') is-invalid @enderror"
                        required
                    >
                        <option value="">Select Movement Type</option>
                        <option value="opening_balance" @selected(old('movement_type') === 'opening_balance')>Opening Balance</option>
                        <option value="adjustment_in" @selected(old('movement_type') === 'adjustment_in')>Adjustment In</option>
                        <option value="adjustment_out" @selected(old('movement_type') === 'adjustment_out')>Adjustment Out</option>
                    </select>
                    @error('movement_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                    <input
                        type="number"
                        name="quantity"
                        id="quantity"
                        value="{{ old('quantity') }}"
                        class="form-control @error('quantity') is-invalid @enderror"
                        step="0.0001"
                        min="0.0001"
                        required
                    >
                    @error('quantity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="remarks" class="form-label">Remarks</label>
                <textarea
                    name="remarks"
                    id="remarks"
                    rows="4"
                    class="form-control @error('remarks') is-invalid @enderror"
                >{{ old('remarks') }}</textarea>
                @error('remarks')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Stock Adjustment</button>
        </div>
    </form>
@endsection
