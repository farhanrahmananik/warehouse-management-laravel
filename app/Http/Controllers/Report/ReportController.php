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
use App\Services\Report\ReportCsvExportService;
use App\Services\Report\StockMovementReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function inventory(
        InventoryReportFilterRequest $request,
        InventoryReportService $inventoryReportService,
    ): View {
        return view('reports.inventory', $inventoryReportService->generate($request->validated()));
    }

    public function exportInventory(
        InventoryReportFilterRequest $request,
        InventoryReportService $inventoryReportService,
        ReportCsvExportService $csvExportService,
    ): StreamedResponse {
        return $csvExportService->download(
            filenamePrefix: 'inventory-report',
            headers: [
                'Warehouse',
                'Product SKU',
                'Product Name',
                'Category',
                'Quantity',
                'Reserved Quantity',
                'Available Quantity',
                'Reorder Level',
            ],
            rows: $inventoryReportService->exportRows($request->validated()),
        );
    }

    public function stockMovements(
        StockMovementReportFilterRequest $request,
        StockMovementReportService $stockMovementReportService,
    ): View {
        return view('reports.stock-movements', $stockMovementReportService->generate($request->validated()));
    }

    public function exportStockMovements(
        StockMovementReportFilterRequest $request,
        StockMovementReportService $stockMovementReportService,
        ReportCsvExportService $csvExportService,
    ): StreamedResponse {
        return $csvExportService->download(
            filenamePrefix: 'stock-movements-report',
            headers: [
                'Date',
                'Warehouse',
                'Product SKU',
                'Product Name',
                'Movement Type',
                'Quantity',
                'Balance After',
                'Reference Type',
                'Reference ID',
            ],
            rows: $stockMovementReportService->exportRows($request->validated()),
        );
    }

    public function lowStock(
        LowStockReportFilterRequest $request,
        LowStockReportService $lowStockReportService,
    ): View {
        return view('reports.low-stock', $lowStockReportService->generate($request->validated()));
    }

    public function exportLowStock(
        LowStockReportFilterRequest $request,
        LowStockReportService $lowStockReportService,
        ReportCsvExportService $csvExportService,
    ): StreamedResponse {
        return $csvExportService->download(
            filenamePrefix: 'low-stock-report',
            headers: [
                'Warehouse',
                'Product SKU',
                'Product Name',
                'Category',
                'Quantity',
                'Reserved Quantity',
                'Available Quantity',
                'Reorder Level',
                'Shortage Quantity',
            ],
            rows: $lowStockReportService->exportRows($request->validated()),
        );
    }

    public function purchaseOrders(
        PurchaseOrderReportFilterRequest $request,
        PurchaseOrderReportService $purchaseOrderReportService,
    ): View {
        return view('reports.purchase-orders', $purchaseOrderReportService->generate($request->validated()));
    }

    public function exportPurchaseOrders(
        PurchaseOrderReportFilterRequest $request,
        PurchaseOrderReportService $purchaseOrderReportService,
        ReportCsvExportService $csvExportService,
    ): StreamedResponse {
        return $csvExportService->download(
            filenamePrefix: 'purchase-orders-report',
            headers: [
                'PO Number',
                'Supplier',
                'Warehouse',
                'Status',
                'Order Date',
                'Expected Date',
                'Items Count',
                'Ordered Quantity',
                'Received Quantity',
                'Pending Quantity',
                'Total Amount',
            ],
            rows: $purchaseOrderReportService->exportRows($request->validated()),
        );
    }
}
