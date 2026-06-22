<?php

namespace Tests\Feature\Reports;

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

class PurchaseOrderReportTest extends TestCase
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

    public function test_user_with_purchase_order_report_permission_can_see_report_data(): void
    {
        $user = $this->userWithPermissions(['reports.purchase-orders.view']);
        $supplier = $this->supplier(['name' => 'Report Supplier']);
        $warehouse = $this->warehouse([
            'code' => 'PO-RPT-WH-001',
            'name' => 'Report PO Warehouse',
        ]);
        $createdBy = User::factory()->create(['name' => 'PO Creator']);
        $approvedBy = User::factory()->create(['name' => 'PO Approver']);
        $purchaseOrder = $this->purchaseOrder($supplier, $warehouse, [
            'po_number' => 'PO-RPT-001',
            'status' => PurchaseOrder::STATUS_APPROVED,
            'order_date' => '2026-06-15',
            'expected_date' => '2026-06-20',
            'total_amount' => 125,
            'notes' => 'Report purchase order note.',
            'created_by' => $createdBy->id,
            'approved_by' => $approvedBy->id,
            'approved_at' => '2026-06-16 09:00:00',
        ]);
        $this->purchaseOrderItem($purchaseOrder, $this->product(), [
            'quantity' => 10,
            'received_quantity' => 4,
            'unit_cost' => 10,
            'line_total' => 100,
        ]);
        $this->purchaseOrderItem($purchaseOrder, $this->product(), [
            'quantity' => 2,
            'received_quantity' => 2,
            'unit_cost' => 12.5,
            'line_total' => 25,
        ]);

        $this->actingAs($user)
            ->get(route('reports.purchase-orders'))
            ->assertOk()
            ->assertSeeText('Purchase Order Report')
            ->assertSeeText('PO-RPT-001')
            ->assertSeeText('Report Supplier')
            ->assertSeeText('Report PO Warehouse')
            ->assertSeeText('PO-RPT-WH-001')
            ->assertSeeText('Approved')
            ->assertSeeText('Jun 15, 2026')
            ->assertSeeText('Jun 20, 2026')
            ->assertSeeText('12.000')
            ->assertSeeText('6.000')
            ->assertSeeText('6.000')
            ->assertSeeText('125.00')
            ->assertSeeText('PO Creator')
            ->assertSeeText('PO Approver')
            ->assertSeeText('Report purchase order note.')
            ->assertViewHas('purchaseOrderRows', function ($purchaseOrderRows) use ($purchaseOrder): bool {
                return $purchaseOrderRows->count() === 1
                    && $purchaseOrderRows->getCollection()->first()->is($purchaseOrder);
            });
    }

    public function test_purchase_order_report_supports_supplier_filter(): void
    {
        $user = $this->userWithPermissions(['reports.purchase-orders.view']);
        $matchingSupplier = $this->supplier(['name' => 'Matching Supplier']);
        $otherSupplier = $this->supplier(['name' => 'Other Supplier']);
        $matchingPurchaseOrder = $this->purchaseOrder($matchingSupplier, $this->warehouse(), [
            'po_number' => 'PO-SUPPLIER-001',
        ]);
        $this->purchaseOrder($otherSupplier, $this->warehouse(), [
            'po_number' => 'PO-SUPPLIER-002',
        ]);

        $this->actingAs($user)
            ->get(route('reports.purchase-orders', ['supplier_id' => $matchingSupplier->id]))
            ->assertOk()
            ->assertSeeText('PO-SUPPLIER-001')
            ->assertViewHas('purchaseOrderRows', function ($purchaseOrderRows) use ($matchingPurchaseOrder): bool {
                return $purchaseOrderRows->count() === 1
                    && $purchaseOrderRows->getCollection()->first()->is($matchingPurchaseOrder);
            });
    }

    public function test_purchase_order_report_supports_warehouse_filter(): void
    {
        $user = $this->userWithPermissions(['reports.purchase-orders.view']);
        $supplier = $this->supplier();
        $matchingWarehouse = $this->warehouse([
            'code' => 'PO-WH-FILTER-001',
            'name' => 'Matching PO Warehouse',
        ]);
        $otherWarehouse = $this->warehouse([
            'code' => 'PO-WH-FILTER-002',
            'name' => 'Other PO Warehouse',
        ]);
        $matchingPurchaseOrder = $this->purchaseOrder($supplier, $matchingWarehouse, [
            'po_number' => 'PO-WAREHOUSE-001',
        ]);
        $this->purchaseOrder($supplier, $otherWarehouse, [
            'po_number' => 'PO-WAREHOUSE-002',
        ]);

        $this->actingAs($user)
            ->get(route('reports.purchase-orders', ['warehouse_id' => $matchingWarehouse->id]))
            ->assertOk()
            ->assertSeeText('PO-WAREHOUSE-001')
            ->assertViewHas('purchaseOrderRows', function ($purchaseOrderRows) use ($matchingPurchaseOrder): bool {
                return $purchaseOrderRows->count() === 1
                    && $purchaseOrderRows->getCollection()->first()->is($matchingPurchaseOrder);
            });
    }

    public function test_purchase_order_report_supports_status_filter(): void
    {
        $user = $this->userWithPermissions(['reports.purchase-orders.view']);
        $supplier = $this->supplier();
        $warehouse = $this->warehouse();
        $matchingPurchaseOrder = $this->purchaseOrder($supplier, $warehouse, [
            'po_number' => 'PO-STATUS-001',
            'status' => PurchaseOrder::STATUS_RECEIVED,
        ]);
        $this->purchaseOrder($supplier, $warehouse, [
            'po_number' => 'PO-STATUS-002',
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);

        $this->actingAs($user)
            ->get(route('reports.purchase-orders', ['status' => PurchaseOrder::STATUS_RECEIVED]))
            ->assertOk()
            ->assertSeeText('PO-STATUS-001')
            ->assertSeeText('Received')
            ->assertViewHas('purchaseOrderRows', function ($purchaseOrderRows) use ($matchingPurchaseOrder): bool {
                return $purchaseOrderRows->count() === 1
                    && $purchaseOrderRows->getCollection()->first()->is($matchingPurchaseOrder);
            });
    }

    public function test_purchase_order_report_supports_date_range_filter(): void
    {
        $user = $this->userWithPermissions(['reports.purchase-orders.view']);
        $supplier = $this->supplier();
        $warehouse = $this->warehouse();
        $matchingPurchaseOrder = $this->purchaseOrder($supplier, $warehouse, [
            'po_number' => 'PO-DATE-001',
            'order_date' => '2026-06-15',
        ]);
        $this->purchaseOrder($supplier, $warehouse, [
            'po_number' => 'PO-DATE-002',
            'order_date' => '2026-06-18',
        ]);

        $this->actingAs($user)
            ->get(route('reports.purchase-orders', [
                'date_from' => '2026-06-15',
                'date_to' => '2026-06-15',
            ]))
            ->assertOk()
            ->assertSeeText('PO-DATE-001')
            ->assertViewHas('purchaseOrderRows', function ($purchaseOrderRows) use ($matchingPurchaseOrder): bool {
                return $purchaseOrderRows->count() === 1
                    && $purchaseOrderRows->getCollection()->first()->is($matchingPurchaseOrder);
            });
    }

    public function test_purchase_order_report_shows_ordered_received_and_pending_quantity_summary(): void
    {
        $user = $this->userWithPermissions(['reports.purchase-orders.view']);
        $purchaseOrder = $this->purchaseOrder($this->supplier(), $this->warehouse(), [
            'po_number' => 'PO-QTY-001',
        ]);
        $this->purchaseOrderItem($purchaseOrder, $this->product(), [
            'quantity' => 7,
            'received_quantity' => 3,
            'unit_cost' => 10,
            'line_total' => 70,
        ]);
        $this->purchaseOrderItem($purchaseOrder, $this->product(), [
            'quantity' => 5,
            'received_quantity' => 2,
            'unit_cost' => 10,
            'line_total' => 50,
        ]);

        $this->actingAs($user)
            ->get(route('reports.purchase-orders'))
            ->assertOk()
            ->assertSeeText('12.000')
            ->assertSeeText('5.000')
            ->assertSeeText('7.000')
            ->assertViewHas('purchaseOrderRows', function ($purchaseOrderRows): bool {
                $row = $purchaseOrderRows->getCollection()->first();

                return (float) $row->ordered_quantity === 12.0
                    && (float) $row->received_quantity === 5.0
                    && (float) $row->pending_quantity === 7.0;
            });
    }

    public function test_purchase_order_report_rejects_invalid_status_filter(): void
    {
        $user = $this->userWithPermissions(['reports.purchase-orders.view']);

        $this->actingAs($user)
            ->from(route('reports.purchase-orders'))
            ->get(route('reports.purchase-orders', ['status' => 'invalid-status']))
            ->assertRedirect(route('reports.purchase-orders'))
            ->assertSessionHasErrors('status');
    }

    public function test_purchase_order_report_rejects_date_to_before_date_from(): void
    {
        $user = $this->userWithPermissions(['reports.purchase-orders.view']);

        $this->actingAs($user)
            ->from(route('reports.purchase-orders'))
            ->get(route('reports.purchase-orders', [
                'date_from' => '2026-06-20',
                'date_to' => '2026-06-15',
            ]))
            ->assertRedirect(route('reports.purchase-orders'))
            ->assertSessionHasErrors('date_to');
    }

    public function test_user_without_purchase_order_report_permission_receives_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.purchase-orders'))
            ->assertForbidden();
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
            'description' => 'Temporary role for purchase order report tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
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
            'email' => 'warehouse-'.Str::lower($key).'@example.com',
            'address' => 'Berlin, Germany',
            'city' => 'Berlin',
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
            'description' => 'Purchase order report test product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function purchaseOrder(Supplier $supplier, Warehouse $warehouse, array $overrides = []): PurchaseOrder
    {
        return PurchaseOrder::query()->create(array_replace([
            'po_number' => 'PO-RPT-'.Str::upper(Str::random(8)),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'order_date' => '2026-06-15',
            'expected_date' => '2026-06-22',
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 50,
            'notes' => 'Purchase order report test.',
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
            'notes' => 'Purchase order report item.',
        ], $overrides));
    }
}
