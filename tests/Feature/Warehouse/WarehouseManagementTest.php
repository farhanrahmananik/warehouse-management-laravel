<?php

namespace Tests\Feature\Warehouse;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
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

    public function test_guest_is_redirected_from_warehouses_index_to_login(): void
    {
        $this->get(route('warehouses.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_warehouses_view_permission_receives_403_from_warehouses_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('warehouses.index'))
            ->assertForbidden();
    }

    public function test_user_with_warehouses_view_permission_can_view_warehouses_index(): void
    {
        $user = $this->userWithPermissions(['warehouses.view']);

        $this->actingAs($user)
            ->get(route('warehouses.index'))
            ->assertOk()
            ->assertSee('Warehouses');
    }

    public function test_user_with_warehouses_create_permission_can_view_warehouse_create_page(): void
    {
        $user = $this->userWithPermissions(['warehouses.create']);

        $this->actingAs($user)
            ->get(route('warehouses.create'))
            ->assertOk()
            ->assertSee('Create Warehouse');
    }

    public function test_user_with_warehouses_create_permission_can_create_warehouse(): void
    {
        $user = $this->userWithPermissions(['warehouses.create']);

        $this->actingAs($user)
            ->post(route('warehouses.store'), [
                'code' => 'WH-001',
                'name' => 'Main Warehouse',
                'contact_person' => 'Jane Manager',
                'phone' => '+49 123 456789',
                'email' => 'warehouse@example.com',
                'address' => 'Berlin, Germany',
                'city' => 'Berlin',
                'is_active' => true,
            ])
            ->assertRedirect(route('warehouses.index'));

        $this->assertDatabaseHas('warehouses', [
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
            'contact_person' => 'Jane Manager',
            'city' => 'Berlin',
            'is_active' => true,
        ]);
    }

    public function test_warehouse_creation_validates_required_code_and_name(): void
    {
        $user = $this->userWithPermissions(['warehouses.create']);

        $this->actingAs($user)
            ->post(route('warehouses.store'), [])
            ->assertSessionHasErrors([
                'code',
                'name',
            ]);
    }

    public function test_user_with_warehouses_update_permission_can_update_warehouse(): void
    {
        $warehouse = $this->warehouse();
        $user = $this->userWithPermissions(['warehouses.update']);

        $this->actingAs($user)
            ->put(route('warehouses.update', $warehouse), [
                'code' => 'WH-UPDATED',
                'name' => 'Updated Warehouse',
                'contact_person' => 'Updated Manager',
                'phone' => $warehouse->phone,
                'email' => $warehouse->email,
                'address' => $warehouse->address,
                'city' => 'Hamburg',
                'is_active' => true,
            ])
            ->assertRedirect(route('warehouses.index'));

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'code' => 'WH-UPDATED',
            'name' => 'Updated Warehouse',
            'contact_person' => 'Updated Manager',
            'city' => 'Hamburg',
        ]);
    }

    public function test_user_with_warehouses_delete_permission_can_soft_delete_warehouse(): void
    {
        $warehouse = $this->warehouse();
        $user = $this->userWithPermissions(['warehouses.delete']);

        $this->actingAs($user)
            ->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect(route('warehouses.index'));

        $this->assertSoftDeleted('warehouses', [
            'id' => $warehouse->id,
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
            'description' => 'Temporary role for warehouse management tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function warehouse(): Warehouse
    {
        return Warehouse::query()->create([
            'code' => 'WH-EXISTING',
            'name' => 'Existing Warehouse',
            'contact_person' => 'Existing Manager',
            'phone' => '+49 111 222333',
            'email' => 'existing-warehouse@example.com',
            'address' => 'Munich, Germany',
            'city' => 'Munich',
            'is_active' => true,
        ]);
    }
}
