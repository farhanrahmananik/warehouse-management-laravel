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

class LowStockReportTest extends TestCase
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

    public function test_user_with_low_stock_report_permission_can_see_report_data(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view']);
        $category = $this->category(['name' => 'Report Low Category', 'slug' => 'report-low-category']);
        $unit = $this->unit(['name' => 'Piece', 'short_name' => 'pcs']);
        $warehouse = $this->warehouse([
            'code' => 'LOW-WH-001',
            'name' => 'Low Stock Warehouse',
        ]);
        $product = $this->product($category, $unit, [
            'name' => 'Low Stock Product',
            'slug' => 'low-stock-product',
            'sku' => 'LOW-STOCK-001',
            'reorder_level' => 5,
        ]);
        $stock = $this->warehouseStock($warehouse, $product, quantity: 4, reservedQuantity: 1);

        $this->actingAs($user)
            ->get(route('reports.low-stock'))
            ->assertOk()
            ->assertSeeText('Low Stock Report')
            ->assertSeeText('Low Stock Warehouse')
            ->assertSeeText('LOW-WH-001')
            ->assertSeeText('Low Stock Product')
            ->assertSeeText('LOW-STOCK-001')
            ->assertSeeText('Report Low Category')
            ->assertSeeText('pcs')
            ->assertSeeText('4.0000')
            ->assertSeeText('1.0000')
            ->assertSeeText('3.0000')
            ->assertSeeText('5.00')
            ->assertSeeText('2.0000')
            ->assertViewHas('lowStockRows', function ($lowStockRows) use ($stock): bool {
                return $lowStockRows->count() === 1
                    && $lowStockRows->getCollection()->first()->is($stock);
            });
    }

    public function test_low_stock_report_includes_low_stock_products(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view']);
        $stocks = $this->stockStatusFixture();

        $this->actingAs($user)
            ->get(route('reports.low-stock'))
            ->assertOk()
            ->assertSeeText('Fixture Low Stock Product')
            ->assertViewHas('lowStockRows', function ($lowStockRows) use ($stocks): bool {
                return $lowStockRows->getCollection()->contains(fn ($row): bool => $row->is($stocks['low_stock']));
            });
    }

    public function test_low_stock_report_includes_out_of_stock_products(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view']);
        $stocks = $this->stockStatusFixture();

        $this->actingAs($user)
            ->get(route('reports.low-stock'))
            ->assertOk()
            ->assertSeeText('Fixture Out Of Stock Product')
            ->assertViewHas('lowStockRows', function ($lowStockRows) use ($stocks): bool {
                return $lowStockRows->getCollection()->contains(fn ($row): bool => $row->is($stocks['out_of_stock']));
            });
    }

    public function test_low_stock_report_excludes_healthy_in_stock_products(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view']);
        $stocks = $this->stockStatusFixture();

        $this->actingAs($user)
            ->get(route('reports.low-stock'))
            ->assertOk()
            ->assertDontSeeText('Fixture Healthy Product')
            ->assertViewHas('lowStockRows', function ($lowStockRows) use ($stocks): bool {
                return ! $lowStockRows->getCollection()->contains(fn ($row): bool => $row->is($stocks['healthy']));
            });
    }

    public function test_low_stock_report_supports_warehouse_filter(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view']);
        $category = $this->category();
        $unit = $this->unit();
        $matchingWarehouse = $this->warehouse([
            'code' => 'LOW-FILTER-WH-001',
            'name' => 'Matching Low Warehouse',
        ]);
        $otherWarehouse = $this->warehouse([
            'code' => 'LOW-FILTER-WH-002',
            'name' => 'Other Low Warehouse',
        ]);
        $product = $this->product($category, $unit, [
            'name' => 'Warehouse Filter Low Product',
            'slug' => 'warehouse-filter-low-product',
            'sku' => 'LOW-WAREHOUSE-FILTER-001',
            'reorder_level' => 5,
        ]);
        $matchingStock = $this->warehouseStock($matchingWarehouse, $product, quantity: 2, reservedQuantity: 0);
        $this->warehouseStock($otherWarehouse, $product, quantity: 1, reservedQuantity: 0);

        $this->actingAs($user)
            ->get(route('reports.low-stock', ['warehouse_id' => $matchingWarehouse->id]))
            ->assertOk()
            ->assertSeeText('Matching Low Warehouse')
            ->assertViewHas('lowStockRows', function ($lowStockRows) use ($matchingStock): bool {
                return $lowStockRows->count() === 1
                    && $lowStockRows->getCollection()->first()->is($matchingStock);
            });
    }

    public function test_low_stock_report_supports_category_filter(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view']);
        $matchingCategory = $this->category([
            'name' => 'Matching Low Category',
            'slug' => 'matching-low-category',
        ]);
        $otherCategory = $this->category([
            'name' => 'Other Low Category',
            'slug' => 'other-low-category',
        ]);
        $unit = $this->unit();
        $warehouse = $this->warehouse();
        $matchingProduct = $this->product($matchingCategory, $unit, [
            'name' => 'Category Filter Low Product',
            'slug' => 'category-filter-low-product',
            'sku' => 'LOW-CATEGORY-FILTER-001',
            'reorder_level' => 5,
        ]);
        $otherProduct = $this->product($otherCategory, $unit, [
            'name' => 'Other Category Low Product',
            'slug' => 'other-category-low-product',
            'sku' => 'LOW-CATEGORY-FILTER-002',
            'reorder_level' => 5,
        ]);
        $matchingStock = $this->warehouseStock($warehouse, $matchingProduct, quantity: 2, reservedQuantity: 0);
        $this->warehouseStock($warehouse, $otherProduct, quantity: 1, reservedQuantity: 0);

        $this->actingAs($user)
            ->get(route('reports.low-stock', ['category_id' => $matchingCategory->id]))
            ->assertOk()
            ->assertSeeText('Matching Low Category')
            ->assertSeeText('Category Filter Low Product')
            ->assertViewHas('lowStockRows', function ($lowStockRows) use ($matchingStock): bool {
                return $lowStockRows->count() === 1
                    && $lowStockRows->getCollection()->first()->is($matchingStock);
            });
    }

    public function test_low_stock_report_supports_low_stock_status_filter(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view']);
        $stocks = $this->stockStatusFixture();

        $this->actingAs($user)
            ->get(route('reports.low-stock', ['stock_status' => 'low_stock']))
            ->assertOk()
            ->assertSeeText('Fixture Low Stock Product')
            ->assertViewHas('lowStockRows', function ($lowStockRows) use ($stocks): bool {
                return $lowStockRows->count() === 1
                    && $lowStockRows->getCollection()->first()->is($stocks['low_stock']);
            });
    }

    public function test_low_stock_report_supports_out_of_stock_status_filter(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view']);
        $stocks = $this->stockStatusFixture();

        $this->actingAs($user)
            ->get(route('reports.low-stock', ['stock_status' => 'out_of_stock']))
            ->assertOk()
            ->assertSeeText('Fixture Out Of Stock Product')
            ->assertViewHas('lowStockRows', function ($lowStockRows) use ($stocks): bool {
                return $lowStockRows->count() === 1
                    && $lowStockRows->getCollection()->first()->is($stocks['out_of_stock']);
            });
    }

    public function test_low_stock_report_rejects_invalid_stock_status_filter(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view']);

        $this->actingAs($user)
            ->from(route('reports.low-stock'))
            ->get(route('reports.low-stock', ['stock_status' => 'in_stock']))
            ->assertRedirect(route('reports.low-stock'))
            ->assertSessionHasErrors('stock_status');
    }

    public function test_user_without_low_stock_report_permission_receives_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.low-stock'))
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
            'description' => 'Temporary role for low stock report tests.',
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
            'description' => 'Low stock report test category.',
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
            'description' => 'Low stock report test unit.',
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
            'description' => 'Low stock report test product.',
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
     * @return array{low_stock: WarehouseStock, out_of_stock: WarehouseStock, healthy: WarehouseStock}
     */
    private function stockStatusFixture(): array
    {
        $category = $this->category(['name' => 'Low Status Category', 'slug' => 'low-status-category']);
        $unit = $this->unit(['name' => 'Status Piece', 'short_name' => 'low-status-pcs']);
        $warehouse = $this->warehouse([
            'code' => 'LOW-STATUS-WH',
            'name' => 'Low Status Warehouse',
        ]);

        $lowStockProduct = $this->product($category, $unit, [
            'name' => 'Fixture Low Stock Product',
            'slug' => 'fixture-low-stock-product',
            'sku' => 'FIXTURE-LOW-001',
            'reorder_level' => 5,
        ]);
        $outOfStockProduct = $this->product($category, $unit, [
            'name' => 'Fixture Out Of Stock Product',
            'slug' => 'fixture-out-of-stock-product',
            'sku' => 'FIXTURE-OUT-001',
            'reorder_level' => 5,
        ]);
        $healthyProduct = $this->product($category, $unit, [
            'name' => 'Fixture Healthy Product',
            'slug' => 'fixture-healthy-product',
            'sku' => 'FIXTURE-HEALTHY-001',
            'reorder_level' => 5,
        ]);

        return [
            'low_stock' => $this->warehouseStock($warehouse, $lowStockProduct, quantity: 3, reservedQuantity: 1),
            'out_of_stock' => $this->warehouseStock($warehouse, $outOfStockProduct, quantity: 0, reservedQuantity: 0),
            'healthy' => $this->warehouseStock($warehouse, $healthyProduct, quantity: 10, reservedQuantity: 1),
        ];
    }
}
