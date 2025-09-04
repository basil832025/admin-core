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

            // Если нужна «чистая» загрузка — раскомментируй:
            // DB::table($table)->truncate();

            foreach (array_chunk($rows, 1000) as $chunk) {
                // Если есть риск дублей по PK — можно так:
                // DB::table($table)->insertOrIgnore($chunk);
                DB::table($table)->insert($chunk);
            }

            $this->command?->info("Seeded: {$table} (".count($rows).')');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
