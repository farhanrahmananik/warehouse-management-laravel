<?php

namespace Tests\Feature\Reports;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportsAccessTest extends TestCase
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

    public function test_guest_is_redirected_from_reports_index_to_login(): void
    {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_reports_view_permission_receives_403_from_reports_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_user_with_reports_view_permission_can_access_reports_index(): void
    {
        $user = $this->userWithPermissions(['reports.view']);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSeeText('Reports');
    }

    public function test_user_with_inventory_report_permission_can_access_inventory_report_page(): void
    {
        $user = $this->userWithPermissions(['reports.inventory.view']);

        $this->actingAs($user)
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSeeText('Inventory Report');
    }

    public function test_user_with_stock_movements_report_permission_can_access_stock_movement_report_page(): void
    {
        $user = $this->userWithPermissions(['reports.stock-movements.view']);

        $this->actingAs($user)
            ->get(route('reports.stock-movements'))
            ->assertOk()
            ->assertSeeText('Stock Movement Report');
    }

    public function test_user_with_low_stock_report_permission_can_access_low_stock_report_page(): void
    {
        $user = $this->userWithPermissions(['reports.low-stock.view']);

        $this->actingAs($user)
            ->get(route('reports.low-stock'))
            ->assertOk()
            ->assertSeeText('Low Stock Report');
    }

    public function test_user_with_purchase_orders_report_permission_can_access_purchase_order_report_page(): void
    {
        $user = $this->userWithPermissions(['reports.purchase-orders.view']);

        $this->actingAs($user)
            ->get(route('reports.purchase-orders'))
            ->assertOk()
            ->assertSeeText('Purchase Order Report');
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
            'description' => 'Temporary role for reports access tests.',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
