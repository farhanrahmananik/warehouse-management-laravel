<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index');
    }

    public function inventory(): View
    {
        return view('reports.inventory');
    }

    public function stockMovements(): View
    {
        return view('reports.stock-movements');
    }

    public function lowStock(): View
    {
        return view('reports.low-stock');
    }

    public function purchaseOrders(): View
    {
        return view('reports.purchase-orders');
    }
}
