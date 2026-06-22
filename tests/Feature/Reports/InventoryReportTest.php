<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
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

class InventoryReportTest extends TestCase
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

    public function test_user_with_inventory_report_permission_can_see_inventory_report_data(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view']);
        $category = $this->category(['name' => 'Report Category', 'slug' => 'report-category']);
        $unit = $this->unit(['name' => 'Piece', 'short_name' => 'pcs']);
        $warehouse = $this->warehouse([
            'code' => 'RPT-WH-001',
            'name' => 'Report Warehouse',
        ]);
        $product = $this->product($category, $unit, [
            'name' => 'Report Product',
            'slug' => 'report-product',
            'sku' => 'REPORT-001',
            'reorder_level' => 5,
        ]);
        $stock = $this->warehouseStock($warehouse, $product, quantity: 12, reservedQuantity: 2);

        $this->actingAs($user)
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSeeText('Inventory Report')
            ->assertSeeText('Report Warehouse')
            ->assertSeeText('RPT-WH-001')
            ->assertSeeText('Report Product')
            ->assertSeeText('REPORT-001')
            ->assertSeeText('Report Category')
            ->assertSeeText('pcs')
            ->assertSeeText('12.0000')
            ->assertSeeText('2.0000')
            ->assertSeeText('10.0000')
            ->assertSeeText('5.00')
            ->assertSeeText('In Stock')
            ->assertViewHas('inventoryRows', function ($inventoryRows) use ($stock): bool {
                return $inventoryRows->count() === 1
                    && $inventoryRows->getCollection()->first()->is($stock);
            });
    }

    public function test_inventory_report_supports_warehouse_filter(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view']);
        $category = $this->category();
        $unit = $this->unit();
        $matchingWarehouse = $this->warehouse([
            'code' => 'FILTER-WH-001',
            'name' => 'Matching Warehouse',
        ]);
        $otherWarehouse = $this->warehouse([
            'code' => 'FILTER-WH-002',
            'name' => 'Other Warehouse',
        ]);
        $product = $this->product($category, $unit, [
            'name' => 'Warehouse Filter Product',
            'slug' => 'warehouse-filter-product',
            'sku' => 'WAREHOUSE-FILTER-001',
        ]);
        $matchingStock = $this->warehouseStock($matchingWarehouse, $product);
        $this->warehouseStock($otherWarehouse, $product);

        $this->actingAs($user)
            ->get(route('reports.inventory', ['warehouse_id' => $matchingWarehouse->id]))
            ->assertOk()
            ->assertSeeText('Matching Warehouse')
            ->assertViewHas('inventoryRows', function ($inventoryRows) use ($matchingStock): bool {
                return $inventoryRows->count() === 1
                    && $inventoryRows->getCollection()->first()->is($matchingStock);
            });
    }

    public function test_inventory_report_supports_product_filter(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view']);
        $category = $this->category();
        $unit = $this->unit();
        $warehouse = $this->warehouse();
        $matchingProduct = $this->product($category, $unit, [
            'name' => 'Matching Product',
            'slug' => 'matching-product',
            'sku' => 'MATCHING-PRODUCT-001',
        ]);
        $otherProduct = $this->product($category, $unit, [
            'name' => 'Other Product',
            'slug' => 'other-product',
            'sku' => 'OTHER-PRODUCT-001',
        ]);
        $matchingStock = $this->warehouseStock($warehouse, $matchingProduct);
        $this->warehouseStock($warehouse, $otherProduct);

        $this->actingAs($user)
            ->get(route('reports.inventory', ['product_id' => $matchingProduct->id]))
            ->assertOk()
            ->assertSeeText('Matching Product')
            ->assertViewHas('inventoryRows', function ($inventoryRows) use ($matchingStock): bool {
                return $inventoryRows->count() === 1
                    && $inventoryRows->getCollection()->first()->is($matchingStock);
            });
    }

    public function test_inventory_report_supports_category_filter(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view']);
        $matchingCategory = $this->category([
            'name' => 'Matching Category',
            'slug' => 'matching-category',
        ]);
        $otherCategory = $this->category([
            'name' => 'Other Category',
            'slug' => 'other-category',
        ]);
        $unit = $this->unit();
        $warehouse = $this->warehouse();
        $matchingProduct = $this->product($matchingCategory, $unit, [
            'name' => 'Category Filter Product',
            'slug' => 'category-filter-product',
            'sku' => 'CATEGORY-FILTER-001',
        ]);
        $otherProduct = $this->product($otherCategory, $unit, [
            'name' => 'Category Filter Hidden Product',
            'slug' => 'category-filter-hidden-product',
            'sku' => 'CATEGORY-FILTER-002',
        ]);
        $matchingStock = $this->warehouseStock($warehouse, $matchingProduct);
        $this->warehouseStock($warehouse, $otherProduct);

        $this->actingAs($user)
            ->get(route('reports.inventory', ['category_id' => $matchingCategory->id]))
            ->assertOk()
            ->assertSeeText('Matching Category')
            ->assertSeeText('Category Filter Product')
            ->assertViewHas('inventoryRows', function ($inventoryRows) use ($matchingStock): bool {
                return $inventoryRows->count() === 1
                    && $inventoryRows->getCollection()->first()->is($matchingStock);
            });
    }

    public function test_inventory_report_supports_in_stock_status_filter(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view']);
        $stocks = $this->stockStatusFixture();

        $this->actingAs($user)
            ->get(route('reports.inventory', ['stock_status' => 'in_stock']))
            ->assertOk()
            ->assertSeeText('In Stock Product')
            ->assertViewHas('inventoryRows', function ($inventoryRows) use ($stocks): bool {
                return $inventoryRows->count() === 1
                    && $inventoryRows->getCollection()->first()->is($stocks['in_stock']);
            });
    }

    public function test_inventory_report_supports_low_stock_status_filter(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view']);
        $stocks = $this->stockStatusFixture();

        $this->actingAs($user)
            ->get(route('reports.inventory', ['stock_status' => 'low_stock']))
            ->assertOk()
            ->assertSeeText('Low Stock Product')
            ->assertViewHas('inventoryRows', function ($inventoryRows) use ($stocks): bool {
                return $inventoryRows->count() === 1
                    && $inventoryRows->getCollection()->first()->is($stocks['low_stock']);
            });
    }

    public function test_inventory_report_supports_out_of_stock_status_filter(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view']);
        $stocks = $this->stockStatusFixture();

        $this->actingAs($user)
            ->get(route('reports.inventory', ['stock_status' => 'out_of_stock']))
            ->assertOk()
            ->assertSeeText('Out Of Stock Product')
            ->assertViewHas('inventoryRows', function ($inventoryRows) use ($stocks): bool {
                return $inventoryRows->count() === 1
                    && $inventoryRows->getCollection()->first()->is($stocks['out_of_stock']);
            });
    }

    public function test_inventory_report_rejects_invalid_stock_status_filter(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view']);

        $this->actingAs($user)
            ->from(route('reports.inventory'))
            ->get(route('reports.inventory', ['stock_status' => 'invalid-status']))
            ->assertRedirect(route('reports.inventory'))
            ->assertSessionHasErrors('stock_status');
    }

    public function test_user_without_inventory_report_permission_receives_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.inventory'))
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
            'description' => 'Temporary role for inventory report tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function category(array $overrides = []): Category
    {
        $key = Str::lower(Str::random(8));

        return Category::query()->create(array_replace([
            'name' => 'Category '.$key,
            'slug' => 'category-'.$key,
            'description' => 'Inventory report test category.',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function unit(array $overrides = []): Unit
    {
        $key = Str::lower(Str::random(8));

        return Unit::query()->create(array_replace([
            'name' => 'Piece '.$key,
            'short_name' => 'pcs-'.$key,
            'description' => 'Inventory report test unit.',
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
    private function product(Category $category, Unit $unit, array $overrides = []): Product
    {
        $key = Str::upper(Str::random(8));

        return Product::query()->create(array_replace([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Product '.$key,
            'slug' => 'product-'.Str::lower($key),
            'sku' => 'SKU-'.$key,
            'description' => 'Inventory report test product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ], $overrides));
    }

    private function warehouseStock(
        Warehouse $warehouse,
        Product $product,
        float|int $quantity = 10,
        float|int $reservedQuantity = 2,
    ): WarehouseStock {
        return WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity,
        ]);
    }

    /**
     * @return array{in_stock: WarehouseStock, low_stock: WarehouseStock, out_of_stock: WarehouseStock}
     */
    private function stockStatusFixture(): array
    {
        $category = $this->category(['name' => 'Status Category', 'slug' => 'status-category']);
        $unit = $this->unit(['name' => 'Status Piece', 'short_name' => 'status-pcs']);
        $warehouse = $this->warehouse([
            'code' => 'STATUS-WH-001',
            'name' => 'Status Warehouse',
        ]);

        $inStockProduct = $this->product($category, $unit, [
            'name' => 'In Stock Product',
            'slug' => 'in-stock-product',
            'sku' => 'STATUS-IN-001',
            'reorder_level' => 5,
        ]);
        $lowStockProduct = $this->product($category, $unit, [
            'name' => 'Low Stock Product',
            'slug' => 'low-stock-product',
            'sku' => 'STATUS-LOW-001',
            'reorder_level' => 5,
        ]);
        $outOfStockProduct = $this->product($category, $unit, [
            'name' => 'Out Of Stock Product',
            'slug' => 'out-of-stock-product',
            'sku' => 'STATUS-OUT-001',
            'reorder_level' => 5,
        ]);

        return [
            'in_stock' => $this->warehouseStock($warehouse, $inStockProduct, quantity: 10, reservedQuantity: 0),
            'low_stock' => $this->warehouseStock($warehouse, $lowStockProduct, quantity: 3, reservedQuantity: 1),
            'out_of_stock' => $this->warehouseStock($warehouse, $outOfStockProduct, quantity: 0, reservedQuantity: 0),
        ];
    }
}
