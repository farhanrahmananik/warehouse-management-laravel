<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
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

class WarehouseAuditLogTest extends TestCase
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

    public function test_creating_a_warehouse_creates_audit_log(): void
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

        $warehouse = Warehouse::query()->where('code', 'WH-001')->firstOrFail();
        $auditLog = $this->latestAuditLog('created');

        $this->assertSame($user->id, $auditLog->user_id);
        $this->assertSame($warehouse->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($warehouse->id, $auditLog->auditable_id);
        $this->assertSame('Warehouse "Main Warehouse" was created.', $auditLog->description);
        $this->assertSame('WH-001', $auditLog->new_values['code']);
        $this->assertSame('Main Warehouse', $auditLog->new_values['name']);
        $this->assertSame('Berlin', $auditLog->new_values['city']);
        $this->assertSame(['model' => 'warehouse'], $auditLog->metadata);
    }

    public function test_updating_a_warehouse_creates_audit_log_with_changed_values_only(): void
    {
        $warehouse = $this->warehouse();
        $user = $this->userWithPermissions(['warehouses.update']);

        $this->actingAs($user)
            ->put(route('warehouses.update', $warehouse), [
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'contact_person' => $warehouse->contact_person,
                'phone' => $warehouse->phone,
                'email' => $warehouse->email,
                'address' => $warehouse->address,
                'city' => 'Hamburg',
                'is_active' => true,
            ])
            ->assertRedirect(route('warehouses.index'));

        $auditLog = $this->latestAuditLog('updated');

        $this->assertSame($warehouse->id, $auditLog->auditable_id);
        $this->assertSame('Warehouse "Existing Warehouse" was updated.', $auditLog->description);
        $this->assertSame([
            'city' => 'Munich',
        ], $auditLog->old_values);
        $this->assertSame([
            'city' => 'Hamburg',
        ], $auditLog->new_values);
    }

    public function test_deleting_a_warehouse_creates_audit_log(): void
    {
        $warehouse = $this->warehouse();
        $user = $this->userWithPermissions(['warehouses.delete']);

        $this->actingAs($user)
            ->delete(route('warehouses.destroy', $warehouse))
            ->assertRedirect(route('warehouses.index'));

        $auditLog = $this->latestAuditLog('deleted');

        $this->assertSame($warehouse->getMorphClass(), $auditLog->auditable_type);
        $this->assertSame($warehouse->id, $auditLog->auditable_id);
        $this->assertSame('Warehouse "Existing Warehouse" was deleted.', $auditLog->description);
        $this->assertSame('WH-EXISTING', $auditLog->old_values['code']);
        $this->assertSame('Existing Warehouse', $auditLog->old_values['name']);
        $this->assertSame('Munich', $auditLog->old_values['city']);
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
            'description' => 'Temporary role for warehouse audit log tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function latestAuditLog(string $event): AuditLog
    {
        return AuditLog::query()
            ->where('module', 'warehouses')
            ->where('event', $event)
            ->latest('id')
            ->firstOrFail();
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
