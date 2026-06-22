<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
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

class StockAdjustmentAuditLogTest extends TestCase
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

    public function test_opening_balance_stock_adjustment_creates_audit_log(): void
    {
        $warehouse = $this->warehouse('WH-OPEN', 'Opening Warehouse');
        $product = $this->product('Opening Product', 'OPEN-PRODUCT-001');
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

        $movement = StockMovement::query()->firstOrFail();
        $stock = WarehouseStock::query()->firstOrFail();
        $auditLog = $this->latestAuditLog();

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($movement->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($movement->id, $auditLog->auditable_id);
        $this->assertSame(
            'Stock adjustment was recorded for product "Opening Product" in warehouse "Opening Warehouse".',
            $auditLog->description,
        );
        $this->assertSame($warehouse->id, $auditLog->new_values['warehouse_id']);
        $this->assertSame('Opening Warehouse', $auditLog->new_values['warehouse_name']);
        $this->assertSame($product->id, $auditLog->new_values['product_id']);
        $this->assertSame('Opening Product', $auditLog->new_values['product_name']);
        $this->assertSame('opening_balance', $auditLog->new_values['movement_type']);
        $this->assertSame('25.0000', $auditLog->new_values['quantity']);
        $this->assertSame('0.0000', $auditLog->new_values['previous_quantity']);
        $this->assertSame('25.0000', $auditLog->new_values['new_quantity']);
        $this->assertSame('Initial opening stock.', $auditLog->new_values['remarks']);
        $this->assertSame([
            'model' => 'stock_adjustment',
            'stock_movement_id' => $movement->id,
            'warehouse_stock_id' => $stock->id,
        ], $auditLog->metadata);
    }

    public function test_adjustment_in_creates_audit_log(): void
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

        $movement = StockMovement::query()->firstOrFail();
        $auditLog = $this->latestAuditLog();

        $this->assertSame($movement->id, $auditLog->auditable_id);
        $this->assertSame('stock_adjusted', $auditLog->event);
        $this->assertSame('stock_adjustments', $auditLog->module);
        $this->assertSame('adjustment_in', $auditLog->new_values['movement_type']);
        $this->assertSame('4.5000', $auditLog->new_values['quantity']);
        $this->assertSame('10.0000', $auditLog->new_values['previous_quantity']);
        $this->assertSame('14.5000', $auditLog->new_values['new_quantity']);
        $this->assertSame($stock->id, $auditLog->metadata['warehouse_stock_id']);
    }

    public function test_adjustment_out_creates_audit_log(): void
    {
        [$warehouse, $product] = $this->warehouseStock(quantity: 10, reservedQuantity: 2);
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

        $movement = StockMovement::query()->firstOrFail();
        $auditLog = $this->latestAuditLog();

        $this->assertSame($movement->id, $auditLog->auditable_id);
        $this->assertSame('adjustment_out', $auditLog->new_values['movement_type']);
        $this->assertSame('3.0000', $auditLog->new_values['quantity']);
        $this->assertSame('10.0000', $auditLog->new_values['previous_quantity']);
        $this->assertSame('7.0000', $auditLog->new_values['new_quantity']);
        $this->assertSame('Manual count decrease.', $auditLog->new_values['remarks']);
    }

    public function test_failed_adjustment_out_below_zero_does_not_create_audit_log(): void
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
        $this->assertSame(0, AuditLog::query()->where('module', 'stock_adjustments')->count());
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
            'description' => 'Temporary role for stock adjustment audit log tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function latestAuditLog(): AuditLog
    {
        return AuditLog::query()
            ->where('module', 'stock_adjustments')
            ->where('event', 'stock_adjusted')
            ->latest('id')
            ->firstOrFail();
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

    private function warehouse(?string $code = null, string $name = 'Test Warehouse'): Warehouse
    {
        return Warehouse::query()->create([
            'code' => $code ?? 'WH-'.Str::upper(Str::random(8)),
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function product(string $name = 'Test Product', ?string $sku = null): Product
    {
        $sku ??= 'SKU-'.Str::upper(Str::random(8));
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
            'slug' => Str::slug($name.' '.$sku),
            'sku' => $sku,
            'description' => 'Test stock adjustment audit product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ]);
    }
}
