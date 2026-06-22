<?php

namespace Tests\Feature\Dashboard;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockIn;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\StockTransfer;
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

class DashboardTest extends TestCase
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

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_dashboard_view_permission_receives_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_user_with_dashboard_view_permission_can_access_dashboard(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Warehouse Management Dashboard')
            ->assertSeeText('Products')
            ->assertSeeText('Categories')
            ->assertSeeText('Suppliers')
            ->assertSeeText('Warehouses')
            ->assertSeeText('Purchase Orders')
            ->assertSeeText('Stock Workflows')
            ->assertSeeText('Low Stock Products')
            ->assertSeeText('Recent Stock Movements');
    }

    public function test_dashboard_displays_aggregate_data_from_database_records(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);
        $supplier = $this->supplier(['name' => 'Dashboard Supplier']);
        $warehouse = $this->warehouse(['code' => 'DASH-WH-01', 'name' => 'Dashboard Warehouse']);
        $destinationWarehouse = $this->warehouse(['code' => 'DASH-WH-02', 'name' => 'Dashboard Destination']);
        $product = $this->product([
            'name' => 'Dashboard Product',
            'sku' => 'DASH-PRODUCT-001',
            'reorder_level' => 30,
        ]);

        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 25,
            'reserved_quantity' => 5,
        ]);

        $this->purchaseOrder($supplier, $warehouse, [
            'po_number' => 'PO-DASH-DRAFT-001',
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        $this->purchaseOrder($supplier, $warehouse, [
            'po_number' => 'PO-DASH-APPROVED-001',
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);

        StockIn::query()->create([
            'document_no' => 'SI-DASH-0001',
            'warehouse_id' => $warehouse->id,
            'stock_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);
        StockOut::query()->create([
            'document_no' => 'SO-DASH-0001',
            'warehouse_id' => $warehouse->id,
            'stock_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);
        StockTransfer::query()->create([
            'document_no' => 'ST-DASH-0001',
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $destinationWarehouse->id,
            'transfer_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        StockMovement::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'stock_in',
            'quantity' => 25,
            'balance_after' => 25,
            'remarks' => 'Dashboard aggregate movement.',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('25.0000')
            ->assertSeeText('5.0000')
            ->assertSeeText('20.0000')
            ->assertSeeText('Draft')
            ->assertSeeText('Approved')
            ->assertSeeTextInOrder(['Draft', '1', 'Approved', '1'])
            ->assertSeeText('Stock In')
            ->assertSeeText('Stock Out')
            ->assertSeeText('Stock Transfer')
            ->assertSeeTextInOrder(['Stock Workflows', 'Stock In', '1', 'Stock Out', '1', 'Stock Transfer', '1'])
            ->assertSeeText('Dashboard Product')
            ->assertSeeText('DASH-PRODUCT-001')
            ->assertSeeText('Dashboard Warehouse')
            ->assertSeeText('DASH-WH-01');
    }

    public function test_low_stock_products_are_displayed(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);
        $warehouse = $this->warehouse();
        $product = $this->product([
            'name' => 'Low Stock Widget',
            'sku' => 'LOW-STOCK-001',
            'reorder_level' => 10,
        ]);

        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'reserved_quantity' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Low Stock Widget')
            ->assertSeeText('LOW-STOCK-001')
            ->assertSeeText('3.0000')
            ->assertSeeText('1.0000')
            ->assertSeeText('2.0000')
            ->assertSeeText('10.00');
    }

    public function test_recent_stock_movements_are_displayed(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);
        $creator = User::factory()->create([
            'name' => 'Movement Creator',
        ]);
        $warehouse = $this->warehouse([
            'code' => 'MOVE-WH-01',
            'name' => 'Movement Warehouse',
        ]);
        $product = $this->product([
            'name' => 'Movement Product',
            'sku' => 'MOVE-PRODUCT-001',
        ]);

        StockMovement::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => 'stock_out',
            'quantity' => 4,
            'balance_after' => 6,
            'remarks' => 'Movement dashboard test.',
            'created_by' => $creator->id,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Movement Product')
            ->assertSeeText('MOVE-PRODUCT-001')
            ->assertSeeText('Movement Warehouse')
            ->assertSeeText('MOVE-WH-01')
            ->assertSeeText('Stock Out')
            ->assertSeeText('4.0000')
            ->assertSeeText('Movement Creator');
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
            'description' => 'Temporary role for dashboard tests.',
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
            'description' => 'Dashboard test product.',
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
            'po_number' => 'PO-DASH-'.Str::upper(Str::random(8)),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addWeek()->toDateString(),
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 50,
            'notes' => 'Dashboard test purchase order.',
            'created_by' => User::factory()->create()->id,
        ], $overrides));
    }
}
