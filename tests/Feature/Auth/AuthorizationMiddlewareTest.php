<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationMiddlewareTest extends TestCase
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

    public function test_authenticated_super_admin_can_access_dashboard(): void
    {
        $user = $this->userWithRole('super-admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Warehouse Management Dashboard');
    }

    public function test_authenticated_user_without_dashboard_view_permission_receives_403_from_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_role_middleware_allows_user_with_super_admin_role(): void
    {
        $this->registerTestRoute('/test-role-allows', ['auth', 'role:super-admin']);

        $this->actingAs($this->userWithRole('super-admin'))
            ->get('/test-role-allows')
            ->assertOk()
            ->assertSee('authorized');
    }

    public function test_role_middleware_denies_user_without_required_role(): void
    {
        $this->registerTestRoute('/test-role-denies', ['auth', 'role:super-admin']);

        $this->actingAs($this->userWithRole('viewer'))
            ->get('/test-role-denies')
            ->assertForbidden();
    }

    public function test_permission_middleware_allows_user_with_dashboard_view_permission_through_role(): void
    {
        $this->registerTestRoute('/test-permission-allows', ['auth', 'permission:dashboard.view']);

        $this->actingAs($this->userWithRole('viewer'))
            ->get('/test-permission-allows')
            ->assertOk()
            ->assertSee('authorized');
    }

    public function test_permission_middleware_denies_user_without_required_permission(): void
    {
        $this->registerTestRoute('/test-permission-denies', ['auth', 'permission:users.delete']);

        $this->actingAs($this->userWithRole('viewer'))
            ->get('/test-permission-denies')
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $middleware
     */
    private function registerTestRoute(string $uri, array $middleware): void
    {
        Route::middleware($middleware)->get($uri, fn () => 'authorized');
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
