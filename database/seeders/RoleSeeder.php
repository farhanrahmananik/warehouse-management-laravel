<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the default RBAC roles.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full system access.',
                'is_active' => true,
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Can manage and approve warehouse workflows.',
                'is_active' => true,
            ],
            [
                'name' => 'Warehouse Staff',
                'slug' => 'warehouse-staff',
                'description' => 'Can handle warehouse stock operations.',
                'is_active' => true,
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only operational access.',
                'is_active' => true,
            ],
        ];

        foreach ($roles as $role) {
            $record = Role::withTrashed()->updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );

            if ($record->trashed()) {
                $record->restore();
            }
        }
    }
}
