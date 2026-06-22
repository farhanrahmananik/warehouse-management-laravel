@extends('layouts.app')

@section('title', 'Create Stock Transfer - ' . config('app.name'))

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
            <h1 class="h3 mb-1">Create Stock Transfer</h1>
            <p class="text-muted mb-0">Move stock from one warehouse to another.</p>
        </div>

        <a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if ($warehouses->count() < 2)
        <div class="alert alert-warning" role="alert">
            Please create at least two active warehouses before creating a stock transfer document.
        </div>
    @endif

    @if ($products->isEmpty())
        <div class="alert alert-warning" role="alert">
            Please create an active product before creating a stock transfer document.
        </div>
    @endif

    <form method="POST" action="{{ route('stock-transfers.store') }}" class="card shadow-sm border-0" id="stock-transfer-form">
        @csrf

        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="from_warehouse_id" class="form-label">From Warehouse <span class="text-danger">*</span></label>
                    <select
                        name="from_warehouse_id"
                        id="from_warehouse_id"
                        class="form-select @error('from_warehouse_id') is-invalid @enderror"
                        required
                    >
                        <option value="">Select Source Warehouse</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('from_warehouse_id') === (string) $warehouse->id)>
                                {{ $warehouse->name }} ({{ $warehouse->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('from_warehouse_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label for="to_warehouse_id" class="form-label">To Warehouse <span class="text-danger">*</span></label>
                    <select
                        name="to_warehouse_id"
                        id="to_warehouse_id"
                        class="form-select @error('to_warehouse_id') is-invalid @enderror"
                        required
                    >
                        <option value="">Select Destination Warehouse</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('to_warehouse_id') === (string) $warehouse->id)>
                                {{ $warehouse->name }} ({{ $warehouse->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('to_warehouse_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label for="transfer_date" class="form-label">Transfer Date <span class="text-danger">*</span></label>
                    <input
                        type="date"
                        name="transfer_date"
                        id="transfer_date"
                        value="{{ old('transfer_date', now()->toDateString()) }}"
                        class="form-control @error('transfer_date') is-invalid @enderror"
                        required
                    >
                    @error('transfer_date')
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
                    <p class="small text-muted mb-0">Add each product once with a positive transfer quantity.</p>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="add-stock-transfer-item">Add Item</button>
            </div>

            @error('items')
                <div class="alert alert-danger" role="alert">{{ $message }}</div>
            @enderror

            <div id="stock-transfer-items">
                @foreach ($items as $index => $item)
                    <div class="stock-transfer-item border rounded p-3 mb-3" data-item-row>
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
            <a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Stock Transfer</button>
        </div>
    </form>

    <template id="stock-transfer-item-template">
        <div class="stock-transfer-item border rounded p-3 mb-3" data-item-row>
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
            const form = document.getElementById('stock-transfer-form');
            const fromWarehouse = document.getElementById('from_warehouse_id');
            const toWarehouse = document.getElementById('to_warehouse_id');
            const itemsContainer = document.getElementById('stock-transfer-items');
            const itemTemplate = document.getElementById('stock-transfer-item-template');
            const addItemButton = document.getElementById('add-stock-transfer-item');
            let nextItemIndex = itemsContainer.querySelectorAll('[data-item-row]').length;

            form.addEventListener('submit', function (event) {
                if (fromWarehouse.value !== '' && fromWarehouse.value === toWarehouse.value) {
                    event.preventDefault();
                    alert('Source and destination warehouses must be different.');
                }
            });

            addItemButton.addEventListener('click', function () {
                const html = itemTemplate.innerHTML.replaceAll('__INDEX__', String(nextItemIndex++));

                itemsContainer.insertAdjacentHTML('beforeend', html);
            });

            itemsContainer.addEventListener('click', function (event) {
                if (! event.target.classList.contains('js-remove-item')) {
                    return;
                }

                if (itemsContainer.querySelectorAll('[data-item-row]').length === 1) {
                    alert('At least one stock transfer item is required.');
                    return;
                }

                event.target.closest('[data-item-row]').remove();
            });
        });
    </script>
@endsection
