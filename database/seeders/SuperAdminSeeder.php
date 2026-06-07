<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $email = (string) env('SUPER_ADMIN_EMAIL', '1245640@gmail.com');
        $password = (string) env('SUPER_ADMIN_PASSWORD', 'admin');
        $name = (string) env('SUPER_ADMIN_NAME', 'Super Admin');

        $role = Role::firstOrCreate([
            'name' => config('filament-shield.super_admin.name', 'super_admin'),
            'guard_name' => 'admin',
        ]);

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ],
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        $permissions = Permission::query()
            ->where('guard_name', 'admin')
            ->pluck('name')
            ->all();

        if ($permissions !== []) {
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info("Super admin ready: {$user->email}");
    }
}
