<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BladeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config([
            'seeding.super_admin.name' => 'Seed Super Admin',
            'seeding.super_admin.email' => 'seed-super-admin@example.com',
            'seeding.super_admin.password' => 'password',
        ]);

        $this->seed(DatabaseSeeder::class);
    }

    public function test_super_admin_user_can_see_dashboard_and_super_admin_authorized_content(): void
    {
        $user = $this->userWithRole('super-admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard permission verified')
            ->assertSee('Super Admin access enabled');
    }

    public function test_viewer_user_can_see_dashboard_permission_content_but_not_super_admin_content(): void
    {
        $user = $this->userWithRole('viewer');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard permission verified')
            ->assertDontSee('Super Admin access enabled');
    }

    public function test_user_without_dashboard_view_permission_cannot_access_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    private function userWithRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
