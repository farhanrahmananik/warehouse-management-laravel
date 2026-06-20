<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public const MOVEMENT_OPENING_BALANCE = 'opening_balance';

    public const MOVEMENT_ADJUSTMENT_IN = 'adjustment_in';

    public const MOVEMENT_ADJUSTMENT_OUT = 'adjustment_out';

    /**
     * @param  array{warehouse_id: int|string, product_id: int|string, movement_type: string, quantity: int|float|string, remarks?: string|null}  $data
     */
    public function create(array $data, int $createdBy): WarehouseStock
    {
        return DB::transaction(function () use ($data, $createdBy): WarehouseStock {
            $warehouseId = (int) $data['warehouse_id'];
            $productId = (int) $data['product_id'];
            $movementType = (string) $data['movement_type'];
            $quantity = round((float) $data['quantity'], 4);

            $stock = $this->lockedWarehouseStock($warehouseId, $productId);
            $currentQuantity = round((float) $stock->quantity, 4);
            $reservedQuantity = round((float) $stock->reserved_quantity, 4);

            $balanceAfter = match ($movementType) {
                self::MOVEMENT_OPENING_BALANCE => $this->openingBalance($warehouseId, $productId, $quantity, $reservedQuantity),
                self::MOVEMENT_ADJUSTMENT_IN => $currentQuantity + $quantity,
                self::MOVEMENT_ADJUSTMENT_OUT => $currentQuantity - $quantity,
                default => throw ValidationException::withMessages([
                    'movement_type' => 'The selected movement type is invalid.',
                ]),
            };
            $balanceAfter = round($balanceAfter, 4);

            $this->ensureStockCanSettle($balanceAfter, $reservedQuantity);

            $stock->update([
                'quantity' => $balanceAfter,
            ]);

            StockMovement::create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'balance_after' => $balanceAfter,
                'reference_type' => null,
                'reference_id' => null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $createdBy,
            ]);

            return $stock->refresh();
        });
    }

    public function activeWarehouses(): Collection
    {
        return Warehouse::active()
            ->orderBy('name')
            ->orderBy('code')
            ->get();
    }

    public function activeProducts(): Collection
    {
        return Product::active()
            ->with(['category', 'unit'])
            ->orderBy('name')
            ->get();
    }

    private function lockedWarehouseStock(int $warehouseId, int $productId): WarehouseStock
    {
        $stock = WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        WarehouseStock::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'quantity' => 0,
            'reserved_quantity' => 0,
        ]);

        return WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function openingBalance(int $warehouseId, int $productId, float $quantity, float $reservedQuantity): float
    {
        $hasExistingMovement = StockMovement::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->exists();

        if ($hasExistingMovement) {
            throw ValidationException::withMessages([
                'movement_type' => 'Opening balance can only be created before any stock movement exists for this warehouse and product.',
            ]);
        }

        if ($quantity < $reservedQuantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock quantity cannot be lower than reserved quantity.',
            ]);
        }

        return $quantity;
    }

    private function ensureStockCanSettle(float $balanceAfter, float $reservedQuantity): void
    {
        if ($balanceAfter < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock quantity cannot be reduced below zero.',
            ]);
        }

        if ($balanceAfter < $reservedQuantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock quantity cannot be lower than reserved quantity.',
            ]);
        }
    }
}
