<?php
/*
 *  как выролнить сдеры
 * в начале php artisan migrate --force //  загрузка таблиц
 * php artisan db:seed --class=CurrentDbSeeder --force // запуск сидеров с дампа
 *
 */
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CurrentDbSeeder extends Seeder
{
    public function run(): void
    {
        $dir = database_path('seeders/dumps');
        $files = collect(File::files($dir))
            ->filter(fn($f) => $f->getExtension() === 'json')
            ->sortBy(fn($f) => $f->getFilename()) // порядок по имени
            ->values();

        // Если у вас PostgreSQL — закомментируй следующие две строки (аналогично sqlsrv).
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($files as $file) {
            $table = str_replace('.json', '', $file->getFilename());
            $rows  = json_decode(File::get($file->getPathname()), true) ?: [];
            if (!$rows) continue;

            // Проверяем, существует ли таблица
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                $this->command?->warn("Skipped: {$table} (table does not exist)");
                continue;
            }

            // Получаем список существующих колонок в таблице
            $columns = DB::getSchemaBuilder()->getColumnListing($table);
            
            // Фильтруем данные, оставляя только существующие колонки
            $filteredRows = array_map(function($row) use ($columns) {
                return array_intersect_key($row, array_flip($columns));
            }, $rows);

            if (empty($filteredRows)) {
                $this->command?->warn("Skipped: {$table} (no matching columns)");
                continue;
            }

            // Очищаем таблицу перед загрузкой данных
            $permissionTables = ['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'];
            if (!in_array($table, $permissionTables, true)) {
                DB::table($table)->truncate();
            } else {
                // Для таблиц пермишенов используем delete вместо truncate
                DB::table($table)->delete();
            }

            foreach (array_chunk($filteredRows, 1000) as $chunk) {
                // Для таблиц пермишенов вставляем по одной записи с проверкой дублей
                if (in_array($table, $permissionTables, true)) {
                    foreach ($chunk as $row) {
                        try {
                            DB::table($table)->insert($row);
                        } catch (\Illuminate\Database\QueryException $e) {
                            // Игнорируем ошибки дубликатов (например, unique constraint)
                            if (strpos($e->getMessage(), 'Duplicate entry') === false && 
                                strpos($e->getMessage(), 'UNIQUE constraint') === false) {
                                throw $e;
                            }
                        }
                    }
                } else {
                    DB::table($table)->insert($chunk);
                }
            }

            $this->command?->info("Seeded: {$table} (".count($filteredRows).')');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        // Сбрасываем кеш пермишенов после загрузки
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            $this->command->info('Кеш пермишенов сброшен.');
        }
    }
}
