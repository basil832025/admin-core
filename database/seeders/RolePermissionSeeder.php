<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use App\Enums\OrderStatus;
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Сброс кэша ролей/прав (важно при повторном запуске)
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Базовые права, которые у тебя уже были
        Permission::findOrCreate('view orders');
        Permission::findOrCreate('edit products');

        // ---- Права на статусы ----
        // Админское «общее» право
        Permission::findOrCreate('set_order_status');

        // Право на даунгрейд (возврат статуса назад)
        Permission::findOrCreate('order_status_downgrade');

        // Точечные права по каждому статусу
        foreach (OrderStatus::cases() as $status) {
            Permission::findOrCreate('set_order_status_' . $status->value);
        }

        // ----- Роли -----
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $admin = Role::firstOrCreate(['name' => 'admin']);

        // Какие статусы может менеджер (пример — настрой под себя)
        $managerStatusPermissions = [
            'set_order_status_new',
            'set_order_status_processing',
            'set_order_status_on_hold',
            // 'set_order_status_filling',
            // 'set_order_status_molding',
            // ...
            // 'set_order_status_cancelled',
        ];

        // Если менеджеру разрешён откат назад — добавь:
        $managerCanDowngrade = true;

        $manager->syncPermissions(array_filter(array_merge(
            ['view orders', 'edit products'],
            $managerStatusPermissions,
            $managerCanDowngrade ? ['order_status_downgrade'] : []
        )));

        // Админу можно всё: либо выдаём «общее» право, либо просто все существующие
        $admin->syncPermissions(Permission::pluck('name')->all());
        // или так:
        // $admin->givePermissionTo('set_order_status');
        // $admin->givePermissionTo('order_status_downgrade');

        // Назначим роли пользователям (пример)
        if ($user = User::find(1)) {
            $user->syncRoles(['admin']);   // админ
        }
        // User::find(2)?->syncRoles(['manager']);
    }
}
