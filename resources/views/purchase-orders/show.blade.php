@extends('layouts.app')

@section('title', $purchaseOrder->po_number . ' - ' . config('app.name'))

@section('content')
    @php
        $statusLabels = [
            'draft' => 'Draft',
            'approved' => 'Approved',
            'partially_received' => 'Partially Received',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
        ];
        $statusBadgeClasses = [
            'draft' => 'text-bg-secondary',
            'approved' => 'text-bg-primary',
            'partially_received' => 'text-bg-warning',
            'received' => 'text-bg-success',
            'cancelled' => 'text-bg-danger',
        ];
        $status = $purchaseOrder->status;
    @endphp

    <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $purchaseOrder->po_number }}</h1>
            <p class="text-muted mb-0">Review purchase order details.</p>
        </div>

        <div class="d-flex flex-wrap justify-content-lg-end gap-2">
            <a href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">Back to Purchase Orders</a>

            @if ($purchaseOrder->isDraft())
                @can('permission', 'purchase-orders.update')
                    <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="btn btn-outline-primary">Edit</a>
                @endcan

                @can('permission', 'purchase-orders.approve')
                    <form method="POST" action="{{ route('purchase-orders.approve', $purchaseOrder) }}" onsubmit="return confirm('Approve this purchase order?');">
                        @csrf
                        <button type="submit" class="btn btn-primary">Approve</button>
                    </form>
                @endcan
            @endif

            @if ($purchaseOrder->isDraft() || $purchaseOrder->isCancelled())
                @can('permission', 'purchase-orders.delete')
                    <form method="POST" action="{{ route('purchase-orders.destroy', $purchaseOrder) }}" onsubmit="return confirm('Delete this purchase order?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">Delete</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" role="alert">
            {{ session('error') }}
        </div>
    @endif

    @error('status')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Purchase Order Details</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">PO Number</dt>
                        <dd class="col-sm-8"><code>{{ $purchaseOrder->po_number }}</code></dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ $statusBadgeClasses[$status] ?? 'text-bg-secondary' }}">
                                {{ $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
                            </span>
                        </dd>

                        <dt class="col-sm-4">Supplier</dt>
                        <dd class="col-sm-8">{{ $purchaseOrder->supplier?->name ?? '-' }}</dd>

                        <dt class="col-sm-4">Warehouse</dt>
                        <dd class="col-sm-8">
                            {{ $purchaseOrder->warehouse?->name ?? '-' }}
                            @if ($purchaseOrder->warehouse?->code)
                                <span class="text-muted">({{ $purchaseOrder->warehouse->code }})</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Order Date</dt>
                        <dd class="col-sm-8">{{ $purchaseOrder->order_date?->format('M d, Y') ?? '-' }}</dd>

                        <dt class="col-sm-4">Expected Date</dt>
                        <dd class="col-sm-8">{{ $purchaseOrder->expected_date?->format('M d, Y') ?? '-' }}</dd>

                        <dt class="col-sm-4">Created By</dt>
                        <dd class="col-sm-8">{{ $purchaseOrder->createdBy?->name ?? 'System' }}</dd>

                        <dt class="col-sm-4">Approved By / At</dt>
                        <dd class="col-sm-8">
                            @if ($purchaseOrder->approved_at)
                                {{ $purchaseOrder->approvedBy?->name ?? 'System' }}
                                <span class="text-muted">at {{ $purchaseOrder->approved_at->format('M d, Y h:i A') }}</span>
                            @else
                                -
                            @endif
                        </dd>

                        <dt class="col-sm-4">Received By / At</dt>
                        <dd class="col-sm-8">
                            @if ($purchaseOrder->received_at)
                                {{ $purchaseOrder->receivedBy?->name ?? 'System' }}
                                <span class="text-muted">at {{ $purchaseOrder->received_at->format('M d, Y h:i A') }}</span>
                            @else
                                -
                            @endif
                        </dd>

                        <dt class="col-sm-4">Cancelled By / At</dt>
                        <dd class="col-sm-8">
                            @if ($purchaseOrder->cancelled_at)
                                {{ $purchaseOrder->cancelledBy?->name ?? 'System' }}
                                <span class="text-muted">at {{ $purchaseOrder->cancelled_at->format('M d, Y h:i A') }}</span>
                            @else
                                -
                            @endif
                        </dd>

                        <dt class="col-sm-4">Notes</dt>
                        <dd class="col-sm-8">{{ $purchaseOrder->notes ?: 'No notes provided.' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Items</h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col" class="text-end">Ordered Quantity</th>
                                    <th scope="col" class="text-end">Received Quantity</th>
                                    <th scope="col" class="text-end">Unit Cost</th>
                                    <th scope="col" class="text-end">Line Total</th>
                                    <th scope="col">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($purchaseOrder->items as $item)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item->product?->name ?? '-' }}</div>
                                            <div class="small text-muted">{{ $item->product?->sku ?? '' }}</div>
                                        </td>
                                        <td class="text-end">{{ number_format((float) $item->quantity, 3) }}</td>
                                        <td class="text-end">{{ number_format((float) $item->received_quantity, 3) }}</td>
                                        <td class="text-end">{{ number_format((float) $item->unit_cost, 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $item->line_total, 2) }}</td>
                                        <td>{{ $item->notes ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No items found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Totals</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-7">Subtotal</dt>
                        <dd class="col-5 text-end">{{ number_format((float) $purchaseOrder->subtotal, 2) }}</dd>

                        <dt class="col-7">Discount</dt>
                        <dd class="col-5 text-end">{{ number_format((float) $purchaseOrder->discount_amount, 2) }}</dd>

                        <dt class="col-7">Tax</dt>
                        <dd class="col-5 text-end">{{ number_format((float) $purchaseOrder->tax_amount, 2) }}</dd>

                        <dt class="col-7">Shipping</dt>
                        <dd class="col-5 text-end">{{ number_format((float) $purchaseOrder->shipping_amount, 2) }}</dd>

                        <dt class="col-7 border-top pt-2">Total</dt>
                        <dd class="col-5 text-end border-top pt-2 fw-semibold">{{ number_format((float) $purchaseOrder->total_amount, 2) }}</dd>
                    </dl>
                </div>
            </div>

            @if ($purchaseOrder->isApproved() || $purchaseOrder->isPartiallyReceived())
                @can('permission', 'purchase-orders.receive')
                    <form method="POST" action="{{ route('purchase-orders.receive', $purchaseOrder) }}" class="card shadow-sm border-0 mb-4" onsubmit="return confirm('Receive these purchase order quantities into stock?');">
                        @csrf
                        <div class="card-header bg-white">
                            <h2 class="h5 mb-0">Receive Stock</h2>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">
                                Enter the quantities received for this delivery. At least one item must have a received quantity greater than zero.
                            </p>

                            @error('items')
                                <div class="alert alert-danger" role="alert">{{ $message }}</div>
                            @enderror

                            <div class="vstack gap-3">
                                @foreach ($purchaseOrder->items as $item)
                                    @php
                                        $itemIndex = $loop->index;
                                        $remainingQuantity = max(0, round((float) $item->quantity - (float) $item->received_quantity, 3));
                                    @endphp

                                    <div class="border rounded p-3">
                                        <input type="hidden" name="items[{{ $itemIndex }}][purchase_order_item_id]" value="{{ $item->id }}">

                                        <div class="fw-semibold">{{ $item->product?->name ?? '-' }}</div>
                                        <div class="small text-muted mb-2">{{ $item->product?->sku ?? 'No SKU' }}</div>

                                        <dl class="row small mb-2">
                                            <dt class="col-7">Ordered</dt>
                                            <dd class="col-5 text-end mb-1">{{ number_format((float) $item->quantity, 3) }}</dd>

                                            <dt class="col-7">Received</dt>
                                            <dd class="col-5 text-end mb-1">{{ number_format((float) $item->received_quantity, 3) }}</dd>

                                            <dt class="col-7">Remaining</dt>
                                            <dd class="col-5 text-end mb-0">{{ number_format($remainingQuantity, 3) }}</dd>
                                        </dl>

                                        <label for="items_{{ $itemIndex }}_received_quantity" class="form-label small">Receive Quantity</label>
                                        <input
                                            type="number"
                                            name="items[{{ $itemIndex }}][received_quantity]"
                                            id="items_{{ $itemIndex }}_received_quantity"
                                            class="form-control @error('items.'.$itemIndex.'.received_quantity') is-invalid @enderror"
                                            value="{{ old('items.'.$itemIndex.'.received_quantity') }}"
                                            min="0"
                                            max="{{ number_format($remainingQuantity, 3, '.', '') }}"
                                            step="0.001"
                                        >
                                        @error('items.'.$itemIndex.'.received_quantity')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        @error('items.'.$itemIndex.'.purchase_order_item_id')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3">
                                <label for="receive_notes" class="form-label">Receiving Notes</label>
                                <textarea
                                    name="notes"
                                    id="receive_notes"
                                    rows="3"
                                    class="form-control @error('notes') is-invalid @enderror"
                                >{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-end">
                            <button type="submit" class="btn btn-success">Receive Stock</button>
                        </div>
                    </form>
                @endcan
            @endif

            @if ($purchaseOrder->isDraft() || $purchaseOrder->isApproved())
                @can('permission', 'purchase-orders.delete')
                    <form method="POST" action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" class="card shadow-sm border-0" onsubmit="return confirm('Cancel this purchase order?');">
                        @csrf
                        <div class="card-header bg-white">
                            <h2 class="h5 mb-0">Cancel Purchase Order</h2>
                        </div>
                        <div class="card-body">
                            <label for="cancel_notes" class="form-label">Cancellation Notes</label>
                            <textarea
                                name="notes"
                                id="cancel_notes"
                                rows="3"
                                class="form-control @error('notes') is-invalid @enderror"
                            >{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-danger">Cancel Purchase Order</button>
                        </div>
                    </form>
                @endcan
            @endif
        </div>
    </div>
@endsection
