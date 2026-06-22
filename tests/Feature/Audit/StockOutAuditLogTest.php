<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\StockOut;
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

class StockOutAuditLogTest extends TestCase
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

    public function test_creating_a_stock_out_document_creates_exactly_one_audit_log(): void
    {
        $warehouse = $this->warehouse('WH-OUT-001', 'Issuing Warehouse');
        $product = $this->product('Issued Product', 'ISSUE-001');
        $this->warehouseStock($warehouse, $product, quantity: 10, reservedQuantity: 2);
        $user = $this->userWithPermissions(['stock-out.create']);

        $response = $this->actingAs($user)
            ->post(route('stock-outs.store'), $this->validStockOutPayload($warehouse, $product, 5));

        $stockOut = StockOut::query()->firstOrFail();
        $movement = StockMovement::query()->firstOrFail();

        $response->assertRedirect(route('stock-outs.show', $stockOut));

        $this->assertSame(1, AuditLog::query()->where('module', 'stock_outs')->count());

        $auditLog = $this->latestAuditLog();

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($stockOut->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($stockOut->id, $auditLog->auditable_id);
        $this->assertSame('stock_out_created', $auditLog->event);
        $this->assertSame('stock_outs', $auditLog->module);
        $this->assertSame(
            sprintf('Stock out "%s" was created from warehouse "Issuing Warehouse".', $stockOut->document_no),
            $auditLog->description,
        );
        $this->assertSame([
            'model' => 'stock_out',
            'stock_out_id' => $stockOut->id,
            'warehouse_id' => $warehouse->id,
            'movement_ids' => [$movement->id],
        ], $auditLog->metadata);
    }

    public function test_audit_log_contains_document_warehouse_item_product_and_quantity_context(): void
    {
        $warehouse = $this->warehouse('WH-OUT-002', 'Context Out Warehouse');
        $firstProduct = $this->product('First Out Product', 'OUT-CONTEXT-001');
        $secondProduct = $this->product('Second Out Product', 'OUT-CONTEXT-002');
        $this->warehouseStock($warehouse, $firstProduct, quantity: 10, reservedQuantity: 1);
        $this->warehouseStock($warehouse, $secondProduct, quantity: 12, reservedQuantity: 2);
        $user = $this->userWithPermissions(['stock-out.create']);

        $this->actingAs($user)
            ->post(route('stock-outs.store'), [
                'warehouse_id' => $warehouse->id,
                'stock_date' => '2026-06-22',
                'remarks' => 'Document level stock out remarks.',
                'items' => [
                    [
                        'product_id' => $firstProduct->id,
                        'quantity' => 2.5,
                        'remarks' => 'First out item remarks.',
                    ],
                    [
                        'product_id' => $secondProduct->id,
                        'quantity' => 3.75,
                        'remarks' => 'Second out item remarks.',
                    ],
                ],
            ])
            ->assertRedirect();

        $stockOut = StockOut::query()->firstOrFail();
        $auditLog = $this->latestAuditLog();

        $this->assertSame($stockOut->id, $auditLog->new_values['stock_out_id']);
        $this->assertSame($stockOut->document_no, $auditLog->new_values['reference_no']);
        $this->assertSame($warehouse->id, $auditLog->new_values['warehouse_id']);
        $this->assertSame('Context Out Warehouse', $auditLog->new_values['warehouse_name']);
        $this->assertSame('2026-06-22', $auditLog->new_values['stock_out_date']);
        $this->assertSame(2, $auditLog->new_values['total_items']);
        $this->assertSame('6.2500', $auditLog->new_values['total_quantity']);
        $this->assertSame('Document level stock out remarks.', $auditLog->new_values['remarks']);
        $this->assertCount(2, $auditLog->new_values['items']);
        $this->assertSame($firstProduct->id, $auditLog->new_values['items'][0]['product_id']);
        $this->assertSame('First Out Product', $auditLog->new_values['items'][0]['product_name']);
        $this->assertSame('2.5000', $auditLog->new_values['items'][0]['quantity']);
        $this->assertSame('First out item remarks.', $auditLog->new_values['items'][0]['remarks']);
        $this->assertSame($secondProduct->id, $auditLog->new_values['items'][1]['product_id']);
        $this->assertSame('Second Out Product', $auditLog->new_values['items'][1]['product_name']);
        $this->assertSame('3.7500', $auditLog->new_values['items'][1]['quantity']);
        $this->assertSame('Second out item remarks.', $auditLog->new_values['items'][1]['remarks']);
    }

    public function test_failed_stock_out_validation_does_not_create_audit_log(): void
    {
        $user = $this->userWithPermissions(['stock-out.create']);

        $this->actingAs($user)
            ->from(route('stock-outs.create'))
            ->post(route('stock-outs.store'), [])
            ->assertRedirect(route('stock-outs.create'))
            ->assertSessionHasErrors([
                'warehouse_id',
                'stock_date',
                'items',
            ]);

        $this->assertSame(0, StockOut::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'stock_outs')->count());
    }

    public function test_failed_stock_out_for_insufficient_stock_does_not_create_audit_log(): void
    {
        $warehouse = $this->warehouse('WH-OUT-003', 'Insufficient Warehouse');
        $product = $this->product('Insufficient Product', 'INSUFFICIENT-001');
        $stock = $this->warehouseStock($warehouse, $product, quantity: 3, reservedQuantity: 0);
        $user = $this->userWithPermissions(['stock-out.create']);

        $this->actingAs($user)
            ->from(route('stock-outs.create'))
            ->post(route('stock-outs.store'), $this->validStockOutPayload($warehouse, $product, 4))
            ->assertRedirect(route('stock-outs.create'))
            ->assertSessionHasErrors(['items.0.quantity']);

        $this->assertSame('3.0000', $stock->refresh()->quantity);
        $this->assertSame(0, StockOut::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'stock_outs')->count());
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
            'description' => 'Temporary role for stock out audit log tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function latestAuditLog(): AuditLog
    {
        return AuditLog::query()
            ->where('module', 'stock_outs')
            ->where('event', 'stock_out_created')
            ->latest('id')
            ->firstOrFail();
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
            'description' => 'Test stock out audit product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ]);
    }

    private function warehouseStock(
        Warehouse $warehouse,
        Product $product,
        float|int $quantity,
        float|int $reservedQuantity,
    ): WarehouseStock {
        return WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'reserved_quantity' => $reservedQuantity,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validStockOutPayload(Warehouse $warehouse, Product $product, float|int $quantity): array
    {
        return [
            'warehouse_id' => $warehouse->id,
            'stock_date' => now()->toDateString(),
            'remarks' => 'Header stock out remarks.',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'remarks' => 'Item stock out remarks.',
                ],
            ],
        ];
    }
}
