@php
    $purchaseOrder = $purchaseOrder ?? null;
    $selectedSupplierId = old('supplier_id', $purchaseOrder?->supplier_id);
    $selectedWarehouseId = old('warehouse_id', $purchaseOrder?->warehouse_id);
    $oldItems = old('items');

    if (is_array($oldItems)) {
        $items = $oldItems;
    } elseif ($purchaseOrder) {
        $items = $purchaseOrder->items->map(fn ($item): array => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'unit_cost' => $item->unit_cost,
            'notes' => $item->notes,
        ])->all();
    } else {
        $items = [];
    }

    if ($items === []) {
        $items[] = [
            'product_id' => '',
            'quantity' => '',
            'unit_cost' => '',
            'notes' => '',
        ];
    }
@endphp

@if ($suppliers->isEmpty())
    <div class="alert alert-warning" role="alert">
        Please create an active supplier before creating a purchase order.
    </div>
@endif

@if ($warehouses->isEmpty())
    <div class="alert alert-warning" role="alert">
        Please create an active warehouse before creating a purchase order.
    </div>
@endif

@if ($products->isEmpty())
    <div class="alert alert-warning" role="alert">
        Please create an active product before creating a purchase order.
    </div>
@endif

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
        <select
            name="supplier_id"
            id="supplier_id"
            class="form-select @error('supplier_id') is-invalid @enderror"
            required
        >
            <option value="">Select supplier</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected((string) $selectedSupplierId === (string) $supplier->id)>
                    {{ $supplier->name }}
                </option>
            @endforeach
        </select>
        @error('supplier_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="warehouse_id" class="form-label">Warehouse <span class="text-danger">*</span></label>
        <select
            name="warehouse_id"
            id="warehouse_id"
            class="form-select @error('warehouse_id') is-invalid @enderror"
            required
        >
            <option value="">Select warehouse</option>
            @foreach ($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected((string) $selectedWarehouseId === (string) $warehouse->id)>
                    {{ $warehouse->name }} ({{ $warehouse->code }})
                </option>
            @endforeach
        </select>
        @error('warehouse_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="order_date" class="form-label">Order Date <span class="text-danger">*</span></label>
        <input
            type="date"
            name="order_date"
            id="order_date"
            value="{{ old('order_date', $purchaseOrder?->order_date?->format('Y-m-d')) }}"
            class="form-control @error('order_date') is-invalid @enderror"
            required
        >
        @error('order_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="expected_date" class="form-label">Expected Date</label>
        <input
            type="date"
            name="expected_date"
            id="expected_date"
            value="{{ old('expected_date', $purchaseOrder?->expected_date?->format('Y-m-d')) }}"
            class="form-control @error('expected_date') is-invalid @enderror"
        >
        @error('expected_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="discount_amount" class="form-label">Discount Amount</label>
        <input
            type="number"
            name="discount_amount"
            id="discount_amount"
            value="{{ old('discount_amount', $purchaseOrder?->discount_amount ?? 0) }}"
            class="form-control js-order-adjustment @error('discount_amount') is-invalid @enderror"
            step="0.01"
            min="0"
        >
        @error('discount_amount')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="tax_amount" class="form-label">Tax Amount</label>
        <input
            type="number"
            name="tax_amount"
            id="tax_amount"
            value="{{ old('tax_amount', $purchaseOrder?->tax_amount ?? 0) }}"
            class="form-control js-order-adjustment @error('tax_amount') is-invalid @enderror"
            step="0.01"
            min="0"
        >
        @error('tax_amount')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="shipping_amount" class="form-label">Shipping Amount</label>
        <input
            type="number"
            name="shipping_amount"
            id="shipping_amount"
            value="{{ old('shipping_amount', $purchaseOrder?->shipping_amount ?? 0) }}"
            class="form-control js-order-adjustment @error('shipping_amount') is-invalid @enderror"
            step="0.01"
            min="0"
        >
        @error('shipping_amount')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-4">
    <label for="notes" class="form-label">Notes</label>
    <textarea
        name="notes"
        id="notes"
        rows="4"
        class="form-control @error('notes') is-invalid @enderror"
    >{{ old('notes', $purchaseOrder?->notes) }}</textarea>
    @error('notes')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
    <div>
        <h2 class="h5 mb-1">Items</h2>
        <p class="small text-muted mb-0">Displayed totals are estimates. The server calculates authoritative totals.</p>
    </div>
    <button type="button" class="btn btn-outline-primary btn-sm" id="add-purchase-order-item">Add Item</button>
</div>

@error('items')
    <div class="alert alert-danger" role="alert">{{ $message }}</div>
@enderror

<div id="purchase-order-items">
    @foreach ($items as $index => $item)
        <div class="purchase-order-item border rounded p-3 mb-3" data-item-row>
            <div class="row g-3 align-items-start">
                <div class="col-lg-4">
                    <label for="items_{{ $index }}_product_id" class="form-label">Product <span class="text-danger">*</span></label>
                    <select
                        name="items[{{ $index }}][product_id]"
                        id="items_{{ $index }}_product_id"
                        class="form-select js-product-select @error('items.'.$index.'.product_id') is-invalid @enderror"
                        required
                    >
                        <option value="">Select product</option>
                        @foreach ($products as $product)
                            <option
                                value="{{ $product->id }}"
                                data-cost="{{ $product->purchase_price }}"
                                @selected((string) ($item['product_id'] ?? '') === (string) $product->id)
                            >
                                {{ $product->name }} ({{ $product->sku }})
                            </option>
                        @endforeach
                    </select>
                    @error('items.'.$index.'.product_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-lg-2">
                    <label for="items_{{ $index }}_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                    <input
                        type="number"
                        name="items[{{ $index }}][quantity]"
                        id="items_{{ $index }}_quantity"
                        value="{{ $item['quantity'] ?? '' }}"
                        class="form-control js-item-quantity @error('items.'.$index.'.quantity') is-invalid @enderror"
                        step="0.001"
                        min="0.001"
                        required
                    >
                    @error('items.'.$index.'.quantity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-lg-2">
                    <label for="items_{{ $index }}_unit_cost" class="form-label">Unit Cost <span class="text-danger">*</span></label>
                    <input
                        type="number"
                        name="items[{{ $index }}][unit_cost]"
                        id="items_{{ $index }}_unit_cost"
                        value="{{ $item['unit_cost'] ?? '' }}"
                        class="form-control js-item-unit-cost @error('items.'.$index.'.unit_cost') is-invalid @enderror"
                        step="0.01"
                        min="0"
                        required
                    >
                    @error('items.'.$index.'.unit_cost')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-lg-2">
                    <label class="form-label">Line Total</label>
                    <div class="form-control bg-light js-line-total">0.00</div>
                </div>

                <div class="col-lg-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger w-100 js-remove-item">Remove</button>
                </div>

                <div class="col-12">
                    <label for="items_{{ $index }}_notes" class="form-label">Item Notes</label>
                    <textarea
                        name="items[{{ $index }}][notes]"
                        id="items_{{ $index }}_notes"
                        rows="2"
                        class="form-control @error('items.'.$index.'.notes') is-invalid @enderror"
                    >{{ $item['notes'] ?? '' }}</textarea>
                    @error('items.'.$index.'.notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card bg-light border-0">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-3">
                <div class="small text-muted">Estimated Subtotal</div>
                <div class="fw-semibold" id="estimated-subtotal">0.00</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Estimated Discount</div>
                <div class="fw-semibold" id="estimated-discount">0.00</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Estimated Tax + Shipping</div>
                <div class="fw-semibold" id="estimated-tax-shipping">0.00</div>
            </div>
            <div class="col-md-3">
                <div class="small text-muted">Estimated Total</div>
                <div class="fw-semibold" id="estimated-total">0.00</div>
            </div>
        </div>
    </div>
</div>

<template id="purchase-order-item-template">
    <div class="purchase-order-item border rounded p-3 mb-3" data-item-row>
        <div class="row g-3 align-items-start">
            <div class="col-lg-4">
                <label class="form-label" for="items___INDEX___product_id">Product <span class="text-danger">*</span></label>
                <select name="items[__INDEX__][product_id]" id="items___INDEX___product_id" class="form-select js-product-select" required>
                    <option value="">Select product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" data-cost="{{ $product->purchase_price }}">
                            {{ $product->name }} ({{ $product->sku }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="items___INDEX___quantity">Quantity <span class="text-danger">*</span></label>
                <input type="number" name="items[__INDEX__][quantity]" id="items___INDEX___quantity" class="form-control js-item-quantity" step="0.001" min="0.001" required>
            </div>
            <div class="col-lg-2">
                <label class="form-label" for="items___INDEX___unit_cost">Unit Cost <span class="text-danger">*</span></label>
                <input type="number" name="items[__INDEX__][unit_cost]" id="items___INDEX___unit_cost" class="form-control js-item-unit-cost" step="0.01" min="0" required>
            </div>
            <div class="col-lg-2">
                <label class="form-label">Line Total</label>
                <div class="form-control bg-light js-line-total">0.00</div>
            </div>
            <div class="col-lg-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger w-100 js-remove-item">Remove</button>
            </div>
            <div class="col-12">
                <label class="form-label" for="items___INDEX___notes">Item Notes</label>
                <textarea name="items[__INDEX__][notes]" id="items___INDEX___notes" rows="2" class="form-control"></textarea>
            </div>
        </div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const itemsContainer = document.getElementById('purchase-order-items');
        const itemTemplate = document.getElementById('purchase-order-item-template');
        const addItemButton = document.getElementById('add-purchase-order-item');
        let nextItemIndex = itemsContainer.querySelectorAll('[data-item-row]').length;

        const money = new Intl.NumberFormat(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const parseNumber = function (value) {
            const number = parseFloat(value);

            return Number.isFinite(number) ? number : 0;
        };

        const calculateTotals = function () {
            let subtotal = 0;

            itemsContainer.querySelectorAll('[data-item-row]').forEach(function (row) {
                const quantity = parseNumber(row.querySelector('.js-item-quantity')?.value);
                const unitCost = parseNumber(row.querySelector('.js-item-unit-cost')?.value);
                const lineTotal = quantity * unitCost;
                const lineTotalElement = row.querySelector('.js-line-total');

                if (lineTotalElement) {
                    lineTotalElement.textContent = money.format(lineTotal);
                }

                subtotal += lineTotal;
            });

            const discount = parseNumber(document.getElementById('discount_amount')?.value);
            const tax = parseNumber(document.getElementById('tax_amount')?.value);
            const shipping = parseNumber(document.getElementById('shipping_amount')?.value);
            const total = subtotal - discount + tax + shipping;

            document.getElementById('estimated-subtotal').textContent = money.format(subtotal);
            document.getElementById('estimated-discount').textContent = money.format(discount);
            document.getElementById('estimated-tax-shipping').textContent = money.format(tax + shipping);
            document.getElementById('estimated-total').textContent = money.format(total);
        };

        addItemButton.addEventListener('click', function () {
            const html = itemTemplate.innerHTML.replaceAll('__INDEX__', String(nextItemIndex++));

            itemsContainer.insertAdjacentHTML('beforeend', html);
            calculateTotals();
        });

        itemsContainer.addEventListener('click', function (event) {
            if (! event.target.classList.contains('js-remove-item')) {
                return;
            }

            if (itemsContainer.querySelectorAll('[data-item-row]').length === 1) {
                alert('At least one purchase order item is required.');
                return;
            }

            event.target.closest('[data-item-row]').remove();
            calculateTotals();
        });

        itemsContainer.addEventListener('change', function (event) {
            if (! event.target.classList.contains('js-product-select')) {
                return;
            }

            const selectedOption = event.target.options[event.target.selectedIndex];
            const row = event.target.closest('[data-item-row]');
            const unitCostInput = row.querySelector('.js-item-unit-cost');

            if (unitCostInput && unitCostInput.value === '' && selectedOption?.dataset.cost) {
                unitCostInput.value = selectedOption.dataset.cost;
            }

            calculateTotals();
        });

        document.addEventListener('input', function (event) {
            if (
                event.target.classList.contains('js-item-quantity') ||
                event.target.classList.contains('js-item-unit-cost') ||
                event.target.classList.contains('js-order-adjustment')
            ) {
                calculateTotals();
            }
        });

        calculateTotals();
    });
</script>
