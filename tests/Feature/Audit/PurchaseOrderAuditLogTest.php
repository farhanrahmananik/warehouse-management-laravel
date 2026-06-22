<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrderAuditLogTest extends TestCase
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

    public function test_creating_a_draft_purchase_order_creates_audit_log(): void
    {
        $supplier = $this->supplier(['name' => 'Audit Supplier']);
        $warehouse = $this->warehouse(['name' => 'Audit Warehouse']);
        $product = $this->product(['name' => 'Audit Product', 'slug' => 'audit-product', 'sku' => 'AUDIT-PO-001']);
        $user = $this->userWithPermissions(['purchase-orders.create']);

        $response = $this->actingAs($user)
            ->post(route('purchase-orders.store'), $this->validPurchaseOrderPayload(
                supplier: $supplier,
                warehouse: $warehouse,
                product: $product,
                overrides: [
                    'discount_amount' => 2,
                    'tax_amount' => 1,
                    'shipping_amount' => 3,
                ],
            ));

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();
        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $auditLog = $this->latestAuditLog('created');

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($purchaseOrder->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($purchaseOrder->id, $auditLog->auditable_id);
        $this->assertSame(sprintf('Purchase order "%s" was created.', $purchaseOrder->po_number), $auditLog->description);
        $this->assertSame($purchaseOrder->id, $auditLog->new_values['purchase_order_id']);
        $this->assertSame($purchaseOrder->po_number, $auditLog->new_values['reference_no']);
        $this->assertSame('Audit Supplier', $auditLog->new_values['supplier_name']);
        $this->assertSame('Audit Warehouse', $auditLog->new_values['warehouse_name']);
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $auditLog->new_values['status']);
        $this->assertSame('52.00', $auditLog->new_values['total_amount']);
        $this->assertSame(1, $auditLog->new_values['total_items']);
        $this->assertSame('5.000', $auditLog->new_values['total_quantity']);
        $this->assertSame('Audit Product', $auditLog->new_values['items'][0]['product_name']);
        $this->assertSame('5.000', $auditLog->new_values['items'][0]['quantity']);
        $this->assertSame('10.00', $auditLog->new_values['items'][0]['unit_price']);
        $this->assertSame([
            'model' => 'purchase_order',
            'purchase_order_id' => $purchaseOrder->id,
        ], $auditLog->metadata);
    }

    public function test_updating_a_draft_purchase_order_creates_audit_log_with_changed_values_only(): void
    {
        $supplier = $this->supplier();
        $warehouse = $this->warehouse();
        $product = $this->product();
        $purchaseOrder = $this->purchaseOrder([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'notes' => 'Original PO notes.',
        ]);
        $this->purchaseOrderItem($purchaseOrder, $product);
        $user = $this->userWithPermissions(['purchase-orders.update']);

        $this->actingAs($user)
            ->put(route('purchase-orders.update', $purchaseOrder), $this->validPurchaseOrderPayload(
                supplier: $supplier,
                warehouse: $warehouse,
                product: $product,
                overrides: [
                    'notes' => 'Updated PO notes.',
                ],
            ))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $auditLog = $this->latestAuditLog('updated');

        $this->assertSame($purchaseOrder->id, $auditLog->auditable_id);
        $this->assertSame(sprintf('Purchase order "%s" was updated.', $purchaseOrder->po_number), $auditLog->description);
        $this->assertSame([
            'notes' => 'Original PO notes.',
        ], $auditLog->old_values);
        $this->assertSame([
            'notes' => 'Updated PO notes.',
        ], $auditLog->new_values);
    }

    public function test_deleting_a_draft_purchase_order_creates_audit_log(): void
    {
        $purchaseOrder = $this->purchaseOrder();
        $this->purchaseOrderItem($purchaseOrder, $this->product());
        $user = $this->userWithPermissions(['purchase-orders.delete']);

        $this->actingAs($user)
            ->delete(route('purchase-orders.destroy', $purchaseOrder))
            ->assertRedirect(route('purchase-orders.index'));

        $auditLog = $this->latestAuditLog('deleted');

        $this->assertSame($purchaseOrder->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($purchaseOrder->id, $auditLog->auditable_id);
        $this->assertSame(sprintf('Purchase order "%s" was deleted.', $purchaseOrder->po_number), $auditLog->description);
        $this->assertSame($purchaseOrder->id, $auditLog->old_values['purchase_order_id']);
        $this->assertSame($purchaseOrder->po_number, $auditLog->old_values['reference_no']);
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $auditLog->old_values['status']);
        $this->assertSame(1, $auditLog->old_values['total_items']);
    }

    public function test_approving_a_draft_purchase_order_creates_audit_log(): void
    {
        $purchaseOrder = $this->purchaseOrder();
        $this->purchaseOrderItem($purchaseOrder, $this->product());
        $user = $this->userWithPermissions(['purchase-orders.approve']);

        $this->actingAs($user)
            ->post(route('purchase-orders.approve', $purchaseOrder))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $auditLog = $this->latestAuditLog('approved');

        $this->assertSame($purchaseOrder->id, $auditLog->auditable_id);
        $this->assertSame(sprintf('Purchase order "%s" was approved.', $purchaseOrder->po_number), $auditLog->description);
        $this->assertSame([
            'status' => PurchaseOrder::STATUS_DRAFT,
        ], $auditLog->old_values);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $auditLog->new_values['status']);
        $this->assertSame($user->id, $auditLog->new_values['approved_by']);
        $this->assertNotEmpty($auditLog->new_values['approved_at']);
    }

    public function test_cancelling_an_approved_purchase_order_creates_audit_log(): void
    {
        $purchaseOrder = $this->purchaseOrder([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'notes' => 'Original approved notes.',
        ]);
        $this->purchaseOrderItem($purchaseOrder, $this->product());
        $user = $this->userWithPermissions(['purchase-orders.delete']);

        $this->actingAs($user)
            ->post(route('purchase-orders.cancel', $purchaseOrder), [
                'notes' => 'No longer needed.',
            ])
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder));

        $auditLog = $this->latestAuditLog('cancelled');

        $this->assertSame($purchaseOrder->id, $auditLog->auditable_id);
        $this->assertSame(sprintf('Purchase order "%s" was cancelled.', $purchaseOrder->po_number), $auditLog->description);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $auditLog->old_values['status']);
        $this->assertSame('Original approved notes.', $auditLog->old_values['notes']);
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $auditLog->new_values['status']);
        $this->assertSame($user->id, $auditLog->new_values['cancelled_by']);
        $this->assertNotEmpty($auditLog->new_values['cancelled_at']);
        $this->assertStringContainsString('No longer needed.', $auditLog->new_values['notes']);
    }

    public function test_failed_purchase_order_validation_does_not_create_audit_log(): void
    {
        $user = $this->userWithPermissions(['purchase-orders.create']);

        $this->actingAs($user)
            ->post(route('purchase-orders.store'), [])
            ->assertSessionHasErrors([
                'supplier_id',
                'warehouse_id',
                'order_date',
                'items',
            ]);

        $this->assertSame(0, PurchaseOrder::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'purchase_orders')->count());
    }

    public function test_approved_purchase_order_update_rejection_does_not_create_audit_log(): void
    {
        $supplier = $this->supplier();
        $warehouse = $this->warehouse();
        $product = $this->product();
        $purchaseOrder = $this->purchaseOrder([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'notes' => 'Original notes.',
        ]);
        $this->purchaseOrderItem($purchaseOrder, $product);
        $user = $this->userWithPermissions(['purchase-orders.update']);

        $this->actingAs($user)
            ->from(route('purchase-orders.edit', $purchaseOrder))
            ->put(route('purchase-orders.update', $purchaseOrder), $this->validPurchaseOrderPayload(
                supplier: $supplier,
                warehouse: $warehouse,
                product: $product,
                overrides: [
                    'notes' => 'Rejected notes.',
                ],
            ))
            ->assertRedirect(route('purchase-orders.edit', $purchaseOrder))
            ->assertSessionHasErrors('status');

        $this->assertSame(0, AuditLog::query()->where('module', 'purchase_orders')->count());
    }

    public function test_approved_purchase_order_delete_rejection_does_not_create_audit_log(): void
    {
        $purchaseOrder = $this->purchaseOrder([
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $this->purchaseOrderItem($purchaseOrder, $this->product());
        $user = $this->userWithPermissions(['purchase-orders.delete']);

        $this->actingAs($user)
            ->from(route('purchase-orders.show', $purchaseOrder))
            ->delete(route('purchase-orders.destroy', $purchaseOrder))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('status');

        $this->assertSame(0, AuditLog::query()->where('module', 'purchase_orders')->count());
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
            'description' => 'Temporary role for purchase order audit log tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function latestAuditLog(string $event): AuditLog
    {
        return AuditLog::query()
            ->where('module', 'purchase_orders')
            ->where('event', $event)
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function supplier(array $overrides = []): Supplier
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
    private function warehouse(array $overrides = []): Warehouse
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
    private function product(array $overrides = []): Product
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
            'description' => 'Test purchase order audit product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function purchaseOrder(array $overrides = []): PurchaseOrder
    {
        return PurchaseOrder::query()->create(array_replace([
            'po_number' => 'PO-AUDIT-'.Str::upper(Str::random(8)),
            'supplier_id' => $this->supplier()->id,
            'warehouse_id' => $this->warehouse()->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'order_date' => '2026-06-22',
            'expected_date' => '2026-06-27',
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 50,
            'notes' => 'Original PO notes.',
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
        $quantity = (float) ($overrides['quantity'] ?? 5);
        $unitCost = (float) ($overrides['unit_cost'] ?? 10);

        return PurchaseOrderItem::query()->create(array_replace([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'received_quantity' => 0,
            'unit_cost' => $unitCost,
            'line_total' => round($quantity * $unitCost, 2),
            'notes' => 'Payload item.',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPurchaseOrderPayload(
        Supplier $supplier,
        Warehouse $warehouse,
        Product $product,
        array $overrides = [],
    ): array {
        return array_replace([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => '2026-06-22',
            'expected_date' => '2026-06-27',
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'notes' => 'Original PO notes.',
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
}
