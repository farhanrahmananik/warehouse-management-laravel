<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockTransferIndexRequest;
use App\Http\Requests\Stock\StoreStockTransferRequest;
use App\Models\StockTransfer;
use App\Services\Stock\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockTransferService $stockTransferService,
    ) {
    }

    public function index(StockTransferIndexRequest $request): View
    {
        $filters = $request->validated();
        $stockTransfers = $this->stockTransferService->paginate($filters);
        $warehouses = $this->stockTransferService->activeWarehouses();
        $products = $this->stockTransferService->activeProducts();

        return view('stock.transfers.index', compact('stockTransfers', 'warehouses', 'products', 'filters'));
    }

    public function create(): View
    {
        $warehouses = $this->stockTransferService->activeWarehouses();
        $products = $this->stockTransferService->activeProducts();

        return view('stock.transfers.create', compact('warehouses', 'products'));
    }

    public function store(StoreStockTransferRequest $request): RedirectResponse
    {
        $stockTransfer = $this->stockTransferService->create($request->validated(), $request->user());

        return redirect()
            ->route('stock-transfers.show', $stockTransfer)
            ->with('success', 'Stock transfer document created successfully.');
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $stockTransfer = $this->stockTransferService->loadForShow($stockTransfer);
        $movements = $this->stockTransferService->movements($stockTransfer);

        return view('stock.transfers.show', compact('stockTransfer', 'movements'));
    }
}
