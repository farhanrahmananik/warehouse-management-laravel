<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
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
use Illuminate\Support\Str;
use Tests\TestCase;

class StockInAuditLogTest extends TestCase
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

    public function test_creating_a_stock_in_document_creates_exactly_one_audit_log(): void
    {
        $warehouse = $this->warehouse('WH-IN-001', 'Receiving Warehouse');
        $product = $this->product('Receiving Product', 'RECEIVE-001');
        $user = $this->userWithPermissions(['stock-in.create']);

        $response = $this->actingAs($user)
            ->post(route('stock-ins.store'), $this->validStockInPayload($warehouse, $product, 6.25));

        $stockIn = StockIn::query()->firstOrFail();
        $movement = StockMovement::query()->firstOrFail();

        $response->assertRedirect(route('stock-ins.show', $stockIn));

        $this->assertSame(1, AuditLog::query()->where('module', 'stock_ins')->count());

        $auditLog = $this->latestAuditLog();

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($stockIn->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($stockIn->id, $auditLog->auditable_id);
        $this->assertSame('stock_in_created', $auditLog->event);
        $this->assertSame('stock_ins', $auditLog->module);
        $this->assertSame(
            sprintf('Stock in "%s" was created for warehouse "Receiving Warehouse".', $stockIn->document_no),
            $auditLog->description,
        );
        $this->assertSame([
            'model' => 'stock_in',
            'stock_in_id' => $stockIn->id,
            'warehouse_id' => $warehouse->id,
            'movement_ids' => [$movement->id],
        ], $auditLog->metadata);
    }

    public function test_audit_log_contains_document_warehouse_item_product_and_quantity_context(): void
    {
        $warehouse = $this->warehouse('WH-IN-002', 'Context Warehouse');
        $firstProduct = $this->product('First Context Product', 'CONTEXT-001');
        $secondProduct = $this->product('Second Context Product', 'CONTEXT-002');
        $user = $this->userWithPermissions(['stock-in.create']);

        $this->actingAs($user)
            ->post(route('stock-ins.store'), [
                'warehouse_id' => $warehouse->id,
                'stock_date' => '2026-06-22',
                'remarks' => 'Document level stock in remarks.',
                'items' => [
                    [
                        'product_id' => $firstProduct->id,
                        'quantity' => 2.5,
                        'remarks' => 'First item remarks.',
                    ],
                    [
                        'product_id' => $secondProduct->id,
                        'quantity' => 3.75,
                        'remarks' => 'Second item remarks.',
                    ],
                ],
            ])
            ->assertRedirect();

        $stockIn = StockIn::query()->firstOrFail();
        $auditLog = $this->latestAuditLog();

        $this->assertSame($stockIn->id, $auditLog->new_values['stock_in_id']);
        $this->assertSame($stockIn->document_no, $auditLog->new_values['reference_no']);
        $this->assertSame($warehouse->id, $auditLog->new_values['warehouse_id']);
        $this->assertSame('Context Warehouse', $auditLog->new_values['warehouse_name']);
        $this->assertSame('2026-06-22', $auditLog->new_values['stock_in_date']);
        $this->assertSame(2, $auditLog->new_values['total_items']);
        $this->assertSame('6.2500', $auditLog->new_values['total_quantity']);
        $this->assertSame('Document level stock in remarks.', $auditLog->new_values['remarks']);
        $this->assertCount(2, $auditLog->new_values['items']);
        $this->assertSame($firstProduct->id, $auditLog->new_values['items'][0]['product_id']);
        $this->assertSame('First Context Product', $auditLog->new_values['items'][0]['product_name']);
        $this->assertSame('2.5000', $auditLog->new_values['items'][0]['quantity']);
        $this->assertSame('First item remarks.', $auditLog->new_values['items'][0]['remarks']);
        $this->assertSame($secondProduct->id, $auditLog->new_values['items'][1]['product_id']);
        $this->assertSame('Second Context Product', $auditLog->new_values['items'][1]['product_name']);
        $this->assertSame('3.7500', $auditLog->new_values['items'][1]['quantity']);
        $this->assertSame('Second item remarks.', $auditLog->new_values['items'][1]['remarks']);
    }

    public function test_failed_stock_in_validation_does_not_create_audit_log(): void
    {
        $user = $this->userWithPermissions(['stock-in.create']);

        $this->actingAs($user)
            ->from(route('stock-ins.create'))
            ->post(route('stock-ins.store'), [])
            ->assertRedirect(route('stock-ins.create'))
            ->assertSessionHasErrors([
                'warehouse_id',
                'stock_date',
                'items',
            ]);

        $this->assertSame(0, StockIn::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, AuditLog::query()->where('module', 'stock_ins')->count());
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
            'description' => 'Temporary role for stock in audit log tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function latestAuditLog(): AuditLog
    {
        return AuditLog::query()
            ->where('module', 'stock_ins')
            ->where('event', 'stock_in_created')
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
            'description' => 'Test stock in audit product.',
            'purchase_price' => 10,
            'selling_price' => 15,
            'reorder_level' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validStockInPayload(Warehouse $warehouse, Product $product, float|int $quantity): array
    {
        return [
            'warehouse_id' => $warehouse->id,
            'stock_date' => now()->toDateString(),
            'remarks' => 'Header stock in remarks.',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'remarks' => 'Item stock in remarks.',
                ],
            ],
        ];
    }
}
