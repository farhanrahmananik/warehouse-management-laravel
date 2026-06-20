<?php

namespace Tests\Feature\Stock;

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

class StockOverviewTest extends TestCase
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

    public function test_guest_is_redirected_from_stock_index_to_login(): void
    {
        $this->get(route('stock.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_stock_view_permission_receives_403_from_stock_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('stock.index'))
            ->assertForbidden();
    }

    public function test_user_with_stock_view_permission_can_view_stock_overview_page(): void
    {
        $user = $this->userWithPermissions(['stock.view']);

        $this->actingAs($user)
            ->get(route('stock.index'))
            ->assertOk()
            ->assertSee('Stock Overview');
    }

    public function test_stock_overview_displays_warehouse_stock_data(): void
    {
        $stock = $this->warehouseStock(
            warehouseName: 'Main Warehouse',
            warehouseCode: 'WH-MAIN',
            productName: 'USB Keyboard',
            productSlug: 'usb-keyboard',
            sku: 'USB-KEYBOARD-001',
            quantity: 25,
            reservedQuantity: 5,
        );
        $user = $this->userWithPermissions(['stock.view']);

        $this->actingAs($user)
            ->get(route('stock.index'))
            ->assertOk()
            ->assertSee('Main Warehouse')
            ->assertSee('USB Keyboard')
            ->assertSee('USB-KEYBOARD-001')
            ->assertSee('25.0000')
            ->assertSee('5.0000')
            ->assertSee('20.0000')
            ->assertViewHas('stocks', function ($stocks) use ($stock): bool {
                return $stocks->count() === 1
                    && $stocks->getCollection()->first()->is($stock);
            });
    }

    public function test_stock_overview_supports_warehouse_filter(): void
    {
        $matchingStock = $this->warehouseStock(
            warehouseName: 'Main Warehouse',
            warehouseCode: 'WH-MAIN',
            productName: 'USB Keyboard',
            productSlug: 'usb-keyboard',
            sku: 'USB-KEYBOARD-001',
        );
        $this->warehouseStock(
            warehouseName: 'Overflow Warehouse',
            warehouseCode: 'WH-OVERFLOW',
            productName: 'USB Mouse',
            productSlug: 'usb-mouse',
            sku: 'USB-MOUSE-001',
        );
        $user = $this->userWithPermissions(['stock.view']);

        $this->actingAs($user)
            ->get(route('stock.index', ['warehouse_id' => $matchingStock->warehouse_id]))
            ->assertOk()
            ->assertViewHas('stocks', function ($stocks) use ($matchingStock): bool {
                return $stocks->count() === 1
                    && $stocks->getCollection()->first()->is($matchingStock);
            });
    }

    public function test_stock_overview_supports_product_filter(): void
    {
        $matchingStock = $this->warehouseStock(
            warehouseName: 'Main Warehouse',
            warehouseCode: 'WH-MAIN',
            productName: 'USB Keyboard',
            productSlug: 'usb-keyboard',
            sku: 'USB-KEYBOARD-001',
        );
        $this->warehouseStock(
            warehouseName: 'Main Warehouse',
            warehouseCode: 'WH-MAIN-2',
            productName: 'USB Mouse',
            productSlug: 'usb-mouse',
            sku: 'USB-MOUSE-001',
        );
        $user = $this->userWithPermissions(['stock.view']);

        $this->actingAs($user)
            ->get(route('stock.index', ['product_id' => $matchingStock->product_id]))
            ->assertOk()
            ->assertViewHas('stocks', function ($stocks) use ($matchingStock): bool {
                return $stocks->count() === 1
                    && $stocks->getCollection()->first()->is($matchingStock);
            });
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
            'description' => 'Temporary role for stock overview tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function warehouseStock(
        string $warehouseName,
        string $warehouseCode,
        string $productName,
        string $productSlug,
        string $sku,
        float|int $quantity = 10,
        float|int $reservedQuantity = 2,
    ): WarehouseStock {
        $warehouse = Warehouse::query()->create([
            'code' => $warehouseCode,
            'name' => $warehouseName,
            'is_active' => true,
        ]);

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

        $product = Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => $productName,
            'slug' => $productSlug,
            'sku' => $sku,
            'description' => 'Test stock product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ]);

        return WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity,
        ]);
    }
}
