<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\InventoryReportFilterRequest;
use App\Http\Requests\Report\LowStockReportFilterRequest;
use App\Http\Requests\Report\PurchaseOrderReportFilterRequest;
use App\Http\Requests\Report\StockMovementReportFilterRequest;
use App\Services\Report\InventoryReportService;
use App\Services\Report\LowStockReportService;
use App\Services\Report\PurchaseOrderReportService;
use App\Services\Report\StockMovementReportService;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function inventory(
        InventoryReportFilterRequest $request,
        InventoryReportService $inventoryReportService,
    ): View
    {
        return view('reports.inventory', $inventoryReportService->generate($request->validated()));
    }

    public function stockMovements(
        StockMovementReportFilterRequest $request,
        StockMovementReportService $stockMovementReportService,
    ): View
    {
        return view('reports.stock-movements', $stockMovementReportService->generate($request->validated()));
    }

    public function lowStock(
        LowStockReportFilterRequest $request,
        LowStockReportService $lowStockReportService,
    ): View
    {
        return view('reports.low-stock', $lowStockReportService->generate($request->validated()));
    }

    public function purchaseOrders(
        PurchaseOrderReportFilterRequest $request,
        PurchaseOrderReportService $purchaseOrderReportService,
    ): View
    {
        return view('reports.purchase-orders', $purchaseOrderReportService->generate($request->validated()));
    }
}
