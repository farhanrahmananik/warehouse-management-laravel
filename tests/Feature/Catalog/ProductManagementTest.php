<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductManagementTest extends TestCase
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

    public function test_guest_is_redirected_from_products_index_to_login(): void
    {
        $this->get(route('products.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_products_view_permission_receives_403_from_products_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertForbidden();
    }

    public function test_user_with_products_view_permission_can_view_products_index(): void
    {
        $user = $this->userWithPermissions(['products.view']);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Products');
    }

    public function test_user_with_products_create_permission_can_view_product_create_page(): void
    {
        $this->category();
        $this->unit();
        $user = $this->userWithPermissions(['products.create']);

        $this->actingAs($user)
            ->get(route('products.create'))
            ->assertOk()
            ->assertSee('Create Product');
    }

    public function test_user_with_products_create_permission_can_create_product(): void
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

        $this->assertDatabaseHas('products', [
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'USB Keyboard',
            'slug' => 'usb-keyboard',
            'sku' => 'USB-KEYBOARD-001',
            'barcode' => '1234567890123',
            'purchase_price' => 10.50,
            'selling_price' => 15.99,
            'reorder_level' => 5.00,
        ]);
    }

    public function test_product_creation_validates_required_fields(): void
    {
        $user = $this->userWithPermissions(['products.create']);

        $this->actingAs($user)
            ->post(route('products.store'), [])
            ->assertSessionHasErrors([
                'category_id',
                'unit_id',
                'name',
                'sku',
            ]);
    }

    public function test_product_creation_defaults_blank_numeric_fields_to_zero(): void
    {
        $category = $this->category('Temporary Category', 'temporary-category');
        $unit = $this->unit('Box', 'box');
        $user = $this->userWithPermissions(['products.create']);

        $this->actingAs($user)
            ->post(route('products.store'), [
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'name' => 'Temporary Product',
                'sku' => 'TEMP-PRODUCT-001',
                'purchase_price' => '',
                'selling_price' => '',
                'reorder_level' => '',
                'is_active' => true,
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'name' => 'Temporary Product',
            'sku' => 'TEMP-PRODUCT-001',
            'purchase_price' => 0.00,
            'selling_price' => 0.00,
            'reorder_level' => 0.00,
        ]);
    }

    public function test_user_with_products_update_permission_can_update_product(): void
    {
        $product = $this->product();
        $user = $this->userWithPermissions(['products.update']);

        $this->actingAs($user)
            ->put(route('products.update', $product), [
                'category_id' => $product->category_id,
                'unit_id' => $product->unit_id,
                'name' => 'Wireless Keyboard',
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'description' => 'Updated wireless keyboard',
                'purchase_price' => $product->purchase_price,
                'selling_price' => 29.99,
                'reorder_level' => $product->reorder_level,
                'is_active' => true,
            ])
            ->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Wireless Keyboard',
            'slug' => 'wireless-keyboard',
            'description' => 'Updated wireless keyboard',
            'selling_price' => 29.99,
        ]);
    }

    public function test_user_with_products_delete_permission_can_soft_delete_product(): void
    {
        $product = $this->product();
        $user = $this->userWithPermissions(['products.delete']);

        $this->actingAs($user)
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
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
            'description' => 'Temporary role for product management tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
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

    private function category(string $name = 'Existing Category', string $slug = 'existing-category'): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => 'Existing category description',
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
