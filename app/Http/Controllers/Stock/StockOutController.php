<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockOutIndexRequest;
use App\Http\Requests\Stock\StoreStockOutRequest;
use App\Models\StockOut;
use App\Services\Stock\StockOutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockOutController extends Controller
{
    public function __construct(
        private readonly StockOutService $stockOutService,
    ) {
    }

    public function index(StockOutIndexRequest $request): View
    {
        $filters = $request->validated();
        $stockOuts = $this->stockOutService->paginate($filters);
        $warehouses = $this->stockOutService->activeWarehouses();
        $products = $this->stockOutService->activeProducts();

        return view('stock.outs.index', compact('stockOuts', 'warehouses', 'products', 'filters'));
    }

    public function create(): View
    {
        $warehouses = $this->stockOutService->activeWarehouses();
        $products = $this->stockOutService->activeProducts();

        return view('stock.outs.create', compact('warehouses', 'products'));
    }

    public function store(StoreStockOutRequest $request): RedirectResponse
    {
        $stockOut = $this->stockOutService->create($request->validated(), $request->user());

        return redirect()
            ->route('stock-outs.show', $stockOut)
            ->with('success', 'Stock out document created successfully.');
    }

    public function show(StockOut $stockOut): View
    {
        $stockOut = $this->stockOutService->loadForShow($stockOut);
        $movements = $this->stockOutService->movements($stockOut);

        return view('stock.outs.show', compact('stockOut', 'movements'));
    }
}
