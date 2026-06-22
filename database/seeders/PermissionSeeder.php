<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the default RBAC permissions.
     */
    public function run(): void
    {
        $permissions = [
            ['slug' => 'dashboard.view', 'name' => 'View Dashboard', 'module' => 'dashboard'],
            ['slug' => 'users.view', 'name' => 'View Users', 'module' => 'users'],
            ['slug' => 'users.create', 'name' => 'Create Users', 'module' => 'users'],
            ['slug' => 'users.update', 'name' => 'Update Users', 'module' => 'users'],
            ['slug' => 'users.delete', 'name' => 'Delete Users', 'module' => 'users'],
            ['slug' => 'roles.view', 'name' => 'View Roles', 'module' => 'roles'],
            ['slug' => 'roles.create', 'name' => 'Create Roles', 'module' => 'roles'],
            ['slug' => 'roles.update', 'name' => 'Update Roles', 'module' => 'roles'],
            ['slug' => 'roles.delete', 'name' => 'Delete Roles', 'module' => 'roles'],
            ['slug' => 'products.view', 'name' => 'View Products', 'module' => 'products'],
            ['slug' => 'products.create', 'name' => 'Create Products', 'module' => 'products'],
            ['slug' => 'products.update', 'name' => 'Update Products', 'module' => 'products'],
            ['slug' => 'products.delete', 'name' => 'Delete Products', 'module' => 'products'],
            ['slug' => 'categories.view', 'name' => 'View Categories', 'module' => 'categories'],
            ['slug' => 'categories.create', 'name' => 'Create Categories', 'module' => 'categories'],
            ['slug' => 'categories.update', 'name' => 'Update Categories', 'module' => 'categories'],
            ['slug' => 'categories.delete', 'name' => 'Delete Categories', 'module' => 'categories'],
            ['slug' => 'suppliers.view', 'name' => 'View Suppliers', 'module' => 'suppliers'],
            ['slug' => 'suppliers.create', 'name' => 'Create Suppliers', 'module' => 'suppliers'],
            ['slug' => 'suppliers.update', 'name' => 'Update Suppliers', 'module' => 'suppliers'],
            ['slug' => 'suppliers.delete', 'name' => 'Delete Suppliers', 'module' => 'suppliers'],
            ['slug' => 'warehouses.view', 'name' => 'View Warehouses', 'module' => 'warehouses'],
            ['slug' => 'warehouses.create', 'name' => 'Create Warehouses', 'module' => 'warehouses'],
            ['slug' => 'warehouses.update', 'name' => 'Update Warehouses', 'module' => 'warehouses'],
            ['slug' => 'warehouses.delete', 'name' => 'Delete Warehouses', 'module' => 'warehouses'],
            ['slug' => 'purchase-orders.view', 'name' => 'View Purchase Orders', 'module' => 'purchase_orders'],
            ['slug' => 'purchase-orders.create', 'name' => 'Create Purchase Orders', 'module' => 'purchase_orders'],
            ['slug' => 'purchase-orders.update', 'name' => 'Update Purchase Orders', 'module' => 'purchase_orders'],
            ['slug' => 'purchase-orders.delete', 'name' => 'Delete Purchase Orders', 'module' => 'purchase_orders'],
            ['slug' => 'purchase-orders.approve', 'name' => 'Approve Purchase Orders', 'module' => 'purchase_orders'],
            ['slug' => 'purchase-orders.receive', 'name' => 'Receive Purchase Orders', 'module' => 'purchase_orders'],
            ['slug' => 'stock.view', 'name' => 'View Stock', 'module' => 'stock'],
            ['slug' => 'stock-adjustments.create', 'name' => 'Create Stock Adjustments', 'module' => 'stock'],
            ['slug' => 'stock-in.view', 'name' => 'View Stock In', 'module' => 'stock'],
            ['slug' => 'stock-in.create', 'name' => 'Create Stock In', 'module' => 'stock'],
            ['slug' => 'stock-out.view', 'name' => 'View Stock Out', 'module' => 'stock'],
            ['slug' => 'stock-out.create', 'name' => 'Create Stock Out', 'module' => 'stock'],
            ['slug' => 'stock-transfer.view', 'name' => 'View Stock Transfer', 'module' => 'stock'],
            ['slug' => 'stock-transfer.create', 'name' => 'Create Stock Transfer', 'module' => 'stock'],
            ['slug' => 'reports.view', 'name' => 'View Reports', 'module' => 'reports'],
            ['slug' => 'audit-logs.view', 'name' => 'View Audit Logs', 'module' => 'audit_logs'],
        ];

        foreach ($permissions as $permission) {
            $record = Permission::withTrashed()->updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    ...$permission,
                    'description' => null,
                    'is_active' => true,
                ]
            );

            if ($record->trashed()) {
                $record->restore();
            }
        }
    }
}
