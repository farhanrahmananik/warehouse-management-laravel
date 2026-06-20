<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
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

    public function test_guest_is_redirected_from_categories_index_to_login(): void
    {
        $this->get(route('categories.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_categories_view_permission_receives_403_from_categories_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertForbidden();
    }

    public function test_user_with_categories_view_permission_can_view_categories_index(): void
    {
        $user = $this->userWithPermissions(['categories.view']);

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Categories');
    }

    public function test_user_with_categories_create_permission_can_create_category(): void
    {
        $user = $this->userWithPermissions(['categories.create']);

        $this->actingAs($user)
            ->post(route('categories.store'), [
                'name' => 'Electronics',
                'description' => 'Electronic products',
                'is_active' => true,
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Electronic products',
        ]);
    }

    public function test_category_creation_validates_required_name(): void
    {
        $user = $this->userWithPermissions(['categories.create']);

        $this->actingAs($user)
            ->post(route('categories.store'), [
                'name' => '',
                'description' => 'Missing category name',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_user_with_categories_update_permission_can_update_category(): void
    {
        $category = $this->category();
        $user = $this->userWithPermissions(['categories.update']);

        $this->actingAs($user)
            ->put(route('categories.update', $category), [
                'name' => 'Office Supplies',
                'description' => 'Updated office products',
                'is_active' => true,
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Office Supplies',
            'slug' => 'office-supplies',
            'description' => 'Updated office products',
        ]);
    }

    public function test_user_with_categories_delete_permission_can_soft_delete_category(): void
    {
        $category = $this->category();
        $user = $this->userWithPermissions(['categories.delete']);

        $this->actingAs($user)
            ->delete(route('categories.destroy', $category))
            ->assertRedirect(route('categories.index'));

        $this->assertSoftDeleted('categories', [
            'id' => $category->id,
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
            'description' => 'Temporary role for category management tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function category(): Category
    {
        return Category::query()->create([
            'name' => 'Existing Category',
            'slug' => 'existing-category',
            'description' => 'Existing category description',
            'is_active' => true,
        ]);
    }
}
