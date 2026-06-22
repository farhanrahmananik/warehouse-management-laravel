@extends('layouts.app')

@section('title', $stockIn->document_no . ' - ' . config('app.name'))

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $stockIn->document_no }}</h1>
            <p class="text-muted mb-0">Review stock in document details.</p>
        </div>

        <a href="{{ route('stock-ins.index') }}" class="btn btn-outline-secondary">Back to Stock In</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Document Details</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Document No</dt>
                        <dd class="col-sm-8"><code>{{ $stockIn->document_no }}</code></dd>

                        <dt class="col-sm-4">Stock Date</dt>
                        <dd class="col-sm-8">{{ $stockIn->stock_date?->format('M d, Y') ?? '-' }}</dd>

                        <dt class="col-sm-4">Warehouse</dt>
                        <dd class="col-sm-8">
                            {{ $stockIn->warehouse?->name ?? '-' }}
                            @if ($stockIn->warehouse?->code)
                                <span class="text-muted">({{ $stockIn->warehouse->code }})</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Created By</dt>
                        <dd class="col-sm-8">{{ $stockIn->creator?->name ?? 'System' }}</dd>

                        <dt class="col-sm-4">Created At</dt>
                        <dd class="col-sm-8">{{ $stockIn->created_at?->format('M d, Y h:i A') ?? '-' }}</dd>

                        <dt class="col-sm-4">Remarks</dt>
                        <dd class="col-sm-8">{{ $stockIn->remarks ?: 'No remarks provided.' }}</dd>
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
                                    <th scope="col">SKU</th>
                                    <th scope="col" class="text-end">Quantity</th>
                                    <th scope="col">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($stockIn->items as $item)
                                    <tr>
                                        <td class="fw-semibold">{{ $item->product?->name ?? 'N/A' }}</td>
                                        <td><code>{{ $item->product?->sku ?? 'N/A' }}</code></td>
                                        <td class="text-end">{{ number_format((float) $item->quantity, 4) }}</td>
                                        <td>{{ $item->remarks ?: 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No items found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Stock Movements</h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col" class="text-end">Quantity</th>
                                    <th scope="col" class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($movements as $movement)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $movement->product?->name ?? 'N/A' }}</div>
                                            <div class="small text-muted">{{ $movement->created_at?->format('Y-m-d H:i') ?? '' }}</div>
                                        </td>
                                        <td class="text-end">{{ number_format((float) $movement->quantity, 4) }}</td>
                                        <td class="text-end">{{ number_format((float) $movement->balance_after, 4) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No movements found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
