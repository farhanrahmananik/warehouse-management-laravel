<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Attach default permissions to default roles.
     */
    public function run(): void
    {
        $allPermissionIds = Permission::query()
            ->pluck('id')
            ->all();

        $managerPermissionIds = Permission::query()
            ->whereNotIn('slug', ['users.delete', 'roles.delete'])
            ->pluck('id')
            ->all();

        $warehouseStaffPermissionIds = Permission::query()
            ->whereIn('slug', [
                'dashboard.view',
                'products.view',
                'categories.view',
                'suppliers.view',
                'warehouses.view',
                'purchase-orders.view',
                'stock.view',
                'stock-adjustments.create',
                'stock-in.create',
                'stock-out.create',
                'stock-transfer.create',
            ])
            ->pluck('id')
            ->all();

        $viewerPermissionIds = Permission::query()
            ->whereIn('slug', [
                'dashboard.view',
                'products.view',
                'categories.view',
                'suppliers.view',
                'warehouses.view',
                'purchase-orders.view',
                'stock.view',
                'reports.view',
            ])
            ->pluck('id')
            ->all();

        Role::query()->where('slug', 'super-admin')->firstOrFail()
            ->permissions()
            ->sync($allPermissionIds);

        Role::query()->where('slug', 'manager')->firstOrFail()
            ->permissions()
            ->sync($managerPermissionIds);

        Role::query()->where('slug', 'warehouse-staff')->firstOrFail()
            ->permissions()
            ->sync($warehouseStaffPermissionIds);

        Role::query()->where('slug', 'viewer')->firstOrFail()
            ->permissions()
            ->sync($viewerPermissionIds);
    }
}
