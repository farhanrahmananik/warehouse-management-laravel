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
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockMovementLedgerTest extends TestCase
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

    public function test_guest_is_redirected_from_stock_movements_index_to_login(): void
    {
        $this->get(route('stock-movements.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_stock_view_permission_receives_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('stock-movements.index'))
            ->assertForbidden();
    }

    public function test_user_with_stock_view_permission_can_view_stock_movement_ledger_page(): void
    {
        $user = $this->userWithPermissions(['stock.view']);

        $this->actingAs($user)
            ->get(route('stock-movements.index'))
            ->assertOk()
            ->assertSee('Stock Movement Ledger');
    }

    public function test_ledger_displays_stock_movement_data(): void
    {
        $movement = $this->stockMovement(
            warehouseName: 'Main Warehouse',
            warehouseCode: 'WH-MAIN',
            productName: 'USB Keyboard',
            productSlug: 'usb-keyboard-ledger',
            sku: 'USB-KEYBOARD-LEDGER',
            movementType: 'opening_balance',
            quantity: 12.5,
            balanceAfter: 12.5,
            remarks: 'Initial stock count.',
            creatorName: 'Stock Clerk',
        );
        $user = $this->userWithPermissions(['stock.view']);

        $this->actingAs($user)
            ->get(route('stock-movements.index'))
            ->assertOk()
            ->assertSee('Main Warehouse')
            ->assertSee('USB Keyboard')
            ->assertSee('USB-KEYBOARD-LEDGER')
            ->assertSee('Opening Balance')
            ->assertSee('12.5000')
            ->assertSee('Stock Clerk')
            ->assertSee('Initial stock count.')
            ->assertViewHas('movements', function ($movements) use ($movement): bool {
                return $movements->count() === 1
                    && $movements->getCollection()->first()->is($movement);
            });
    }

    public function test_ledger_supports_warehouse_filter(): void
    {
        $matchingMovement = $this->stockMovement(
            warehouseName: 'Main Warehouse',
            warehouseCode: 'WH-MAIN',
            productName: 'USB Keyboard',
            productSlug: 'usb-keyboard-filter',
            sku: 'USB-KEYBOARD-FILTER',
        );
        $this->stockMovement(
            warehouseName: 'Overflow Warehouse',
            warehouseCode: 'WH-OVERFLOW',
            productName: 'USB Mouse',
            productSlug: 'usb-mouse-filter',
            sku: 'USB-MOUSE-FILTER',
        );
        $user = $this->userWithPermissions(['stock.view']);

        $this->actingAs($user)
            ->get(route('stock-movements.index', ['warehouse_id' => $matchingMovement->warehouse_id]))
            ->assertOk()
            ->assertViewHas('movements', function ($movements) use ($matchingMovement): bool {
                return $movements->count() === 1
                    && $movements->getCollection()->first()->is($matchingMovement);
            });
    }

    public function test_ledger_supports_product_filter(): void
    {
        $matchingMovement = $this->stockMovement(
            warehouseName: 'Main Warehouse',
            warehouseCode: 'WH-MAIN-PRODUCT',
            productName: 'USB Keyboard',
            productSlug: 'usb-keyboard-product-filter',
            sku: 'USB-KEYBOARD-PRODUCT-FILTER',
        );
        $this->stockMovement(
            warehouseName: 'Overflow Warehouse',
            warehouseCode: 'WH-OVERFLOW-PRODUCT',
            productName: 'USB Mouse',
            productSlug: 'usb-mouse-product-filter',
            sku: 'USB-MOUSE-PRODUCT-FILTER',
        );
        $user = $this->userWithPermissions(['stock.view']);

        $this->actingAs($user)
            ->get(route('stock-movements.index', ['product_id' => $matchingMovement->product_id]))
            ->assertOk()
            ->assertViewHas('movements', function ($movements) use ($matchingMovement): bool {
                return $movements->count() === 1
                    && $movements->getCollection()->first()->is($matchingMovement);
            });
    }

    public function test_ledger_supports_movement_type_filter(): void
    {
        $matchingMovement = $this->stockMovement(
            warehouseName: 'Main Warehouse',
            warehouseCode: 'WH-MAIN-TYPE',
            productName: 'USB Keyboard',
            productSlug: 'usb-keyboard-type-filter',
            sku: 'USB-KEYBOARD-TYPE-FILTER',
            movementType: 'adjustment_in',
        );
        $this->stockMovement(
            warehouseName: 'Overflow Warehouse',
            warehouseCode: 'WH-OVERFLOW-TYPE',
            productName: 'USB Mouse',
            productSlug: 'usb-mouse-type-filter',
            sku: 'USB-MOUSE-TYPE-FILTER',
            movementType: 'adjustment_out',
        );
        $user = $this->userWithPermissions(['stock.view']);

        $this->actingAs($user)
            ->get(route('stock-movements.index', ['movement_type' => 'adjustment_in']))
            ->assertOk()
            ->assertViewHas('movements', function ($movements) use ($matchingMovement): bool {
                return $movements->count() === 1
                    && $movements->getCollection()->first()->is($matchingMovement);
            });
    }

    public function test_ledger_supports_date_range_filter(): void
    {
        $matchingMovement = $this->stockMovement(
            warehouseName: 'Main Warehouse',
            warehouseCode: 'WH-MAIN-DATE',
            productName: 'USB Keyboard',
            productSlug: 'usb-keyboard-date-filter',
            sku: 'USB-KEYBOARD-DATE-FILTER',
            createdAt: '2026-06-15 10:30:00',
        );
        $this->stockMovement(
            warehouseName: 'Overflow Warehouse',
            warehouseCode: 'WH-OVERFLOW-DATE',
            productName: 'USB Mouse',
            productSlug: 'usb-mouse-date-filter',
            sku: 'USB-MOUSE-DATE-FILTER',
            createdAt: '2026-06-18 10:30:00',
        );
        $user = $this->userWithPermissions(['stock.view']);

        $this->actingAs($user)
            ->get(route('stock-movements.index', [
                'date_from' => '2026-06-15',
                'date_to' => '2026-06-15',
            ]))
            ->assertOk()
            ->assertViewHas('movements', function ($movements) use ($matchingMovement): bool {
                return $movements->count() === 1
                    && $movements->getCollection()->first()->is($matchingMovement);
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
            'description' => 'Temporary role for stock movement ledger tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function stockMovement(
        string $warehouseName,
        string $warehouseCode,
        string $productName,
        string $productSlug,
        string $sku,
        string $movementType = 'opening_balance',
        float|int $quantity = 10,
        float|int $balanceAfter = 10,
        ?string $remarks = null,
        ?string $creatorName = null,
        ?string $createdAt = null,
    ): StockMovement {
        $warehouse = Warehouse::query()->create([
            'code' => $warehouseCode,
            'name' => $warehouseName,
            'is_active' => true,
        ]);

        $product = $this->product($productName, $productSlug, $sku);
        $creator = User::factory()->create([
            'name' => $creatorName ?? 'Test Stock User',
        ]);

        $movement = StockMovement::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'remarks' => $remarks,
            'created_by' => $creator->id,
        ]);

        if ($createdAt !== null) {
            $timestamp = Carbon::parse($createdAt);

            $movement->forceFill([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->save();
        }

        return $movement->refresh();
    }

    private function product(string $name, string $slug, string $sku): Product
    {
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
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'description' => 'Test stock movement product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ]);
    }
}
