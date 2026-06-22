<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
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

    public function test_guest_is_redirected_from_audit_logs_index_to_login(): void
    {
        $this->get(route('audit-logs.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_audit_logs_view_permission_receives_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }

    public function test_user_with_audit_logs_view_permission_can_view_audit_logs_index(): void
    {
        $user = $this->userWithPermissions(['audit_logs.view']);

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSeeText('Audit Logs');
    }

    public function test_index_displays_audit_log_rows(): void
    {
        $user = $this->userWithPermissions(['audit_logs.view']);
        $actor = User::factory()->create([
            'name' => 'Audit Actor',
            'email' => 'audit.actor@example.com',
        ]);
        $auditLog = $this->auditLog([
            'user_id' => $actor->id,
            'event' => 'created',
            'module' => 'products',
            'description' => 'Product was created.',
            'ip_address' => '203.0.113.10',
        ]);

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSeeText('Audit Actor')
            ->assertSeeText('audit.actor@example.com')
            ->assertSeeText('Created')
            ->assertSeeText('Products')
            ->assertSeeText('Product was created.')
            ->assertSeeText('203.0.113.10')
            ->assertViewHas('auditLogs', function ($auditLogs) use ($auditLog): bool {
                return $auditLogs->count() === 1
                    && $auditLogs->getCollection()->first()->is($auditLog);
            });
    }

    public function test_index_supports_module_filter(): void
    {
        $user = $this->userWithPermissions(['audit_logs.view']);
        $matchingAuditLog = $this->auditLog([
            'event' => 'created',
            'module' => 'products',
        ]);
        $this->auditLog([
            'event' => 'created',
            'module' => 'warehouses',
        ]);

        $this->actingAs($user)
            ->get(route('audit-logs.index', ['module' => 'products']))
            ->assertOk()
            ->assertViewHas('auditLogs', function ($auditLogs) use ($matchingAuditLog): bool {
                return $auditLogs->count() === 1
                    && $auditLogs->getCollection()->first()->is($matchingAuditLog);
            });
    }

    public function test_index_supports_event_filter(): void
    {
        $user = $this->userWithPermissions(['audit_logs.view']);
        $matchingAuditLog = $this->auditLog([
            'event' => 'updated',
            'module' => 'products',
        ]);
        $this->auditLog([
            'event' => 'deleted',
            'module' => 'products',
        ]);

        $this->actingAs($user)
            ->get(route('audit-logs.index', ['event' => 'updated']))
            ->assertOk()
            ->assertViewHas('auditLogs', function ($auditLogs) use ($matchingAuditLog): bool {
                return $auditLogs->count() === 1
                    && $auditLogs->getCollection()->first()->is($matchingAuditLog);
            });
    }

    public function test_index_supports_user_filter(): void
    {
        $user = $this->userWithPermissions(['audit_logs.view']);
        $matchingUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $matchingAuditLog = $this->auditLog([
            'user_id' => $matchingUser->id,
            'event' => 'updated',
            'module' => 'users',
        ]);
        $this->auditLog([
            'user_id' => $otherUser->id,
            'event' => 'updated',
            'module' => 'users',
        ]);

        $this->actingAs($user)
            ->get(route('audit-logs.index', ['user_id' => $matchingUser->id]))
            ->assertOk()
            ->assertViewHas('auditLogs', function ($auditLogs) use ($matchingAuditLog): bool {
                return $auditLogs->count() === 1
                    && $auditLogs->getCollection()->first()->is($matchingAuditLog);
            });
    }

    public function test_index_validates_date_to_cannot_be_before_date_from(): void
    {
        $user = $this->userWithPermissions(['audit_logs.view']);

        $this->actingAs($user)
            ->from(route('audit-logs.index'))
            ->get(route('audit-logs.index', [
                'date_from' => '2026-06-20',
                'date_to' => '2026-06-15',
            ]))
            ->assertRedirect(route('audit-logs.index'))
            ->assertSessionHasErrors('date_to');
    }

    public function test_user_with_audit_logs_view_permission_can_view_audit_log_details(): void
    {
        $user = $this->userWithPermissions(['audit_logs.view']);
        $auditLog = $this->auditLog([
            'event' => 'updated',
            'module' => 'products',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'description' => 'Product was updated.',
            'old_values' => ['name' => 'Old Product'],
            'new_values' => ['name' => 'New Product'],
            'metadata' => ['source' => 'feature-test'],
            'ip_address' => '203.0.113.20',
            'user_agent' => 'AuditLogAccessTest/1.0',
            'url' => 'https://warehouse.test/products/1',
            'method' => 'PUT',
        ]);

        $this->actingAs($user)
            ->get(route('audit-logs.show', $auditLog))
            ->assertOk()
            ->assertSeeText('Audit Log Details')
            ->assertSeeText('Updated')
            ->assertSeeText('Products')
            ->assertSeeText('Product was updated.')
            ->assertSeeText(User::class)
            ->assertSeeText((string) $user->id)
            ->assertSeeText('203.0.113.20')
            ->assertSeeText('AuditLogAccessTest/1.0')
            ->assertSeeText('https://warehouse.test/products/1')
            ->assertSeeText('PUT')
            ->assertSeeText('Old Product')
            ->assertSeeText('New Product')
            ->assertSeeText('feature-test');
    }

    public function test_user_without_audit_logs_view_permission_cannot_view_audit_log_details(): void
    {
        $user = User::factory()->create();
        $auditLog = $this->auditLog();

        $this->actingAs($user)
            ->get(route('audit-logs.show', $auditLog))
            ->assertForbidden();
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
            'description' => 'Temporary role for audit log access tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function auditLog(array $overrides = []): AuditLog
    {
        return AuditLog::query()->create(array_replace([
            'user_id' => null,
            'event' => 'created',
            'module' => 'products',
            'auditable_type' => null,
            'auditable_id' => null,
            'description' => 'Audit log access test entry.',
            'old_values' => null,
            'new_values' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'AuditLogAccessTest',
            'url' => 'https://warehouse.test/audit',
            'method' => 'GET',
            'metadata' => null,
        ], $overrides));
    }
}
