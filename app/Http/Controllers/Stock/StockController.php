<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockIndexRequest;
use App\Services\Stock\StockOverviewService;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        private readonly StockOverviewService $stockOverviewService,
    ) {
    }

    public function index(StockIndexRequest $request): View
    {
        $filters = $request->validated();
        $stocks = $this->stockOverviewService->paginate($filters);
        $warehouses = $this->stockOverviewService->activeWarehouses();
        $products = $this->stockOverviewService->activeProducts();

        return view('stock.index', compact('stocks', 'warehouses', 'products', 'filters'));
    }
}
