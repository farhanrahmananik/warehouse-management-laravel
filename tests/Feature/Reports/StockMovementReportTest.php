<?php

namespace Tests\Feature\Reports;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockIn;
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

class StockMovementReportTest extends TestCase
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

    public function test_user_with_stock_movement_report_permission_can_see_report_data(): void
    {
        $user = $this->userWithPermissions(['reports.stock-movements.view']);
        $movement = $this->stockMovement(
            warehouseName: 'Report Movement Warehouse',
            warehouseCode: 'RPT-MOVE-WH-001',
            productName: 'Report Movement Product',
            productSlug: 'report-movement-product',
            sku: 'RPT-MOVE-001',
            movementType: 'stock_in',
            quantity: 12.5,
            balanceAfter: 22.5,
            remarks: 'Report stock movement note.',
            creatorName: 'Report Creator',
            referenceType: StockIn::class,
            referenceId: 123,
            createdAt: '2026-06-15 09:30:00',
        );

        $this->actingAs($user)
            ->get(route('reports.stock-movements'))
            ->assertOk()
            ->assertSeeText('Stock Movement Report')
            ->assertSeeText('Report Movement Warehouse')
            ->assertSeeText('RPT-MOVE-WH-001')
            ->assertSeeText('Report Movement Product')
            ->assertSeeText('RPT-MOVE-001')
            ->assertSeeText('Stock In')
            ->assertSeeText('12.5000')
            ->assertSeeText('22.5000')
            ->assertSeeText('StockIn #123')
            ->assertSeeText('Report Creator')
            ->assertSeeText('Report stock movement note.')
            ->assertViewHas('movementRows', function ($movementRows) use ($movement): bool {
                return $movementRows->count() === 1
                    && $movementRows->getCollection()->first()->is($movement);
            });
    }

    public function test_stock_movement_report_supports_warehouse_filter(): void
    {
        $user = $this->userWithPermissions(['reports.stock-movements.view']);
        $matchingMovement = $this->stockMovement(
            warehouseName: 'Matching Movement Warehouse',
            warehouseCode: 'MATCH-MOVE-WH',
            productName: 'Warehouse Movement Product',
            productSlug: 'warehouse-movement-product',
            sku: 'WAREHOUSE-MOVE-001',
        );
        $this->stockMovement(
            warehouseName: 'Other Movement Warehouse',
            warehouseCode: 'OTHER-MOVE-WH',
            productName: 'Other Warehouse Movement Product',
            productSlug: 'other-warehouse-movement-product',
            sku: 'WAREHOUSE-MOVE-002',
        );

        $this->actingAs($user)
            ->get(route('reports.stock-movements', ['warehouse_id' => $matchingMovement->warehouse_id]))
            ->assertOk()
            ->assertSeeText('Matching Movement Warehouse')
            ->assertViewHas('movementRows', function ($movementRows) use ($matchingMovement): bool {
                return $movementRows->count() === 1
                    && $movementRows->getCollection()->first()->is($matchingMovement);
            });
    }

    public function test_stock_movement_report_supports_product_filter(): void
    {
        $user = $this->userWithPermissions(['reports.stock-movements.view']);
        $matchingMovement = $this->stockMovement(
            warehouseName: 'Product Filter Warehouse',
            warehouseCode: 'PRODUCT-MOVE-WH',
            productName: 'Matching Movement Product',
            productSlug: 'matching-movement-product',
            sku: 'PRODUCT-MOVE-001',
        );
        $this->stockMovement(
            warehouseName: 'Other Product Filter Warehouse',
            warehouseCode: 'PRODUCT-MOVE-WH-2',
            productName: 'Other Movement Product',
            productSlug: 'other-movement-product',
            sku: 'PRODUCT-MOVE-002',
        );

        $this->actingAs($user)
            ->get(route('reports.stock-movements', ['product_id' => $matchingMovement->product_id]))
            ->assertOk()
            ->assertSeeText('Matching Movement Product')
            ->assertViewHas('movementRows', function ($movementRows) use ($matchingMovement): bool {
                return $movementRows->count() === 1
                    && $movementRows->getCollection()->first()->is($matchingMovement);
            });
    }

    public function test_stock_movement_report_supports_movement_type_filter(): void
    {
        $user = $this->userWithPermissions(['reports.stock-movements.view']);
        $matchingMovement = $this->stockMovement(
            warehouseName: 'Type Filter Warehouse',
            warehouseCode: 'TYPE-MOVE-WH',
            productName: 'Type Filter Product',
            productSlug: 'type-filter-product',
            sku: 'TYPE-MOVE-001',
            movementType: 'adjustment_in',
        );
        $this->stockMovement(
            warehouseName: 'Other Type Filter Warehouse',
            warehouseCode: 'TYPE-MOVE-WH-2',
            productName: 'Other Type Filter Product',
            productSlug: 'other-type-filter-product',
            sku: 'TYPE-MOVE-002',
            movementType: 'adjustment_out',
        );

        $this->actingAs($user)
            ->get(route('reports.stock-movements', ['movement_type' => 'adjustment_in']))
            ->assertOk()
            ->assertSeeText('Adjustment In')
            ->assertViewHas('movementRows', function ($movementRows) use ($matchingMovement): bool {
                return $movementRows->count() === 1
                    && $movementRows->getCollection()->first()->is($matchingMovement);
            });
    }

    public function test_stock_movement_report_supports_date_range_filter(): void
    {
        $user = $this->userWithPermissions(['reports.stock-movements.view']);
        $matchingMovement = $this->stockMovement(
            warehouseName: 'Date Filter Warehouse',
            warehouseCode: 'DATE-MOVE-WH',
            productName: 'Date Filter Product',
            productSlug: 'date-filter-product',
            sku: 'DATE-MOVE-001',
            createdAt: '2026-06-15 10:30:00',
        );
        $this->stockMovement(
            warehouseName: 'Other Date Filter Warehouse',
            warehouseCode: 'DATE-MOVE-WH-2',
            productName: 'Other Date Filter Product',
            productSlug: 'other-date-filter-product',
            sku: 'DATE-MOVE-002',
            createdAt: '2026-06-18 10:30:00',
        );

        $this->actingAs($user)
            ->get(route('reports.stock-movements', [
                'date_from' => '2026-06-15',
                'date_to' => '2026-06-15',
            ]))
            ->assertOk()
            ->assertViewHas('movementRows', function ($movementRows) use ($matchingMovement): bool {
                return $movementRows->count() === 1
                    && $movementRows->getCollection()->first()->is($matchingMovement);
            });
    }

    public function test_stock_movement_report_rejects_invalid_movement_type_filter(): void
    {
        $user = $this->userWithPermissions(['reports.stock-movements.view']);

        $this->actingAs($user)
            ->from(route('reports.stock-movements'))
            ->get(route('reports.stock-movements', ['movement_type' => 'invalid-type']))
            ->assertRedirect(route('reports.stock-movements'))
            ->assertSessionHasErrors('movement_type');
    }

    public function test_stock_movement_report_rejects_date_to_before_date_from(): void
    {
        $user = $this->userWithPermissions(['reports.stock-movements.view']);

        $this->actingAs($user)
            ->from(route('reports.stock-movements'))
            ->get(route('reports.stock-movements', [
                'date_from' => '2026-06-20',
                'date_to' => '2026-06-15',
            ]))
            ->assertRedirect(route('reports.stock-movements'))
            ->assertSessionHasErrors('date_to');
    }

    public function test_user_without_stock_movement_report_permission_receives_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.stock-movements'))
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
            'description' => 'Temporary role for stock movement report tests.',
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
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $createdAt = null,
    ): StockMovement {
        $warehouse = Warehouse::query()->create([
            'code' => $warehouseCode,
            'name' => $warehouseName,
            'is_active' => true,
        ]);
        $product = $this->product($productName, $productSlug, $sku);
        $creator = User::factory()->create([
            'name' => $creatorName ?? 'Report Stock User',
        ]);

        $movement = StockMovement::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
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
            'description' => 'Stock movement report test product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ]);
    }
}
