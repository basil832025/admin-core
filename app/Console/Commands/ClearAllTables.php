<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearAllTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clear-all {--confirm : Подтвердить очистку всех таблиц без запроса}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очистить все таблицы в базе данных (TRUNCATE)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('confirm')) {
            if (!$this->confirm('Вы уверены, что хотите очистить ВСЕ таблицы в базе данных? Это действие необратимо!')) {
                $this->info('Операция отменена.');
                return 0;
            }
        }

        $this->info('Начинаю очистку всех таблиц...');
        $this->newLine();

        // Получаем список всех таблиц в базе данных
        $tables = DB::select('SHOW TABLES');
        $databaseName = DB::getDatabaseName();
        $tableKey = 'Tables_in_' . $databaseName;

        if (empty($tables)) {
            $this->warn('В базе данных не найдено таблиц.');
            return 0;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $cleared = 0;
        $errors = 0;

        foreach ($tables as $table) {
            $tableName = $table->$tableKey;

            try {
                DB::table($tableName)->truncate();
                $this->line("✓ Очищена таблица: {$tableName}");
                $cleared++;
            } catch (\Exception $e) {
                $this->error("✗ Ошибка при очистке {$tableName}: " . $e->getMessage());
                $errors++;
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info("Готово! Очищено: {$cleared}, ошибок: {$errors}");

        return 0;
    }
}
