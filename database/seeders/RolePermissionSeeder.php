<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Права
        Permission::create(['name' => 'view orders']);
        Permission::create(['name' => 'edit products']);

        // Роль
        $role = Role::create(['name' => 'manager']);

        // Назначить права роли
        $role->givePermissionTo(['view orders', 'edit products']);

        // Назначить роль пользователю
        $user = User::find(1);
        if ($user) {
            $user->assignRole('manager');
        }
    }
}
