<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_passes_any_arbitrary_permission_gate(): void
    {
        $user = $this->userWithRole('super-admin');

        $this->assertTrue(Gate::forUser($user)->allows('permission', 'anything.at.all'));
    }

    public function test_user_with_manager_role_passes_role_gate_for_manager(): void
    {
        $user = $this->userWithRole('manager');

        $this->assertTrue(Gate::forUser($user)->allows('role', 'manager'));
    }

    public function test_user_without_manager_role_fails_role_gate_for_manager(): void
    {
        $user = $this->userWithRole('viewer');

        $this->assertFalse(Gate::forUser($user)->allows('role', 'manager'));
    }

    public function test_user_with_dashboard_view_permission_through_role_passes_permission_gate(): void
    {
        $user = $this->userWithRole('viewer');

        $this->assertTrue(Gate::forUser($user)->allows('permission', 'dashboard.view'));
    }

    public function test_user_without_dashboard_view_permission_fails_permission_gate(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('permission', 'dashboard.view'));
    }

    public function test_empty_role_gate_fails_for_non_super_admin_user(): void
    {
        $user = $this->userWithRole('viewer');

        $this->assertFalse(Gate::forUser($user)->allows('role'));
    }

    public function test_empty_permission_gate_fails_for_non_super_admin_user(): void
    {
        $user = $this->userWithRole('viewer');

        $this->assertFalse(Gate::forUser($user)->allows('permission'));
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
