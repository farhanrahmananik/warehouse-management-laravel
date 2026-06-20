<?php

namespace Tests\Feature\Catalog;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
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

    public function test_guest_is_redirected_from_suppliers_index_to_login(): void
    {
        $this->get(route('suppliers.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_suppliers_view_permission_receives_403_from_suppliers_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('suppliers.index'))
            ->assertForbidden();
    }

    public function test_user_with_suppliers_view_permission_can_view_suppliers_index(): void
    {
        $user = $this->userWithPermissions(['suppliers.view']);

        $this->actingAs($user)
            ->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('Suppliers');
    }

    public function test_user_with_suppliers_create_permission_can_create_supplier(): void
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

        $this->assertDatabaseHas('suppliers', [
            'name' => 'ABC Supplies',
            'company_name' => 'ABC Supplies GmbH',
            'opening_balance' => 100.00,
            'current_balance' => 100.00,
        ]);
    }

    public function test_supplier_creation_validates_required_name(): void
    {
        $user = $this->userWithPermissions(['suppliers.create']);

        $this->actingAs($user)
            ->post(route('suppliers.store'), [
                'name' => '',
                'company_name' => 'Missing Name GmbH',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_user_with_suppliers_update_permission_can_update_supplier(): void
    {
        $supplier = $this->supplier();
        $user = $this->userWithPermissions(['suppliers.update']);

        $this->actingAs($user)
            ->put(route('suppliers.update', $supplier), [
                'name' => $supplier->name,
                'company_name' => 'Updated Supplier GmbH',
                'email' => $supplier->email,
                'phone' => '+49 987 654321',
                'address' => $supplier->address,
                'tax_number' => $supplier->tax_number,
                'is_active' => true,
            ])
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'company_name' => 'Updated Supplier GmbH',
            'phone' => '+49 987 654321',
        ]);
    }

    public function test_user_with_suppliers_delete_permission_can_soft_delete_supplier(): void
    {
        $supplier = $this->supplier();
        $user = $this->userWithPermissions(['suppliers.delete']);

        $this->actingAs($user)
            ->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'));

        $this->assertSoftDeleted('suppliers', [
            'id' => $supplier->id,
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
            'description' => 'Temporary role for supplier management tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
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
}
