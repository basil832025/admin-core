<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DropAllTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:drop-all {--confirm : Подтвердить удаление всех таблиц без запроса}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Удалить все таблицы в базе данных (DROP TABLE)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('confirm')) {
            if (!$this->confirm('ВНИМАНИЕ! Это удалит ВСЕ таблицы в базе данных. Это действие необратимо! Продолжить?')) {
                $this->info('Операция отменена.');
                return 0;
            }
        }

        $this->info('Начинаю удаление всех таблиц...');
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

        $dropped = 0;
        $errors = 0;

        foreach ($tables as $table) {
            $tableName = $table->$tableKey;

            try {
                DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                $this->line("✓ Удалена таблица: {$tableName}");
                $dropped++;
            } catch (\Exception $e) {
                $this->error("✗ Ошибка при удалении {$tableName}: " . $e->getMessage());
                $errors++;
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info("Готово! Удалено таблиц: {$dropped}, ошибок: {$errors}");

        return 0;
    }
}
