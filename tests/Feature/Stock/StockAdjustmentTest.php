<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
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

class StockAdjustmentTest extends TestCase
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

    public function test_guest_is_redirected_from_stock_adjustment_create_page_to_login(): void
    {
        $this->get(route('stock-adjustments.create'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_stock_adjustments_create_permission_receives_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('stock-adjustments.create'))
            ->assertForbidden();
    }

    public function test_user_with_stock_adjustments_create_permission_can_view_adjustment_create_page(): void
    {
        $this->warehouse();
        $this->product();
        $user = $this->userWithPermissions(['stock-adjustments.create']);

        $this->actingAs($user)
            ->get(route('stock-adjustments.create'))
            ->assertOk()
            ->assertSee('Stock Adjustment')
            ->assertSee('Opening Balance')
            ->assertSee('Adjustment In')
            ->assertSee('Adjustment Out');
    }

    public function test_opening_balance_creates_warehouse_stock_and_stock_movement(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $user = $this->userWithPermissions(['stock-adjustments.create']);

        $this->actingAs($user)
            ->post(route('stock-adjustments.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_type' => 'opening_balance',
                'quantity' => 25,
                'remarks' => 'Initial opening stock.',
            ])
            ->assertRedirect(route('stock.index'));

        $stock = WarehouseStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertSame('25.0000', $stock->quantity);
        $this->assertSame('0.0000', $stock->reserved_quantity);

        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame($warehouse->id, $movement->warehouse_id);
        $this->assertSame($product->id, $movement->product_id);
        $this->assertSame('opening_balance', $movement->movement_type);
        $this->assertSame('25.0000', $movement->quantity);
        $this->assertSame('25.0000', $movement->balance_after);
        $this->assertSame('Initial opening stock.', $movement->remarks);
        $this->assertSame($user->id, $movement->created_by);
    }

    public function test_opening_balance_cannot_be_created_twice_for_same_warehouse_and_product(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $user = $this->userWithPermissions(['stock-adjustments.create']);

        $this->actingAs($user)
            ->post(route('stock-adjustments.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_type' => 'opening_balance',
                'quantity' => 10,
            ])
            ->assertRedirect(route('stock.index'));

        $this->actingAs($user)
            ->from(route('stock-adjustments.create'))
            ->post(route('stock-adjustments.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_type' => 'opening_balance',
                'quantity' => 20,
            ])
            ->assertRedirect(route('stock-adjustments.create'))
            ->assertSessionHasErrors('movement_type');

        $stock = WarehouseStock::query()->firstOrFail();

        $this->assertSame('10.0000', $stock->quantity);
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_adjustment_in_increases_existing_stock_and_creates_movement(): void
    {
        [$warehouse, $product, $stock] = $this->warehouseStock(quantity: 10, reservedQuantity: 2);
        $user = $this->userWithPermissions(['stock-adjustments.create']);

        $this->actingAs($user)
            ->post(route('stock-adjustments.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_type' => 'adjustment_in',
                'quantity' => 4.5,
                'remarks' => 'Manual count increase.',
            ])
            ->assertRedirect(route('stock.index'));

        $stock->refresh();
        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame('14.5000', $stock->quantity);
        $this->assertSame('adjustment_in', $movement->movement_type);
        $this->assertSame('4.5000', $movement->quantity);
        $this->assertSame('14.5000', $movement->balance_after);
    }

    public function test_adjustment_out_decreases_existing_stock_and_creates_movement(): void
    {
        [$warehouse, $product, $stock] = $this->warehouseStock(quantity: 10, reservedQuantity: 2);
        $user = $this->userWithPermissions(['stock-adjustments.create']);

        $this->actingAs($user)
            ->post(route('stock-adjustments.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_type' => 'adjustment_out',
                'quantity' => 3,
                'remarks' => 'Manual count decrease.',
            ])
            ->assertRedirect(route('stock.index'));

        $stock->refresh();
        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame('7.0000', $stock->quantity);
        $this->assertSame('adjustment_out', $movement->movement_type);
        $this->assertSame('3.0000', $movement->quantity);
        $this->assertSame('7.0000', $movement->balance_after);
    }

    public function test_adjustment_out_cannot_reduce_stock_below_zero(): void
    {
        [$warehouse, $product, $stock] = $this->warehouseStock(quantity: 3, reservedQuantity: 0);
        $user = $this->userWithPermissions(['stock-adjustments.create']);

        $this->actingAs($user)
            ->from(route('stock-adjustments.create'))
            ->post(route('stock-adjustments.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_type' => 'adjustment_out',
                'quantity' => 4,
            ])
            ->assertRedirect(route('stock-adjustments.create'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame('3.0000', $stock->refresh()->quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_adjustment_out_cannot_reduce_stock_below_reserved_quantity(): void
    {
        [$warehouse, $product, $stock] = $this->warehouseStock(quantity: 10, reservedQuantity: 8);
        $user = $this->userWithPermissions(['stock-adjustments.create']);

        $this->actingAs($user)
            ->from(route('stock-adjustments.create'))
            ->post(route('stock-adjustments.store'), [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_type' => 'adjustment_out',
                'quantity' => 3,
            ])
            ->assertRedirect(route('stock-adjustments.create'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame('10.0000', $stock->refresh()->quantity);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_stock_adjustment_validation_requires_warehouse_product_movement_type_and_quantity(): void
    {
        $user = $this->userWithPermissions(['stock-adjustments.create']);

        $this->actingAs($user)
            ->post(route('stock-adjustments.store'), [])
            ->assertSessionHasErrors([
                'warehouse_id',
                'product_id',
                'movement_type',
                'quantity',
            ]);
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
            'description' => 'Temporary role for stock adjustment tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * @return array{0: Warehouse, 1: Product, 2: WarehouseStock}
     */
    private function warehouseStock(float|int $quantity, float|int $reservedQuantity): array
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $stock = WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity,
        ]);

        return [$warehouse, $product, $stock];
    }

    private function warehouse(): Warehouse
    {
        return Warehouse::query()->create([
            'code' => 'WH-'.Str::upper(Str::random(8)),
            'name' => 'Test Warehouse',
            'is_active' => true,
        ]);
    }

    private function product(): Product
    {
        $sku = 'SKU-'.Str::upper(Str::random(8));
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

        return Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Test Product '.$sku,
            'slug' => 'test-product-'.Str::slug($sku),
            'sku' => $sku,
            'description' => 'Test stock adjustment product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ]);
    }
}
