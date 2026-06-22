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

        $managerPermissionSlugs = [
            'dashboard.view',
            'users.view',
            'users.create',
            'users.update',
            'roles.view',
            'roles.create',
            'roles.update',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'suppliers.delete',
            'warehouses.view',
            'warehouses.create',
            'warehouses.update',
            'warehouses.delete',
            'purchase-orders.view',
            'purchase-orders.create',
            'purchase-orders.update',
            'purchase-orders.delete',
            'purchase-orders.approve',
            'purchase-orders.receive',
            'stock.view',
            'stock-adjustments.create',
            'stock-in.view',
            'stock-in.create',
            'stock-out.view',
            'stock-out.create',
            'stock-transfer.view',
            'stock-transfer.create',
            'reports.view',
            'reports.inventory.view',
            'reports.stock-movements.view',
            'reports.low-stock.view',
            'reports.purchase-orders.view',
            'reports.export',
            'audit_logs.view',
        ];

        $managerPermissionIds = Permission::query()
            ->whereIn('slug', $managerPermissionSlugs)
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
                'purchase-orders.receive',
                'stock.view',
                'stock-adjustments.create',
                'stock-in.view',
                'stock-in.create',
                'stock-out.view',
                'stock-out.create',
                'stock-transfer.view',
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
