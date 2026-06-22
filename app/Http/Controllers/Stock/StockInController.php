<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockInIndexRequest;
use App\Http\Requests\Stock\StoreStockInRequest;
use App\Models\StockIn;
use App\Services\Stock\StockInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockInController extends Controller
{
    public function __construct(
        private readonly StockInService $stockInService,
    ) {
    }

    public function index(StockInIndexRequest $request): View
    {
        $filters = $request->validated();
        $stockIns = $this->stockInService->paginate($filters);
        $warehouses = $this->stockInService->activeWarehouses();
        $products = $this->stockInService->activeProducts();

        return view('stock.ins.index', compact('stockIns', 'warehouses', 'products', 'filters'));
    }

    public function create(): View
    {
        $warehouses = $this->stockInService->activeWarehouses();
        $products = $this->stockInService->activeProducts();

        return view('stock.ins.create', compact('warehouses', 'products'));
    }

    public function store(StoreStockInRequest $request): RedirectResponse
    {
        $stockIn = $this->stockInService->create($request->validated(), $request->user());

        return redirect()
            ->route('stock-ins.show', $stockIn)
            ->with('success', 'Stock in document created successfully.');
    }

    public function show(StockIn $stockIn): View
    {
        $stockIn = $this->stockInService->loadForShow($stockIn);
        $movements = $this->stockInService->movements($stockIn);

        return view('stock.ins.show', compact('stockIn', 'movements'));
    }
}
