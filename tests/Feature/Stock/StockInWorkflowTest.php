<?php

namespace Tests\Feature\Stock;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockIn;
use App\Models\StockInItem;
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

class StockInWorkflowTest extends TestCase
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

    public function test_guest_is_redirected_from_stock_ins_index_to_login(): void
    {
        $this->get(route('stock-ins.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_stock_in_view_permission_receives_403_from_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('stock-ins.index'))
            ->assertForbidden();
    }

    public function test_user_with_stock_in_view_permission_can_view_index(): void
    {
        $user = $this->userWithPermissions(['stock-in.view']);

        $this->actingAs($user)
            ->get(route('stock-ins.index'))
            ->assertOk()
            ->assertSee('Stock In');
    }

    public function test_user_with_stock_in_create_permission_can_view_create_page(): void
    {
        $this->warehouse();
        $this->product();
        $user = $this->userWithPermissions(['stock-in.create']);

        $this->actingAs($user)
            ->get(route('stock-ins.create'))
            ->assertOk()
            ->assertSee('Create Stock In');
    }

    public function test_stock_in_creation_validates_required_fields(): void
    {
        $user = $this->userWithPermissions(['stock-in.create']);

        $this->actingAs($user)
            ->post(route('stock-ins.store'), [])
            ->assertSessionHasErrors([
                'warehouse_id',
                'stock_date',
                'items',
            ]);
    }

    public function test_user_with_stock_in_create_permission_can_create_stock_in_document(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $user = $this->userWithPermissions(['stock-in.create']);

        $response = $this->actingAs($user)
            ->post(route('stock-ins.store'), $this->validStockInPayload($warehouse, $product, 5));

        $stockIn = StockIn::query()->firstOrFail();
        $response->assertRedirect(route('stock-ins.show', $stockIn));

        $this->assertStringStartsWith('SI-', $stockIn->document_no);
        $this->assertSame($warehouse->id, $stockIn->warehouse_id);
        $this->assertSame($user->id, $stockIn->created_by);

        $item = StockInItem::query()->firstOrFail();
        $this->assertSame($stockIn->id, $item->stock_in_id);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('5.0000', $item->quantity);
    }

    public function test_stock_in_creation_increases_warehouse_stock_quantity(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $stock = WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 6,
            'reserved_quantity' => 2,
        ]);
        $user = $this->userWithPermissions(['stock-in.create']);

        $this->actingAs($user)
            ->post(route('stock-ins.store'), $this->validStockInPayload($warehouse, $product, 4))
            ->assertRedirect();

        $stock->refresh();
        $this->assertSame('10.0000', $stock->quantity);
        $this->assertSame('2.0000', $stock->reserved_quantity);
    }

    public function test_stock_in_creation_creates_stock_movement_with_stock_in_type(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $user = $this->userWithPermissions(['stock-in.create']);

        $this->actingAs($user)
            ->post(route('stock-ins.store'), $this->validStockInPayload($warehouse, $product, 3.5, [
                'remarks' => 'Received from external source.',
            ]))
            ->assertRedirect();

        $stockIn = StockIn::query()->firstOrFail();
        $movement = StockMovement::query()->firstOrFail();

        $this->assertSame($warehouse->id, $movement->warehouse_id);
        $this->assertSame($product->id, $movement->product_id);
        $this->assertSame('stock_in', $movement->movement_type);
        $this->assertSame('3.5000', $movement->quantity);
        $this->assertSame('3.5000', $movement->balance_after);
        $this->assertSame(StockIn::class, $movement->reference_type);
        $this->assertSame($stockIn->id, $movement->reference_id);
        $this->assertSame('Received from external source.', $movement->remarks);
        $this->assertSame($user->id, $movement->created_by);
    }

    public function test_stock_in_show_page_displays_document_and_item_details(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product([
            'name' => 'Display Product',
            'slug' => 'display-product',
            'sku' => 'DISPLAY-001',
        ]);
        $stockIn = StockIn::query()->create([
            'document_no' => 'SI-TEST-0001',
            'warehouse_id' => $warehouse->id,
            'stock_date' => now()->toDateString(),
            'remarks' => 'Display document remarks.',
            'created_by' => User::factory()->create()->id,
        ]);
        $stockIn->items()->create([
            'product_id' => $product->id,
            'quantity' => 7,
            'remarks' => 'Display item remarks.',
        ]);
        $user = $this->userWithPermissions(['stock-in.view']);

        $this->actingAs($user)
            ->get(route('stock-ins.show', $stockIn))
            ->assertOk()
            ->assertSee('SI-TEST-0001')
            ->assertSee('Display Product')
            ->assertSee('DISPLAY-001')
            ->assertSee('7.0000')
            ->assertSee('Display item remarks.');
    }

    public function test_duplicate_product_rows_are_rejected(): void
    {
        $warehouse = $this->warehouse();
        $product = $this->product();
        $user = $this->userWithPermissions(['stock-in.create']);

        $payload = $this->validStockInPayload($warehouse, $product, 5);
        $payload['items'][] = [
            'product_id' => $product->id,
            'quantity' => 2,
            'remarks' => 'Duplicate product row.',
        ];

        $this->actingAs($user)
            ->from(route('stock-ins.create'))
            ->post(route('stock-ins.store'), $payload)
            ->assertRedirect(route('stock-ins.create'))
            ->assertSessionHasErrors();

        $this->assertSame(0, StockIn::query()->count());
        $this->assertSame(0, WarehouseStock::query()->count());
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
            'description' => 'Temporary role for stock in workflow tests.',
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
            'description' => 'Test stock in product.',
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
    private function validStockInPayload(
        Warehouse $warehouse,
        Product $product,
        float|int $quantity,
        array $itemOverrides = [],
    ): array {
        return [
            'warehouse_id' => $warehouse->id,
            'stock_date' => now()->toDateString(),
            'remarks' => 'Header stock in remarks.',
            'items' => [
                array_replace([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'remarks' => 'Item stock in remarks.',
                ], $itemOverrides),
            ],
        ];
    }
}
