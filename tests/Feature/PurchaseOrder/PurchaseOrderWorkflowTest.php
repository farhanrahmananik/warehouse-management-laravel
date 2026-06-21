<?php

namespace Tests\Feature\PurchaseOrder;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
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

class PurchaseOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seedPermissionsAndRoles();
    }

    public function test_guest_is_redirected_from_purchase_orders_index_to_login(): void
    {
        $this->get(route('purchase-orders.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_purchase_orders_view_permission_receives_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('purchase-orders.index'))
            ->assertForbidden();
    }

    public function test_user_with_purchase_orders_view_permission_can_view_index(): void
    {
        $user = $this->actingUserWithPermissions(['purchase-orders.view']);

        $this->actingAs($user)
            ->get(route('purchase-orders.index'))
            ->assertOk()
            ->assertSee('Purchase Orders');
    }

    public function test_user_with_purchase_orders_create_permission_can_view_create_page(): void
    {
        $user = $this->actingUserWithPermissions(['purchase-orders.create']);

        $this->actingAs($user)
            ->get(route('purchase-orders.create'))
            ->assertOk()
            ->assertSee('Create Purchase Order');
    }

    public function test_purchase_order_creation_validates_required_fields(): void
    {
        $user = $this->actingUserWithPermissions(['purchase-orders.create']);

        $this->actingAs($user)
            ->post(route('purchase-orders.store'), [])
            ->assertSessionHasErrors([
                'supplier_id',
                'warehouse_id',
                'order_date',
                'items',
            ]);
    }

    public function test_user_with_purchase_orders_create_permission_can_create_draft_purchase_order(): void
    {
        $user = $this->actingUserWithPermissions(['purchase-orders.create']);

        $response = $this->actingAs($user)
            ->post(route('purchase-orders.store'), $this->validPurchaseOrderPayload([
                'discount_amount' => 2,
                'tax_amount' => 1,
                'shipping_amount' => 3,
            ]));

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();
        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'subtotal' => 50.00,
            'discount_amount' => 2.00,
            'tax_amount' => 1.00,
            'shipping_amount' => 3.00,
            'total_amount' => 52.00,
            'created_by' => $user->id,
        ]);

        $item = PurchaseOrderItem::query()->firstOrFail();
        $this->assertSame('5.000', $item->quantity);
        $this->assertSame('0.000', $item->received_quantity);
        $this->assertSame('10.00', $item->unit_cost);
        $this->assertSame('50.00', $item->line_total);
        $this->assertSame(0, WarehouseStock::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_user_with_approve_permission_can_approve_draft_purchase_order(): void
    {
        $purchaseOrder = $this->createDraftPurchaseOrder();
        $this->createPurchaseOrderItem($purchaseOrder, $this->createProduct());
        $user = $this->actingUserWithPermissions(['purchase-orders.approve']);

        $this->actingAs($user)
            ->post(route('purchase-orders.approve', $purchaseOrder))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $purchaseOrder->status);
        $this->assertSame($user->id, $purchaseOrder->approved_by);
        $this->assertNotNull($purchaseOrder->approved_at);
    }

    public function test_approved_purchase_order_cannot_be_updated(): void
    {
        $purchaseOrder = $this->createDraftPurchaseOrder([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'notes' => 'Original notes',
        ]);
        $this->createPurchaseOrderItem($purchaseOrder, $this->createProduct());
        $user = $this->actingUserWithPermissions(['purchase-orders.update']);

        $this->actingAs($user)
            ->from(route('purchase-orders.edit', $purchaseOrder))
            ->put(route('purchase-orders.update', $purchaseOrder), $this->validPurchaseOrderPayload([
                'notes' => 'Changed notes',
            ]))
            ->assertRedirect(route('purchase-orders.edit', $purchaseOrder))
            ->assertSessionHasErrors('status');

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $purchaseOrder->status);
        $this->assertSame('Original notes', $purchaseOrder->notes);
    }

    public function test_user_can_cancel_approved_purchase_order(): void
    {
        $purchaseOrder = $this->createDraftPurchaseOrder([
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $this->createPurchaseOrderItem($purchaseOrder, $this->createProduct());
        $user = $this->actingUserWithPermissions(['purchase-orders.delete']);

        $this->actingAs($user)
            ->post(route('purchase-orders.cancel', $purchaseOrder), [
                'notes' => 'No longer needed.',
            ])
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $purchaseOrder->status);
        $this->assertSame($user->id, $purchaseOrder->cancelled_by);
        $this->assertNotNull($purchaseOrder->cancelled_at);
        $this->assertStringContainsString('No longer needed.', (string) $purchaseOrder->notes);
    }

    public function test_draft_purchase_order_can_be_soft_deleted(): void
    {
        $purchaseOrder = $this->createDraftPurchaseOrder();
        $this->createPurchaseOrderItem($purchaseOrder, $this->createProduct());
        $user = $this->actingUserWithPermissions(['purchase-orders.delete']);

        $this->actingAs($user)
            ->delete(route('purchase-orders.destroy', $purchaseOrder))
            ->assertRedirect(route('purchase-orders.index'));

        $this->assertSoftDeleted('purchase_orders', [
            'id' => $purchaseOrder->id,
        ]);
    }

    public function test_approved_purchase_order_cannot_be_deleted(): void
    {
        $purchaseOrder = $this->createDraftPurchaseOrder([
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $this->createPurchaseOrderItem($purchaseOrder, $this->createProduct());
        $user = $this->actingUserWithPermissions(['purchase-orders.delete']);

        $this->actingAs($user)
            ->from(route('purchase-orders.show', $purchaseOrder))
            ->delete(route('purchase-orders.destroy', $purchaseOrder))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_with_receive_permission_can_partially_receive_approved_purchase_order(): void
    {
        $purchaseOrder = $this->createDraftPurchaseOrder([
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $product = $this->createProduct();
        $item = $this->createPurchaseOrderItem($purchaseOrder, $product, [
            'quantity' => 10,
            'unit_cost' => 10,
        ]);
        $user = $this->actingUserWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 4))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $purchaseOrder->refresh();
        $item->refresh();
        $stock = WarehouseStock::query()
            ->where('warehouse_id', $purchaseOrder->warehouse_id)
            ->where('product_id', $product->id)
            ->firstOrFail();
        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $purchaseOrder->status);
        $this->assertSame('4.000', $item->received_quantity);
        $this->assertSame('4.0000', $stock->quantity);
        $this->assertSame('0.0000', $stock->reserved_quantity);
        $this->assertSame('purchase_in', $movement->movement_type);
        $this->assertSame('4.0000', $movement->quantity);
        $this->assertSame('4.0000', $movement->balance_after);
        $this->assertSame(PurchaseOrder::class, $movement->reference_type);
        $this->assertSame($purchaseOrder->id, $movement->reference_id);
        $this->assertSame($user->id, $movement->created_by);
    }

    public function test_user_with_receive_permission_can_fully_receive_approved_purchase_order(): void
    {
        $purchaseOrder = $this->createDraftPurchaseOrder([
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $product = $this->createProduct();
        $item = $this->createPurchaseOrderItem($purchaseOrder, $product, [
            'quantity' => 10,
            'unit_cost' => 10,
        ]);
        $user = $this->actingUserWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 10))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $purchaseOrder->refresh();
        $stock = WarehouseStock::query()
            ->where('warehouse_id', $purchaseOrder->warehouse_id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $purchaseOrder->status);
        $this->assertSame($user->id, $purchaseOrder->received_by);
        $this->assertNotNull($purchaseOrder->received_at);
        $this->assertSame('10.000', $item->refresh()->received_quantity);
        $this->assertSame('10.0000', $stock->quantity);
        $this->assertSame(1, StockMovement::query()->where('movement_type', 'purchase_in')->count());
    }

    public function test_receiving_quantity_cannot_exceed_remaining_quantity(): void
    {
        $purchaseOrder = $this->createDraftPurchaseOrder([
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $product = $this->createProduct();
        $item = $this->createPurchaseOrderItem($purchaseOrder, $product, [
            'quantity' => 10,
            'received_quantity' => 7,
            'unit_cost' => 10,
        ]);
        $stock = WarehouseStock::query()->create([
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'product_id' => $product->id,
            'quantity' => 7,
            'reserved_quantity' => 0,
        ]);
        $user = $this->actingUserWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->from(route('purchase-orders.show', $purchaseOrder))
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 4))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('items');

        $this->assertSame('7.000', $item->refresh()->received_quantity);
        $this->assertSame('7.0000', $stock->refresh()->quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_receiving_all_zero_quantities_is_rejected(): void
    {
        $purchaseOrder = $this->createDraftPurchaseOrder([
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $item = $this->createPurchaseOrderItem($purchaseOrder, $this->createProduct(), [
            'quantity' => 10,
            'unit_cost' => 10,
        ]);
        $user = $this->actingUserWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->from(route('purchase-orders.show', $purchaseOrder))
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 0))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('items');

        $this->assertSame('0.000', $item->refresh()->received_quantity);
        $this->assertSame(0, WarehouseStock::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_draft_purchase_order_cannot_be_received(): void
    {
        $purchaseOrder = $this->createDraftPurchaseOrder();
        $item = $this->createPurchaseOrderItem($purchaseOrder, $this->createProduct(), [
            'quantity' => 10,
            'unit_cost' => 10,
        ]);
        $user = $this->actingUserWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->from(route('purchase-orders.show', $purchaseOrder))
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 5))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('status');

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->refresh()->status);
        $this->assertSame('0.000', $item->refresh()->received_quantity);
        $this->assertSame(0, WarehouseStock::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    private function seedPermissionsAndRoles(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function actingUserWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create();
        $permissions = Permission::query()
            ->whereIn('slug', $permissionSlugs)
            ->get();

        $this->assertCount(count($permissionSlugs), $permissions);

        $role = Role::query()->create([
            'name' => 'Test Role',
            'slug' => 'test-role-'.Str::uuid(),
            'description' => 'Temporary role for purchase order workflow tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSupplier(array $overrides = []): Supplier
    {
        $key = Str::lower(Str::random(8));

        return Supplier::query()->create(array_replace([
            'name' => 'Supplier '.$key,
            'company_name' => 'Supplier '.$key.' GmbH',
            'email' => 'supplier-'.$key.'@example.com',
            'phone' => '+49 100 200300',
            'address' => 'Berlin, Germany',
            'tax_number' => 'TAX-'.Str::upper($key),
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createWarehouse(array $overrides = []): Warehouse
    {
        $key = Str::upper(Str::random(8));

        return Warehouse::query()->create(array_replace([
            'code' => 'WH-'.$key,
            'name' => 'Warehouse '.$key,
            'contact_person' => 'Warehouse Manager',
            'phone' => '+49 111 222333',
            'email' => 'warehouse-'.$key.'@example.com',
            'address' => 'Munich, Germany',
            'city' => 'Munich',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProduct(array $overrides = []): Product
    {
        $key = Str::upper(Str::random(8));
        $category = Category::query()->create([
            'name' => 'Category '.$key,
            'slug' => 'category-'.Str::slug($key),
            'is_active' => true,
        ]);
        $unit = Unit::query()->create([
            'name' => 'Piece '.$key,
            'short_name' => 'pcs-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        return Product::query()->create(array_replace([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Product '.$key,
            'slug' => 'product-'.Str::slug($key),
            'sku' => 'SKU-'.$key,
            'description' => 'Test purchase order product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createDraftPurchaseOrder(array $overrides = []): PurchaseOrder
    {
        return PurchaseOrder::query()->create(array_replace([
            'po_number' => 'PO-TEST-'.Str::upper(Str::random(8)),
            'supplier_id' => $this->createSupplier()->id,
            'warehouse_id' => $this->createWarehouse()->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addWeek()->toDateString(),
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 50,
            'notes' => 'Test purchase order.',
            'created_by' => User::factory()->create()->id,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPurchaseOrderItem(
        PurchaseOrder $purchaseOrder,
        Product $product,
        array $overrides = [],
    ): PurchaseOrderItem {
        $quantity = (float) ($overrides['quantity'] ?? 5);
        $unitCost = (float) ($overrides['unit_cost'] ?? 10);

        return PurchaseOrderItem::query()->create(array_replace([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'received_quantity' => 0,
            'unit_cost' => $unitCost,
            'line_total' => round($quantity * $unitCost, 2),
            'notes' => 'Test purchase order item.',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPurchaseOrderPayload(array $overrides = []): array
    {
        $product = $this->createProduct();

        return array_replace([
            'supplier_id' => $this->createSupplier()->id,
            'warehouse_id' => $this->createWarehouse()->id,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(5)->toDateString(),
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'notes' => 'Test purchase order payload.',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_cost' => 10,
                    'notes' => 'Payload item.',
                ],
            ],
        ], $overrides);
    }

    /**
     * @return array{items: list<array{purchase_order_item_id: int, received_quantity: string|float|int}>}
     */
    private function validReceivePayload(PurchaseOrderItem $item, string|float|int $quantity): array
    {
        return [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'received_quantity' => $quantity,
                ],
            ],
        ];
    }
}
