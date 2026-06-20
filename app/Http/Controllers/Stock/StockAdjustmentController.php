<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreStockAdjustmentRequest;
use App\Services\Stock\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly StockAdjustmentService $stockAdjustmentService,
    ) {
    }

    public function create(): View
    {
        $warehouses = $this->stockAdjustmentService->activeWarehouses();
        $products = $this->stockAdjustmentService->activeProducts();

        return view('stock.adjustments.create', compact('warehouses', 'products'));
    }

    public function store(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        $this->stockAdjustmentService->create(
            $request->validated(),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('stock.index')
            ->with('success', 'Stock adjustment saved successfully.');
    }
}
