<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockMovementIndexRequest;
use App\Services\Stock\StockMovementService;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {
    }

    public function index(StockMovementIndexRequest $request): View
    {
        $filters = $request->validated();
        $movements = $this->stockMovementService->paginate($filters);
        $warehouses = $this->stockMovementService->activeWarehouses();
        $products = $this->stockMovementService->activeProducts();
        $movementTypes = $this->stockMovementService->movementTypes();

        return view(
            'stock.movements.index',
            compact('movements', 'warehouses', 'products', 'movementTypes', 'filters'),
        );
    }
}
