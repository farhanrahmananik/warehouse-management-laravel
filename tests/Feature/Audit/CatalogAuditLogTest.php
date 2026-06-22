<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogAuditLogTest extends TestCase
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

    public function test_creating_a_category_creates_audit_log(): void
    {
        $user = $this->userWithPermissions(['categories.create']);

        $this->actingAs($user)
            ->post(route('categories.store'), [
                'name' => 'Electronics',
                'description' => 'Electronic products',
                'is_active' => true,
            ])
            ->assertRedirect(route('categories.index'));

        $category = Category::query()->where('slug', 'electronics')->firstOrFail();
        $auditLog = $this->latestAuditLog('categories', 'created');

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($category->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($category->id, $auditLog->auditable_id);
        $this->assertSame('Category "Electronics" was created.', $auditLog->description);
        $this->assertSame('Electronics', $auditLog->new_values['name']);
        $this->assertSame('electronics', $auditLog->new_values['slug']);
        $this->assertSame(['model' => 'category'], $auditLog->metadata);
    }

    public function test_updating_a_category_creates_audit_log_with_changed_values_only(): void
    {
        $category = $this->category();
        $user = $this->userWithPermissions(['categories.update']);

        $this->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => $category->name,
                'description' => 'Updated category description',
                'is_active' => true,
            ])
            ->assertRedirect(route('categories.index'));

        $auditLog = $this->latestAuditLog('categories', 'updated');

        $this->assertSame($category->id, $auditLog->auditable_id);
        $this->assertSame('Category "Existing Category" was updated.', $auditLog->description);
        $this->assertSame([
            'description' => 'Existing category description',
        ], $auditLog->old_values);
        $this->assertSame([
            'description' => 'Updated category description',
        ], $auditLog->new_values);
    }

    public function test_deleting_a_category_creates_audit_log(): void
    {
        $category = $this->category();
        $user = $this->userWithPermissions(['categories.delete']);

        $this->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));

        $auditLog = $this->latestAuditLog('categories', 'deleted');

        $this->assertSame($category->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($category->id, $auditLog->auditable_id);
        $this->assertSame('Category "Existing Category" was deleted.', $auditLog->description);
        $this->assertSame('Existing Category', $auditLog->old_values['name']);
        $this->assertSame('existing-category', $auditLog->old_values['slug']);
    }

    public function test_creating_a_supplier_creates_audit_log(): void
    {
        $user = $this->userWithPermissions(['suppliers.create']);

        $this->actingAs($user)
            ->post(route('suppliers.store'), [
                'name' => 'ABC Supplies',
                'company_name' => 'ABC Supplies GmbH',
                'email' => 'abc@example.com',
                'phone' => '+49 123 456789',
                'address' => 'Berlin, Germany',
                'tax_number' => 'TAX-12345',
                'opening_balance' => 100,
                'is_active' => true,
            ])
            ->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::query()->where('email', 'abc@example.com')->firstOrFail();
        $auditLog = $this->latestAuditLog('suppliers', 'created');

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($supplier->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($supplier->id, $auditLog->auditable_id);
        $this->assertSame('Supplier "ABC Supplies" was created.', $auditLog->description);
        $this->assertSame('ABC Supplies', $auditLog->new_values['name']);
        $this->assertSame('100.00', $auditLog->new_values['current_balance']);
        $this->assertSame(['model' => 'supplier'], $auditLog->metadata);
    }

    public function test_updating_a_supplier_creates_audit_log_with_changed_values_only(): void
    {
        $supplier = $this->supplier();
        $user = $this->userWithPermissions(['suppliers.update']);

        $this->actingAs($user)
            ->put(route('suppliers.update', $supplier), [
                'name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'email' => $supplier->email,
                'phone' => '+49 987 654321',
                'address' => $supplier->address,
                'tax_number' => $supplier->tax_number,
                'is_active' => true,
            ])
            ->assertRedirect(route('suppliers.index'));

        $auditLog = $this->latestAuditLog('suppliers', 'updated');

        $this->assertSame($supplier->id, $auditLog->auditable_id);
        $this->assertSame('Supplier "Existing Supplier" was updated.', $auditLog->description);
        $this->assertSame([
            'phone' => '+49 111 222333',
        ], $auditLog->old_values);
        $this->assertSame([
            'phone' => '+49 987 654321',
        ], $auditLog->new_values);
    }

    public function test_deleting_a_supplier_creates_audit_log(): void
    {
        $supplier = $this->supplier();
        $user = $this->userWithPermissions(['suppliers.delete']);

        $this->actingAs($user)
            ->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'));

        $auditLog = $this->latestAuditLog('suppliers', 'deleted');

        $this->assertSame($supplier->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($supplier->id, $auditLog->auditable_id);
        $this->assertSame('Supplier "Existing Supplier" was deleted.', $auditLog->description);
        $this->assertSame('Existing Supplier', $auditLog->old_values['name']);
        $this->assertSame('existing-supplier@example.com', $auditLog->old_values['email']);
    }

    public function test_creating_a_product_creates_audit_log(): void
    {
        $category = $this->category();
        $unit = $this->unit();
        $user = $this->userWithPermissions(['products.create']);

        $this->actingAs($user)
            ->post(route('products.store'), [
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'name' => 'USB Keyboard',
                'sku' => 'USB-KEYBOARD-001',
                'barcode' => '1234567890123',
                'description' => 'Standard USB keyboard',
                'purchase_price' => 10.50,
                'selling_price' => 15.99,
                'reorder_level' => 5,
                'is_active' => true,
            ])
            ->assertRedirect(route('products.index'));

        $product = Product::query()->where('sku', 'USB-KEYBOARD-001')->firstOrFail();
        $auditLog = $this->latestAuditLog('products', 'created');

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($product->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($product->id, $auditLog->auditable_id);
        $this->assertSame('Product "USB Keyboard" was created.', $auditLog->description);
        $this->assertSame('USB Keyboard', $auditLog->new_values['name']);
        $this->assertSame('USB-KEYBOARD-001', $auditLog->new_values['sku']);
        $this->assertSame(['model' => 'product'], $auditLog->metadata);
    }

    public function test_updating_a_product_creates_audit_log_with_changed_values_only(): void
    {
        $product = $this->product();
        $user = $this->userWithPermissions(['products.update']);

        $this->actingAs($user)
            ->put(route('products.update', $product), [
                'category_id' => $product->category_id,
                'unit_id' => $product->unit_id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'description' => $product->description,
                'purchase_price' => $product->purchase_price,
                'selling_price' => 29.99,
                'reorder_level' => $product->reorder_level,
                'is_active' => true,
            ])
            ->assertRedirect(route('products.index'));

        $auditLog = $this->latestAuditLog('products', 'updated');

        $this->assertSame($product->id, $auditLog->auditable_id);
        $this->assertSame('Product "Existing Product" was updated.', $auditLog->description);
        $this->assertSame([
            'selling_price' => '18.75',
        ], $auditLog->old_values);
        $this->assertSame([
            'selling_price' => '29.99',
        ], $auditLog->new_values);
    }

    public function test_deleting_a_product_creates_audit_log(): void
    {
        $product = $this->product();
        $user = $this->userWithPermissions(['products.delete']);

        $this->actingAs($user)
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $auditLog = $this->latestAuditLog('products', 'deleted');

        $this->assertSame($product->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($product->id, $auditLog->auditable_id);
        $this->assertSame('Product "Existing Product" was deleted.', $auditLog->description);
        $this->assertSame('Existing Product', $auditLog->old_values['name']);
        $this->assertSame('EXISTING-PRODUCT-001', $auditLog->old_values['sku']);
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
            'description' => 'Temporary role for catalog audit log tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function latestAuditLog(string $module, string $event): AuditLog
    {
        return AuditLog::query()
            ->where('module', $module)
            ->where('event', $event)
            ->latest('id')
            ->firstOrFail();
    }

    private function category(string $name = 'Existing Category', string $slug = 'existing-category'): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => 'Existing category description',
            'is_active' => true,
        ]);
    }

    private function supplier(): Supplier
    {
        return Supplier::query()->create([
            'name' => 'Existing Supplier',
            'company_name' => 'Existing Supplier GmbH',
            'email' => 'existing-supplier@example.com',
            'phone' => '+49 111 222333',
            'address' => 'Hamburg, Germany',
            'tax_number' => 'TAX-EXISTING',
            'opening_balance' => 50,
            'current_balance' => 50,
            'is_active' => true,
        ]);
    }

    private function product(): Product
    {
        $category = $this->category();
        $unit = $this->unit();

        return Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Existing Product',
            'slug' => 'existing-product',
            'sku' => 'EXISTING-PRODUCT-001',
            'barcode' => '9876543210123',
            'description' => 'Existing product description',
            'purchase_price' => 12.50,
            'selling_price' => 18.75,
            'reorder_level' => 3,
            'is_active' => true,
        ]);
    }

    private function unit(string $name = 'Piece', string $shortName = 'pcs'): Unit
    {
        return Unit::query()->create([
            'name' => $name,
            'short_name' => $shortName,
            'description' => 'Existing unit description',
            'is_active' => true,
        ]);
    }
}
