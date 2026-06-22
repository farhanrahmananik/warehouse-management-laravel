<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
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

class PurchaseOrderReceiveAuditLogTest extends TestCase
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

    public function test_partially_receiving_an_approved_purchase_order_creates_exactly_one_audit_log(): void
    {
        $warehouse = $this->warehouse('Receiving Warehouse');
        $product = $this->product('Partial Receive Product', 'PO-RECV-PARTIAL-001');
        $purchaseOrder = $this->purchaseOrder([
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $item = $this->purchaseOrderItem($purchaseOrder, $product, [
            'quantity' => 10,
        ]);
        $user = $this->userWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 4, 'First receipt.'))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $purchaseOrder->refresh();
        $movement = StockMovement::query()->firstOrFail();
        $auditLog = $this->latestReceiveAuditLog();

        $this->assertSame(1, AuditLog::query()->where('module', 'purchase_orders')->where('event', 'received')->count());
        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($purchaseOrder->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($purchaseOrder->id, $auditLog->auditable_id);
        $this->assertSame(sprintf('Purchase order "%s" was received.', $purchaseOrder->po_number), $auditLog->description);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $auditLog->old_values['status']);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $auditLog->new_values['status']);
        $this->assertSame('4.000', $auditLog->new_values['total_received_now']);
        $this->assertSame('First receipt.', $auditLog->new_values['receiving_notes']);
        $this->assertSame($item->id, $auditLog->old_values['items'][0]['purchase_order_item_id']);
        $this->assertSame('0.000', $auditLog->old_values['items'][0]['received_quantity_before']);
        $this->assertSame('4.000', $auditLog->new_values['items'][0]['received_now']);
        $this->assertSame('4.000', $auditLog->new_values['items'][0]['received_quantity_after']);
        $this->assertSame([
            'model' => 'purchase_order',
            'purchase_order_id' => $purchaseOrder->id,
            'movement_ids' => [$movement->id],
        ], $auditLog->metadata);
    }

    public function test_fully_receiving_an_approved_purchase_order_creates_exactly_one_audit_log(): void
    {
        $warehouse = $this->warehouse('Full Receive Warehouse');
        $product = $this->product('Full Receive Product', 'PO-RECV-FULL-001');
        $purchaseOrder = $this->purchaseOrder([
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $item = $this->purchaseOrderItem($purchaseOrder, $product, [
            'quantity' => 10,
        ]);
        $user = $this->userWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 10))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $purchaseOrder->refresh();
        $auditLog = $this->latestReceiveAuditLog();

        $this->assertSame(1, AuditLog::query()->where('module', 'purchase_orders')->where('event', 'received')->count());
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $auditLog->old_values['status']);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $auditLog->new_values['status']);
        $this->assertSame($user->id, $auditLog->new_values['received_by']);
        $this->assertNotEmpty($auditLog->new_values['received_at']);
        $this->assertSame('10.000', $auditLog->new_values['total_received_now']);
        $this->assertSame('Full Receive Product', $auditLog->new_values['items'][0]['product_name']);
        $this->assertSame('10.000', $auditLog->new_values['items'][0]['ordered_quantity']);
        $this->assertSame('10.000', $auditLog->new_values['items'][0]['received_now']);
        $this->assertSame('10.000', $auditLog->new_values['items'][0]['received_quantity_after']);
    }

    public function test_second_receive_on_partially_received_purchase_order_creates_another_audit_log(): void
    {
        $warehouse = $this->warehouse('Second Receive Warehouse');
        $product = $this->product('Second Receive Product', 'PO-RECV-SECOND-001');
        $purchaseOrder = $this->purchaseOrder([
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $item = $this->purchaseOrderItem($purchaseOrder, $product, [
            'quantity' => 10,
        ]);
        $user = $this->userWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 4))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $this->actingAs($user)
            ->post(route('purchase-orders.receive', $purchaseOrder->refresh()), $this->validReceivePayload($item->refresh(), 6))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $auditLog = $this->latestReceiveAuditLog();

        $this->assertSame(2, AuditLog::query()->where('module', 'purchase_orders')->where('event', 'received')->count());
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $auditLog->old_values['status']);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $auditLog->new_values['status']);
        $this->assertSame('4.000', $auditLog->old_values['items'][0]['received_quantity_before']);
        $this->assertSame('6.000', $auditLog->new_values['items'][0]['received_now']);
        $this->assertSame('10.000', $auditLog->new_values['items'][0]['received_quantity_after']);
        $this->assertSame('6.000', $auditLog->new_values['total_received_now']);
    }

    public function test_failed_receive_because_quantity_exceeds_remaining_quantity_does_not_create_audit_log(): void
    {
        $warehouse = $this->warehouse('Exceeded Receive Warehouse');
        $product = $this->product('Exceeded Receive Product', 'PO-RECV-EXCEED-001');
        $purchaseOrder = $this->purchaseOrder([
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $item = $this->purchaseOrderItem($purchaseOrder, $product, [
            'quantity' => 10,
            'received_quantity' => 7,
        ]);
        $stock = WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 7,
            'reserved_quantity' => 0,
        ]);
        $user = $this->userWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->from(route('purchase-orders.show', $purchaseOrder))
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 4))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('items');

        $this->assertSame('7.000', $item->refresh()->received_quantity);
        $this->assertSame('7.0000', $stock->refresh()->quantity);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'purchase_orders')->where('event', 'received')->count());
    }

    public function test_failed_receive_with_all_zero_quantities_does_not_create_audit_log(): void
    {
        $purchaseOrder = $this->purchaseOrder([
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $item = $this->purchaseOrderItem($purchaseOrder, $this->product(), [
            'quantity' => 10,
        ]);
        $user = $this->userWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->from(route('purchase-orders.show', $purchaseOrder))
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 0))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('items');

        $this->assertSame('0.000', $item->refresh()->received_quantity);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'purchase_orders')->where('event', 'received')->count());
    }

    public function test_failed_receive_on_draft_purchase_order_does_not_create_audit_log(): void
    {
        $purchaseOrder = $this->purchaseOrder([
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        $item = $this->purchaseOrderItem($purchaseOrder, $this->product(), [
            'quantity' => 10,
        ]);
        $user = $this->userWithPermissions(['purchase-orders.receive']);

        $this->actingAs($user)
            ->from(route('purchase-orders.show', $purchaseOrder))
            ->post(route('purchase-orders.receive', $purchaseOrder), $this->validReceivePayload($item, 5))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('status');

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->refresh()->status);
        $this->assertSame('0.000', $item->refresh()->received_quantity);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'purchase_orders')->where('event', 'received')->count());
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
            'description' => 'Temporary role for purchase order receive audit log tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function latestReceiveAuditLog(): AuditLog
    {
        return AuditLog::query()
            ->where('module', 'purchase_orders')
            ->where('event', 'received')
            ->latest('id')
            ->firstOrFail();
    }

    private function supplier(): Supplier
    {
        $key = Str::lower(Str::random(8));

        return Supplier::query()->create([
            'name' => 'Supplier '.$key,
            'company_name' => 'Supplier '.$key.' GmbH',
            'email' => 'supplier-'.$key.'@example.com',
            'phone' => '+49 100 200300',
            'address' => 'Berlin, Germany',
            'tax_number' => 'TAX-'.Str::upper($key),
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
        ]);
    }

    private function warehouse(string $name = 'Test Warehouse'): Warehouse
    {
        $key = Str::upper(Str::random(8));

        return Warehouse::query()->create([
            'code' => 'WH-'.$key,
            'name' => $name,
            'contact_person' => 'Warehouse Manager',
            'phone' => '+49 111 222333',
            'email' => 'warehouse-'.$key.'@example.com',
            'address' => 'Munich, Germany',
            'city' => 'Munich',
            'is_active' => true,
        ]);
    }

    private function product(string $name = 'Test Product', ?string $sku = null): Product
    {
        $sku ??= 'SKU-'.Str::upper(Str::random(8));
        $category = Category::query()->create([
            'name' => 'Category '.$sku,
            'slug' => 'category-'.Str::slug($sku),
            'is_active' => true,
        ]);
        $unit = Unit::query()->create([
            'name' => 'Piece '.$sku,
            'short_name' => 'pcs-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => $name,
            'slug' => Str::slug($name.' '.$sku),
            'sku' => $sku,
            'description' => 'Test purchase order receive audit product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function purchaseOrder(array $overrides = []): PurchaseOrder
    {
        return PurchaseOrder::query()->create(array_replace([
            'po_number' => 'PO-RECEIVE-AUDIT-'.Str::upper(Str::random(8)),
            'supplier_id' => $this->supplier()->id,
            'warehouse_id' => $this->warehouse()->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'order_date' => '2026-06-22',
            'expected_date' => '2026-06-27',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 100,
            'notes' => 'Receive audit purchase order.',
            'created_by' => User::factory()->create()->id,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function purchaseOrderItem(
        PurchaseOrder $purchaseOrder,
        Product $product,
        array $overrides = [],
    ): PurchaseOrderItem {
        $quantity = (float) ($overrides['quantity'] ?? 10);
        $unitCost = (float) ($overrides['unit_cost'] ?? 10);

        return PurchaseOrderItem::query()->create(array_replace([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'received_quantity' => 0,
            'unit_cost' => $unitCost,
            'line_total' => round($quantity * $unitCost, 2),
            'notes' => 'Receive audit item.',
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function validReceivePayload(
        PurchaseOrderItem $item,
        string|float|int $quantity,
        ?string $notes = null,
    ): array {
        $payload = [
            'items' => [
                [
                    'purchase_order_item_id' => $item->id,
                    'received_quantity' => $quantity,
                ],
            ],
        ];

        if ($notes !== null) {
            $payload['notes'] = $notes;
        }

        return $payload;
    }
}
