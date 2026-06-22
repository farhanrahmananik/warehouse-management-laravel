<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockTransferAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_creating_a_stock_transfer_document_creates_exactly_one_audit_log(): void
    {
        $sourceWarehouse = $this->warehouse(['code' => 'SRC-001', 'name' => 'Source Warehouse']);
        $destinationWarehouse = $this->warehouse(['code' => 'DST-001', 'name' => 'Destination Warehouse']);
        $product = $this->product(['name' => 'Transfer Product', 'slug' => 'transfer-product', 'sku' => 'TRANSFER-001']);
        $this->warehouseStock($sourceWarehouse, $product, quantity: 10, reservedQuantity: 2);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $response = $this->actingAs($user)
            ->post(route('stock-transfers.store'), $this->validStockTransferPayload($sourceWarehouse, $destinationWarehouse, $product, 5));

        $stockTransfer = StockTransfer::query()->firstOrFail();
        $movementIds = StockMovement::query()->orderBy('id')->pluck('id')->all();

        $response->assertRedirect(route('stock-transfers.show', $stockTransfer));

        $this->assertCount(2, $movementIds);
        $this->assertSame(1, AuditLog::query()->where('module', 'stock_transfers')->count());

        $auditLog = $this->latestAuditLog();

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($stockTransfer->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($stockTransfer->id, $auditLog->auditable_id);
        $this->assertSame('stock_transfer_created', $auditLog->event);
        $this->assertSame('stock_transfers', $auditLog->module);
        $this->assertSame(
            sprintf(
                'Stock transfer "%s" was created from warehouse "Source Warehouse" to warehouse "Destination Warehouse".',
                $stockTransfer->document_no,
            ),
            $auditLog->description,
        );
        $this->assertSame([
            'model' => 'stock_transfer',
            'stock_transfer_id' => $stockTransfer->id,
            'source_warehouse_id' => $sourceWarehouse->id,
            'destination_warehouse_id' => $destinationWarehouse->id,
            'movement_ids' => $movementIds,
        ], $auditLog->metadata);
    }

    public function test_audit_log_contains_source_destination_item_product_and_quantity_context(): void
    {
        $sourceWarehouse = $this->warehouse(['code' => 'SRC-002', 'name' => 'Context Source']);
        $destinationWarehouse = $this->warehouse(['code' => 'DST-002', 'name' => 'Context Destination']);
        $firstProduct = $this->product(['name' => 'First Transfer Product', 'slug' => 'first-transfer-product', 'sku' => 'TRANSFER-CONTEXT-001']);
        $secondProduct = $this->product(['name' => 'Second Transfer Product', 'slug' => 'second-transfer-product', 'sku' => 'TRANSFER-CONTEXT-002']);
        $this->warehouseStock($sourceWarehouse, $firstProduct, quantity: 10, reservedQuantity: 1);
        $this->warehouseStock($sourceWarehouse, $secondProduct, quantity: 12, reservedQuantity: 2);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->post(route('stock-transfers.store'), [
                'from_warehouse_id' => $sourceWarehouse->id,
                'to_warehouse_id' => $destinationWarehouse->id,
                'transfer_date' => '2026-06-22',
                'remarks' => 'Document level transfer remarks.',
                'items' => [
                    [
                        'product_id' => $firstProduct->id,
                        'quantity' => 2.5,
                        'remarks' => 'First transfer item remarks.',
                    ],
                    [
                        'product_id' => $secondProduct->id,
                        'quantity' => 3.75,
                        'remarks' => 'Second transfer item remarks.',
                    ],
                ],
            ])
            ->assertRedirect();

        $stockTransfer = StockTransfer::query()->firstOrFail();
        $auditLog = $this->latestAuditLog();

        $this->assertSame($stockTransfer->id, $auditLog->new_values['stock_transfer_id']);
        $this->assertSame($stockTransfer->document_no, $auditLog->new_values['reference_no']);
        $this->assertSame($sourceWarehouse->id, $auditLog->new_values['source_warehouse_id']);
        $this->assertSame('Context Source', $auditLog->new_values['source_warehouse_name']);
        $this->assertSame($destinationWarehouse->id, $auditLog->new_values['destination_warehouse_id']);
        $this->assertSame('Context Destination', $auditLog->new_values['destination_warehouse_name']);
        $this->assertSame('2026-06-22', $auditLog->new_values['transfer_date']);
        $this->assertSame(2, $auditLog->new_values['total_items']);
        $this->assertSame('6.2500', $auditLog->new_values['total_quantity']);
        $this->assertSame('Document level transfer remarks.', $auditLog->new_values['remarks']);
        $this->assertCount(2, $auditLog->new_values['items']);
        $this->assertSame($firstProduct->id, $auditLog->new_values['items'][0]['product_id']);
        $this->assertSame('First Transfer Product', $auditLog->new_values['items'][0]['product_name']);
        $this->assertSame('2.5000', $auditLog->new_values['items'][0]['quantity']);
        $this->assertSame('First transfer item remarks.', $auditLog->new_values['items'][0]['remarks']);
        $this->assertSame($secondProduct->id, $auditLog->new_values['items'][1]['product_id']);
        $this->assertSame('Second Transfer Product', $auditLog->new_values['items'][1]['product_name']);
        $this->assertSame('3.7500', $auditLog->new_values['items'][1]['quantity']);
        $this->assertSame('Second transfer item remarks.', $auditLog->new_values['items'][1]['remarks']);
    }

    public function test_failed_stock_transfer_validation_does_not_create_audit_log(): void
    {
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->from(route('stock-transfers.create'))
            ->post(route('stock-transfers.store'), [])
            ->assertRedirect(route('stock-transfers.create'))
            ->assertSessionHasErrors([
                'from_warehouse_id',
                'to_warehouse_id',
                'transfer_date',
                'items',
            ]);

        $this->assertSame(0, StockTransfer::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'stock_transfers')->count());
    }

    public function test_failed_stock_transfer_for_insufficient_stock_does_not_create_audit_log(): void
    {
        $sourceWarehouse = $this->warehouse(['code' => 'SRC-003', 'name' => 'Low Stock Source']);
        $destinationWarehouse = $this->warehouse(['code' => 'DST-003', 'name' => 'Low Stock Destination']);
        $product = $this->product(['name' => 'Low Stock Product', 'slug' => 'low-stock-product', 'sku' => 'LOW-STOCK-001']);
        $sourceStock = $this->warehouseStock($sourceWarehouse, $product, quantity: 3, reservedQuantity: 0);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->from(route('stock-transfers.create'))
            ->post(route('stock-transfers.store'), $this->validStockTransferPayload($sourceWarehouse, $destinationWarehouse, $product, 4))
            ->assertRedirect(route('stock-transfers.create'))
            ->assertSessionHasErrors(['items.0.quantity']);

        $this->assertSame('3.0000', $sourceStock->refresh()->quantity);
        $this->assertSame(0, StockTransfer::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'stock_transfers')->count());
    }

    public function test_failed_stock_transfer_with_same_source_and_destination_warehouse_does_not_create_audit_log(): void
    {
        $warehouse = $this->warehouse(['code' => 'SAME-001', 'name' => 'Same Warehouse']);
        $product = $this->product(['name' => 'Same Warehouse Product', 'slug' => 'same-warehouse-product', 'sku' => 'SAME-WAREHOUSE-001']);
        $this->warehouseStock($warehouse, $product, quantity: 10, reservedQuantity: 0);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->from(route('stock-transfers.create'))
            ->post(route('stock-transfers.store'), $this->validStockTransferPayload($warehouse, $warehouse, $product, 2))
            ->assertRedirect(route('stock-transfers.create'))
            ->assertSessionHasErrors(['to_warehouse_id']);

        $this->assertSame(0, StockTransfer::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'stock_transfers')->count());
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function userWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create();
        $permissions = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->get();

        $this->assertCount(count($permissionSlugs), $permissions);

        $role = Role::query()->create([
            'name' => 'Test Role',
            'slug' => 'test-role-'.Str::uuid(),
            'description' => 'Temporary role for stock transfer audit log tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function latestAuditLog(): AuditLog
    {
        return AuditLog::query()
            ->where('module', 'stock_transfers')
            ->where('event', 'stock_transfer_created')
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function warehouse(array $overrides = []): Warehouse
    {
        $code = $overrides['code'] ?? 'WH-'.Str::upper(Str::random(8));

        return Warehouse::query()->create(array_replace([
            'code' => $code,
            'name' => 'Warehouse '.$code,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function product(array $overrides = []): Product
    {
        $sku = $overrides['sku'] ?? 'SKU-'.Str::upper(Str::random(8));
        $category = Category::query()->create([
            'name' => 'Test Category '.$sku,
            'slug' => 'test-category-'.Str::slug($sku),
            'is_active' => true,
        ]);
        $unit = Unit::query()->create([
            'name' => 'Piece '.$sku,
            'short_name' => 'pcs-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        return Product::query()->create(array_replace([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Test Product '.$sku,
            'slug' => 'test-product-'.Str::slug($sku),
            'sku' => $sku,
            'description' => 'Test stock transfer audit product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ], $overrides));
    }

    private function warehouseStock(
        Warehouse $warehouse,
        Product $product,
        float|int $quantity,
        float|int $reservedQuantity,
    ): WarehouseStock {
        return WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validStockTransferPayload(
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        Product $product,
        float|int $quantity,
    ): array {
        return [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'transfer_date' => now()->toDateString(),
            'remarks' => 'Header stock transfer remarks.',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'remarks' => 'Item stock transfer remarks.',
                ],
            ],
        ];
    }
}
