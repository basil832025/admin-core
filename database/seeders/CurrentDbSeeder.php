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
            DB::table($table)->truncate();

            foreach (array_chunk($filteredRows, 1000) as $chunk) {
                // Если есть риск дублей по PK — можно так:
                // DB::table($table)->insertOrIgnore($chunk);
                DB::table($table)->insert($chunk);
            }

            $this->command?->info("Seeded: {$table} (".count($filteredRows).')');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
