<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
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

class StockTransferWorkflowTest extends TestCase
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

    public function test_guest_is_redirected_from_stock_transfers_index_to_login(): void
    {
        $this->get(route('stock-transfers.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_stock_transfer_view_permission_receives_403_from_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('stock-transfers.index'))
            ->assertForbidden();
    }

    public function test_user_with_stock_transfer_view_permission_can_view_index(): void
    {
        $user = $this->userWithPermissions(['stock-transfer.view']);

        $this->actingAs($user)
            ->get(route('stock-transfers.index'))
            ->assertOk()
            ->assertSee('Stock Transfers');
    }

    public function test_user_with_stock_transfer_create_permission_can_view_create_page(): void
    {
        $this->warehouse();
        $this->warehouse();
        $this->product();
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->get(route('stock-transfers.create'))
            ->assertOk()
            ->assertSee('Create Stock Transfer');
    }

    public function test_stock_transfer_creation_validates_required_fields(): void
    {
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->post(route('stock-transfers.store'), [])
            ->assertSessionHasErrors([
                'from_warehouse_id',
                'to_warehouse_id',
                'transfer_date',
                'items',
            ]);
    }

    public function test_stock_transfer_rejects_same_source_and_destination_warehouse(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $payload = $this->validStockTransferPayload($warehouse, $warehouse, $product, 1);

        $this->actingAs($user)
            ->from(route('stock-transfers.create'))
            ->post(route('stock-transfers.store'), $payload)
            ->assertRedirect(route('stock-transfers.create'))
            ->assertSessionHasErrors(['to_warehouse_id']);
    }

    public function test_user_with_stock_transfer_create_permission_can_create_stock_transfer_document(): void
    {
        $fromWarehouse = $this->warehouse();
        $toWarehouse = $this->warehouse();
        $product = $this->product();
        WarehouseStock::query()->create([
            'warehouse_id' => $fromWarehouse->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'reserved_quantity' => 2,
        ]);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $response = $this->actingAs($user)
            ->post(route('stock-transfers.store'), $this->validStockTransferPayload($fromWarehouse, $toWarehouse, $product, 5));

        $stockTransfer = StockTransfer::query()->firstOrFail();
        $response->assertRedirect(route('stock-transfers.show', $stockTransfer));

        $this->assertStringStartsWith('ST-', $stockTransfer->document_no);
        $this->assertSame($fromWarehouse->id, $stockTransfer->from_warehouse_id);
        $this->assertSame($toWarehouse->id, $stockTransfer->to_warehouse_id);
        $this->assertSame($user->id, $stockTransfer->created_by);

        $item = StockTransferItem::query()->firstOrFail();
        $this->assertSame($stockTransfer->id, $item->stock_transfer_id);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('5.0000', $item->quantity);
    }

    public function test_stock_transfer_decreases_source_warehouse_stock_quantity(): void
    {
        [$fromWarehouse, $toWarehouse, $product, $sourceStock] = $this->stockedTransferSetup(12, 2);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->post(route('stock-transfers.store'), $this->validStockTransferPayload($fromWarehouse, $toWarehouse, $product, 4))
            ->assertRedirect();

        $sourceStock->refresh();
        $this->assertSame('8.0000', $sourceStock->quantity);
        $this->assertSame('2.0000', $sourceStock->reserved_quantity);
    }

    public function test_stock_transfer_increases_destination_warehouse_stock_quantity(): void
    {
        [$fromWarehouse, $toWarehouse, $product] = $this->stockedTransferSetup(12, 2, 3, 1);
        $destinationStock = WarehouseStock::query()
            ->where('warehouse_id', $toWarehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->post(route('stock-transfers.store'), $this->validStockTransferPayload($fromWarehouse, $toWarehouse, $product, 4))
            ->assertRedirect();

        $destinationStock->refresh();
        $this->assertSame('7.0000', $destinationStock->quantity);
        $this->assertSame('1.0000', $destinationStock->reserved_quantity);
    }

    public function test_stock_transfer_creates_transfer_out_movement_for_source_warehouse(): void
    {
        [$fromWarehouse, $toWarehouse, $product] = $this->stockedTransferSetup(10, 0);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->post(route('stock-transfers.store'), $this->validStockTransferPayload($fromWarehouse, $toWarehouse, $product, 3, [
                'remarks' => 'Move to branch.',
            ]))
            ->assertRedirect();

        $stockTransfer = StockTransfer::query()->firstOrFail();
        $movement = StockMovement::query()
            ->where('movement_type', 'transfer_out')
            ->firstOrFail();

        $this->assertSame($fromWarehouse->id, $movement->warehouse_id);
        $this->assertSame($product->id, $movement->product_id);
        $this->assertSame('3.0000', $movement->quantity);
        $this->assertSame('7.0000', $movement->balance_after);
        $this->assertSame(StockTransfer::class, $movement->reference_type);
        $this->assertSame($stockTransfer->id, $movement->reference_id);
        $this->assertSame('Move to branch.', $movement->remarks);
        $this->assertSame($user->id, $movement->created_by);
    }

    public function test_stock_transfer_creates_transfer_in_movement_for_destination_warehouse(): void
    {
        [$fromWarehouse, $toWarehouse, $product] = $this->stockedTransferSetup(10, 0, 2, 0);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->post(route('stock-transfers.store'), $this->validStockTransferPayload($fromWarehouse, $toWarehouse, $product, 3, [
                'remarks' => 'Move to branch.',
            ]))
            ->assertRedirect();

        $stockTransfer = StockTransfer::query()->firstOrFail();
        $movement = StockMovement::query()
            ->where('movement_type', 'transfer_in')
            ->firstOrFail();

        $this->assertSame($toWarehouse->id, $movement->warehouse_id);
        $this->assertSame($product->id, $movement->product_id);
        $this->assertSame('3.0000', $movement->quantity);
        $this->assertSame('5.0000', $movement->balance_after);
        $this->assertSame(StockTransfer::class, $movement->reference_type);
        $this->assertSame($stockTransfer->id, $movement->reference_id);
        $this->assertSame('Move to branch.', $movement->remarks);
        $this->assertSame($user->id, $movement->created_by);
    }

    public function test_stock_transfer_show_page_displays_document_and_item_details(): void
    {
        $fromWarehouse = $this->warehouse(['code' => 'FROM-001', 'name' => 'Source Warehouse']);
        $toWarehouse = $this->warehouse(['code' => 'TO-001', 'name' => 'Destination Warehouse']);
        $product = $this->product([
            'name' => 'Display Product',
            'slug' => 'display-transfer-product',
            'sku' => 'DISPLAY-TRANSFER-001',
        ]);
        $stockTransfer = StockTransfer::query()->create([
            'document_no' => 'ST-TEST-0001',
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'transfer_date' => now()->toDateString(),
            'remarks' => 'Display transfer remarks.',
            'created_by' => User::factory()->create()->id,
        ]);
        $stockTransfer->items()->create([
            'product_id' => $product->id,
            'quantity' => 7,
            'remarks' => 'Display item remarks.',
        ]);
        $user = $this->userWithPermissions(['stock-transfer.view']);

        $this->actingAs($user)
            ->get(route('stock-transfers.show', $stockTransfer))
            ->assertOk()
            ->assertSee('ST-TEST-0001')
            ->assertSee('Source Warehouse')
            ->assertSee('Destination Warehouse')
            ->assertSee('Display Product')
            ->assertSee('DISPLAY-TRANSFER-001')
            ->assertSee('7.0000')
            ->assertSee('Display item remarks.');
    }

    public function test_stock_transfer_cannot_transfer_more_than_available_stock(): void
    {
        [$fromWarehouse, $toWarehouse, $product, $sourceStock] = $this->stockedTransferSetup(5, 1);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->from(route('stock-transfers.create'))
            ->post(route('stock-transfers.store'), $this->validStockTransferPayload($fromWarehouse, $toWarehouse, $product, 5))
            ->assertRedirect(route('stock-transfers.create'))
            ->assertSessionHasErrors(['items.0.quantity']);

        $sourceStock->refresh();
        $this->assertSame('5.0000', $sourceStock->quantity);
        $this->assertSame(0, StockTransfer::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_stock_transfer_cannot_reduce_source_stock_below_reserved_quantity(): void
    {
        [$fromWarehouse, $toWarehouse, $product, $sourceStock] = $this->stockedTransferSetup(10, 7);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $this->actingAs($user)
            ->from(route('stock-transfers.create'))
            ->post(route('stock-transfers.store'), $this->validStockTransferPayload($fromWarehouse, $toWarehouse, $product, 4))
            ->assertRedirect(route('stock-transfers.create'))
            ->assertSessionHasErrors(['items.0.quantity']);

        $sourceStock->refresh();
        $this->assertSame('10.0000', $sourceStock->quantity);
        $this->assertSame('7.0000', $sourceStock->reserved_quantity);
        $this->assertSame(0, StockTransfer::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_duplicate_product_rows_are_rejected(): void
    {
        [$fromWarehouse, $toWarehouse, $product] = $this->stockedTransferSetup(10, 0);
        $user = $this->userWithPermissions(['stock-transfer.create']);

        $payload = $this->validStockTransferPayload($fromWarehouse, $toWarehouse, $product, 5);
        $payload['items'][] = [
            'product_id' => $product->id,
            'quantity' => 2,
            'remarks' => 'Duplicate product row.',
        ];

        $this->actingAs($user)
            ->from(route('stock-transfers.create'))
            ->post(route('stock-transfers.store'), $payload)
            ->assertRedirect(route('stock-transfers.create'))
            ->assertSessionHasErrors();

        $this->assertSame(0, StockTransfer::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
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
            'description' => 'Temporary role for stock transfer workflow tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
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
            'description' => 'Test stock transfer product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @return array{0: Warehouse, 1: Warehouse, 2: Product, 3: WarehouseStock}
     */
    private function stockedTransferSetup(
        float|int $sourceQuantity,
        float|int $sourceReservedQuantity,
        float|int|null $destinationQuantity = null,
        float|int $destinationReservedQuantity = 0,
    ): array {
        $fromWarehouse = $this->warehouse();
        $toWarehouse = $this->warehouse();
        $product = $this->product();

        $sourceStock = WarehouseStock::query()->create([
            'warehouse_id' => $fromWarehouse->id,
            'product_id' => $product->id,
            'quantity' => $sourceQuantity,
            'reserved_quantity' => $sourceReservedQuantity,
        ]);

        if ($destinationQuantity !== null) {
            WarehouseStock::query()->create([
                'warehouse_id' => $toWarehouse->id,
                'product_id' => $product->id,
                'quantity' => $destinationQuantity,
                'reserved_quantity' => $destinationReservedQuantity,
            ]);
        }

        return [$fromWarehouse, $toWarehouse, $product, $sourceStock];
    }

    /**
     * @param  array<string, mixed>  $itemOverrides
     * @return array<string, mixed>
     */
    private function validStockTransferPayload(
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        Product $product,
        float|int $quantity,
        array $itemOverrides = [],
    ): array {
        return [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'transfer_date' => now()->toDateString(),
            'remarks' => 'Header stock transfer remarks.',
            'items' => [
                array_replace([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'remarks' => 'Item stock transfer remarks.',
                ], $itemOverrides),
            ],
        ];
    }
}
