<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Сидер для генерации всех пермишенов Shield и назначения роли super_admin
 * 
 * Использование:
 * php artisan db:seed --class=GenerateShieldPermissionsSeeder
 */
class GenerateShieldPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Сброс кэша пермишенов
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        
        $this->command->info('Генерирую пермишены через Shield...');
        
        // Генерируем пермишены для всех ресурсов, страниц и виджетов
        Artisan::call('shield:generate', [
            '--all' => true,
            '--option' => 'permissions'
        ]);
        
        $this->command->info(Artisan::output());
        
        // Создаем роль super_admin с guard 'admin'
        $this->command->info('Создаю роль super_admin...');
        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'admin'
        ]);
        
        // Назначаем роль super_admin первому пользователю
        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->assignRole($superAdminRole);
            $this->command->info("Роль super_admin назначена пользователю: {$firstUser->email}");
        } else {
            $this->command->warn('Пользователи не найдены. Роль super_admin не назначена.');
        }
        
        // Еще раз сбросим кеш
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        
        $this->command->info('✅ Пермишены и роли успешно настроены!');
    }
}

