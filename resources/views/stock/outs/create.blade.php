@extends('layouts.app')

@section('title', 'Create Stock Out - ' . config('app.name'))

@section('content')
    @php
        $oldItems = old('items');

        if (is_array($oldItems) && $oldItems !== []) {
            $items = $oldItems;
        } else {
            $items = [
                [
                    'product_id' => '',
                    'quantity' => '',
                    'remarks' => '',
                ],
            ];
        }
    @endphp

    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Create Stock Out</h1>
            <p class="text-muted mb-0">Issue stock out of a warehouse.</p>
        </div>

        <a href="{{ route('stock-outs.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if ($warehouses->isEmpty())
        <div class="alert alert-warning" role="alert">
            Please create an active warehouse before creating a stock out document.
        </div>
    @endif

    @if ($products->isEmpty())
        <div class="alert alert-warning" role="alert">
            Please create an active product before creating a stock out document.
        </div>
    @endif

    <form method="POST" action="{{ route('stock-outs.store') }}" class="card shadow-sm border-0">
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
                    <label for="stock_date" class="form-label">Stock Date <span class="text-danger">*</span></label>
                    <input
                        type="date"
                        name="stock_date"
                        id="stock_date"
                        value="{{ old('stock_date', now()->toDateString()) }}"
                        class="form-control @error('stock_date') is-invalid @enderror"
                        required
                    >
                    @error('stock_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <label for="remarks" class="form-label">Remarks</label>
                <textarea
                    name="remarks"
                    id="remarks"
                    rows="3"
                    class="form-control @error('remarks') is-invalid @enderror"
                >{{ old('remarks') }}</textarea>
                @error('remarks')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Items</h2>
                    <p class="small text-muted mb-0">Add each product once with a positive issued quantity.</p>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="add-stock-out-item">Add Item</button>
            </div>

            @error('items')
                <div class="alert alert-danger" role="alert">{{ $message }}</div>
            @enderror

            <div id="stock-out-items">
                @foreach ($items as $index => $item)
                    <div class="stock-out-item border rounded p-3 mb-3" data-item-row>
                        <div class="row g-3 align-items-start">
                            <div class="col-lg-5">
                                <label for="items_{{ $index }}_product_id" class="form-label">Product <span class="text-danger">*</span></label>
                                <select
                                    name="items[{{ $index }}][product_id]"
                                    id="items_{{ $index }}_product_id"
                                    class="form-select @error('items.'.$index.'.product_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">Select Product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected((string) ($item['product_id'] ?? '') === (string) $product->id)>
                                            {{ $product->name }} ({{ $product->sku }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('items.'.$index.'.product_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-3">
                                <label for="items_{{ $index }}_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input
                                    type="number"
                                    name="items[{{ $index }}][quantity]"
                                    id="items_{{ $index }}_quantity"
                                    value="{{ $item['quantity'] ?? '' }}"
                                    class="form-control @error('items.'.$index.'.quantity') is-invalid @enderror"
                                    step="0.0001"
                                    min="0.0001"
                                    required
                                >
                                @error('items.'.$index.'.quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-3">
                                <label for="items_{{ $index }}_remarks" class="form-label">Item Remarks</label>
                                <input
                                    type="text"
                                    name="items[{{ $index }}][remarks]"
                                    id="items_{{ $index }}_remarks"
                                    value="{{ $item['remarks'] ?? '' }}"
                                    class="form-control @error('items.'.$index.'.remarks') is-invalid @enderror"
                                >
                                @error('items.'.$index.'.remarks')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-1 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger w-100 js-remove-item">Remove</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('stock-outs.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Stock Out</button>
        </div>
    </form>

    <template id="stock-out-item-template">
        <div class="stock-out-item border rounded p-3 mb-3" data-item-row>
            <div class="row g-3 align-items-start">
                <div class="col-lg-5">
                    <label class="form-label" for="items___INDEX___product_id">Product <span class="text-danger">*</span></label>
                    <select name="items[__INDEX__][product_id]" id="items___INDEX___product_id" class="form-select" required>
                        <option value="">Select Product</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-3">
                    <label class="form-label" for="items___INDEX___quantity">Quantity <span class="text-danger">*</span></label>
                    <input type="number" name="items[__INDEX__][quantity]" id="items___INDEX___quantity" class="form-control" step="0.0001" min="0.0001" required>
                </div>

                <div class="col-lg-3">
                    <label class="form-label" for="items___INDEX___remarks">Item Remarks</label>
                    <input type="text" name="items[__INDEX__][remarks]" id="items___INDEX___remarks" class="form-control">
                </div>

                <div class="col-lg-1 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger w-100 js-remove-item">Remove</button>
                </div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const itemsContainer = document.getElementById('stock-out-items');
            const itemTemplate = document.getElementById('stock-out-item-template');
            const addItemButton = document.getElementById('add-stock-out-item');
            let nextItemIndex = itemsContainer.querySelectorAll('[data-item-row]').length;

            addItemButton.addEventListener('click', function () {
                const html = itemTemplate.innerHTML.replaceAll('__INDEX__', String(nextItemIndex++));

                itemsContainer.insertAdjacentHTML('beforeend', html);
            });

            itemsContainer.addEventListener('click', function (event) {
                if (! event.target.classList.contains('js-remove-item')) {
                    return;
                }

                if (itemsContainer.querySelectorAll('[data-item-row]').length === 1) {
                    alert('At least one stock out item is required.');
                    return;
                }

                event.target.closest('[data-item-row]').remove();
            });
        });
    </script>
@endsection
