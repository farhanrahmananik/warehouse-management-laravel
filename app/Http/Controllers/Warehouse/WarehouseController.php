<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
    ) {
    }

    public function index(): View
    {
        $warehouses = $this->warehouseService->list()->paginate(15);

        return view('warehouse.warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        return view('warehouse.warehouses.create');
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $this->warehouseService->create($request->validated());

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Warehouse created successfully.');
    }

    public function show(Warehouse $warehouse): View
    {
        return view('warehouse.warehouses.show', compact('warehouse'));
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('warehouse.warehouses.edit', compact('warehouse'));
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->warehouseService->update($warehouse, $request->validated());

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->warehouseService->delete($warehouse);

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Warehouse deleted successfully.');
    }
}
