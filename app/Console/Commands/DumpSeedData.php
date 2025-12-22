<?php
/* пример запуска
 * php artisan seed:dump --except=filament_users,filament_password_resets,filament_sessions
php artisan seed:dump или по умолчанию
 \*/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DumpSeedData extends Command
{
    protected $signature = 'seed:dump
        {--only= : Список таблиц через запятую}
        {--except= : Список таблиц через запятую для исключения}
        {--chunk=1000 : Размер чанка при чтении}';

    protected $description = 'Экспорт текущих данных БД в JSON для сидов';

    public function handle(): int
    {
        $only   = $this->csv($this->option('only'));
        $except = $this->csv($this->option('except'));

        $defaultExcept = [
            'migrations','jobs','failed_jobs','cache','cache_locks','sessions',
            'password_reset_tokens','personal_access_tokens','activity_log'
        ];
        $maybeExcept = ['telescope_entries','horizon_jobs','horizon_supervisors'];

        $except = array_unique(array_merge($except, $defaultExcept, $maybeExcept));

        // Список таблиц без Doctrine
        $tables = collect($this->getAllTableNames())
            ->filter(function ($t) use ($only) {

                // если указан --only, работаем строго по нему
                if ($only) {
                    return in_array($t, $only, true);
                }

                // разрешаем ТОЛЬКО bs_* и users
                return Str::startsWith($t, 'bs_') || $t === 'users';
            })
            ->values();


        $outDir = database_path('seeders/dumps');
        File::ensureDirectoryExists($outDir);

        $chunk = (int) ($this->option('chunk') ?: 1000);

        foreach ($tables as $table) {
            $this->info("→ {$table}");
            $count = DB::table($table)->count();
            $data  = [];

            // для стабильности сортируем по id, если колонка есть
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'id')) {
                $query = $query->orderBy('id');
            }

            for ($offset = 0; $offset < $count; $offset += $chunk) {
                $query->clone()->offset($offset)->limit($chunk)->get()
                    ->each(function ($row) use (&$data) {
                        $data[] = (array) $row;
                    });
            }

            File::put(
                "{$outDir}/{$table}.json",
                json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        $this->info('Готово! Файлы в database/seeders/dumps/*.json');
        return self::SUCCESS;
    }

    /** Возвращает список таблиц без Doctrine. */
    protected function getAllTableNames(): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $db = DB::getDatabaseName();
            $rows = DB::select("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
            ", [$db]);
            return array_map(fn($r) => $r->TABLE_NAME, $rows);
        }

        if ($driver === 'pgsql') {
            $rows = DB::select("
                SELECT tablename
                FROM pg_tables
                WHERE schemaname NOT IN ('pg_catalog','information_schema')
            ");
            return array_map(fn($r) => $r->tablename, $rows);
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("
                SELECT name
                FROM sqlite_master
                WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
            ");
            return array_map(fn($r) => $r->name, $rows);
        }

        if ($driver === 'sqlsrv') {
            $rows = DB::select("
                SELECT t.name AS name
                FROM sys.tables t
            ");
            return array_map(fn($r) => $r->name, $rows);
        }

        // по умолчанию – пусто
        return [];
    }

    /** Парсинг CSV-опции. */
    protected function csv($value): array
    {
        return array_filter(array_map('trim', explode(',', (string) $value)));
    }
}
