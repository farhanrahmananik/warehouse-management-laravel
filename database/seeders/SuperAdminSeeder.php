<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the initial Super Admin user.
     */
    public function run(): void
    {
        $name = trim((string) config('seeding.super_admin.name', 'Super Admin')) ?: 'Super Admin';
        $email = trim((string) config('seeding.super_admin.email'));
        $password = (string) config('seeding.super_admin.password');

        if ($email === '') {
            throw new RuntimeException('SEED_SUPER_ADMIN_EMAIL is required to seed the initial Super Admin user.');
        }

        if ($password === '') {
            throw new RuntimeException('SEED_SUPER_ADMIN_PASSWORD is required to seed the initial Super Admin user.');
        }

        $role = Role::query()
            ->where('slug', 'super-admin')
            ->first();

        if (! $role) {
            throw new RuntimeException('The super-admin role was not found. Run RoleSeeder before SuperAdminSeeder.');
        }

        $hasEmailVerifiedAt = Schema::hasColumn('users', 'email_verified_at');
        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user) {
            $updates = [];

            if ($user->name !== $name) {
                $updates['name'] = $name;
            }

            if ($hasEmailVerifiedAt && $user->email_verified_at === null) {
                $updates['email_verified_at'] = now();
            }

            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }
        } else {
            $attributes = [
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ];

            if ($hasEmailVerifiedAt) {
                $attributes['email_verified_at'] = now();
            }

            $user = new User();
            $user->forceFill($attributes)->save();
        }

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
