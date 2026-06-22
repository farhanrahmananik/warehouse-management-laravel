<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\StockOutItem;
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

class StockOutWorkflowTest extends TestCase
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

    public function test_guest_is_redirected_from_stock_outs_index_to_login(): void
    {
        $this->get(route('stock-outs.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_stock_out_view_permission_receives_403_from_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('stock-outs.index'))
            ->assertForbidden();
    }

    public function test_user_with_stock_out_view_permission_can_view_index(): void
    {
        $user = $this->userWithPermissions(['stock-out.view']);

        $this->actingAs($user)
            ->get(route('stock-outs.index'))
            ->assertOk()
            ->assertSee('Stock Out');
    }

    public function test_user_with_stock_out_create_permission_can_view_create_page(): void
    {
        $this->warehouse();
        $this->product();
        $user = $this->userWithPermissions(['stock-out.create']);

        $this->actingAs($user)
            ->get(route('stock-outs.create'))
            ->assertOk()
            ->assertSee('Create Stock Out');
    }

    public function test_stock_out_creation_validates_required_fields(): void
    {
        $user = $this->userWithPermissions(['stock-out.create']);

        $this->actingAs($user)
            ->post(route('stock-outs.store'), [])
            ->assertSessionHasErrors([
                'warehouse_id',
                'stock_date',
                'items',
            ]);
    }

    public function test_user_with_stock_out_create_permission_can_create_stock_out_document(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'reserved_quantity' => 2,
        ]);
        $user = $this->userWithPermissions(['stock-out.create']);

        $response = $this->actingAs($user)
            ->post(route('stock-outs.store'), $this->validStockOutPayload($warehouse, $product, 5));

        $stockOut = StockOut::query()->firstOrFail();
        $response->assertRedirect(route('stock-outs.show', $stockOut));

        $this->assertStringStartsWith('SO-', $stockOut->document_no);
        $this->assertSame($warehouse->id, $stockOut->warehouse_id);
        $this->assertSame($user->id, $stockOut->created_by);

        $item = StockOutItem::query()->firstOrFail();
        $this->assertSame($stockOut->id, $item->stock_out_id);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('5.0000', $item->quantity);
    }

    public function test_stock_out_creation_decreases_warehouse_stock_quantity(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $stock = WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 12,
            'reserved_quantity' => 2,
        ]);
        $user = $this->userWithPermissions(['stock-out.create']);

        $this->actingAs($user)
            ->post(route('stock-outs.store'), $this->validStockOutPayload($warehouse, $product, 4))
            ->assertRedirect();

        $stock->refresh();
        $this->assertSame('8.0000', $stock->quantity);
        $this->assertSame('2.0000', $stock->reserved_quantity);
    }

    public function test_stock_out_creation_creates_stock_movement_with_stock_out_type(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 8,
            'reserved_quantity' => 0,
        ]);
        $user = $this->userWithPermissions(['stock-out.create']);

        $this->actingAs($user)
            ->post(route('stock-outs.store'), $this->validStockOutPayload($warehouse, $product, 3.5, [
                'remarks' => 'Issued to operations.',
            ]))
            ->assertRedirect();

        $stockOut = StockOut::query()->firstOrFail();
        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame($warehouse->id, $movement->warehouse_id);
        $this->assertSame($product->id, $movement->product_id);
        $this->assertSame('stock_out', $movement->movement_type);
        $this->assertSame('3.5000', $movement->quantity);
        $this->assertSame('4.5000', $movement->balance_after);
        $this->assertSame(StockOut::class, $movement->reference_type);
        $this->assertSame($stockOut->id, $movement->reference_id);
        $this->assertSame('Issued to operations.', $movement->remarks);
        $this->assertSame($user->id, $movement->created_by);
    }

    public function test_stock_out_show_page_displays_document_and_item_details(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product([
            'name' => 'Display Product',
            'slug' => 'display-product',
            'sku' => 'DISPLAY-OUT-001',
        ]);
        $stockOut = StockOut::query()->create([
            'document_no' => 'SO-TEST-0001',
            'warehouse_id' => $warehouse->id,
            'stock_date' => now()->toDateString(),
            'remarks' => 'Display stock out remarks.',
            'created_by' => User::factory()->create()->id,
        ]);
        $stockOut->items()->create([
            'product_id' => $product->id,
            'quantity' => 7,
            'remarks' => 'Display item remarks.',
        ]);
        $user = $this->userWithPermissions(['stock-out.view']);

        $this->actingAs($user)
            ->get(route('stock-outs.show', $stockOut))
            ->assertOk()
            ->assertSee('SO-TEST-0001')
            ->assertSee('Display Product')
            ->assertSee('DISPLAY-OUT-001')
            ->assertSee('7.0000')
            ->assertSee('Display item remarks.');
    }

    public function test_stock_out_cannot_reduce_stock_below_zero(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $stock = WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'reserved_quantity' => 0,
        ]);
        $user = $this->userWithPermissions(['stock-out.create']);

        $this->actingAs($user)
            ->from(route('stock-outs.create'))
            ->post(route('stock-outs.store'), $this->validStockOutPayload($warehouse, $product, 3))
            ->assertRedirect(route('stock-outs.create'))
            ->assertSessionHasErrors(['items.0.quantity']);

        $stock->refresh();
        $this->assertSame('2.0000', $stock->quantity);
        $this->assertSame(0, StockOut::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_stock_out_cannot_reduce_stock_below_reserved_quantity(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $stock = WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'reserved_quantity' => 7,
        ]);
        $user = $this->userWithPermissions(['stock-out.create']);

        $this->actingAs($user)
            ->from(route('stock-outs.create'))
            ->post(route('stock-outs.store'), $this->validStockOutPayload($warehouse, $product, 4))
            ->assertRedirect(route('stock-outs.create'))
            ->assertSessionHasErrors(['items.0.quantity']);

        $stock->refresh();
        $this->assertSame('10.0000', $stock->quantity);
        $this->assertSame('7.0000', $stock->reserved_quantity);
        $this->assertSame(0, StockOut::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_duplicate_product_rows_are_rejected(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);
        $user = $this->userWithPermissions(['stock-out.create']);

        $payload = $this->validStockOutPayload($warehouse, $product, 5);
        $payload['items'][] = [
            'product_id' => $product->id,
            'quantity' => 2,
            'remarks' => 'Duplicate product row.',
        ];

        $this->actingAs($user)
            ->from(route('stock-outs.create'))
            ->post(route('stock-outs.store'), $payload)
            ->assertRedirect(route('stock-outs.create'))
            ->assertSessionHasErrors();

        $this->assertSame(0, StockOut::query()->count());
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
            'description' => 'Temporary role for stock out workflow tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function warehouse(): Warehouse
    {
        $code = 'WH-'.Str::upper(Str::random(8));

        return Warehouse::query()->create([
            'code' => $code,
            'name' => 'Warehouse '.$code,
            'is_active' => true,
        ]);
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
            'description' => 'Test stock out product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $itemOverrides
     * @return array<string, mixed>
     */
    private function validStockOutPayload(
        Warehouse $warehouse,
        Product $product,
        float|int $quantity,
        array $itemOverrides = [],
    ): array {
        return [
            'warehouse_id' => $warehouse->id,
            'stock_date' => now()->toDateString(),
            'remarks' => 'Header stock out remarks.',
            'items' => [
                array_replace([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'remarks' => 'Item stock out remarks.',
                ], $itemOverrides),
            ],
        ];
    }
}
