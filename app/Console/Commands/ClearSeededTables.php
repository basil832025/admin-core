<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ClearSeededTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clear-seeded {--table= : Очистить только указанную таблицу}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очистить все таблицы, которые загружаются из дампов (seeders/dumps)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dir = database_path('seeders/dumps');
        
        if (!File::exists($dir)) {
            $this->error("Директория {$dir} не существует!");
            return 1;
        }

        $files = collect(File::files($dir))
            ->filter(fn($f) => $f->getExtension() === 'json')
            ->sortBy(fn($f) => $f->getFilename())
            ->values();

        if ($files->isEmpty()) {
            $this->warn('Не найдено JSON файлов в директории дампов.');
            return 0;
        }

        $this->info('Начинаю очистку таблиц...');
        $this->newLine();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $cleared = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($files as $file) {
            $table = str_replace('.json', '', $file->getFilename());

            // Если указана опция --table, очищаем только её
            if ($this->option('table') && $table !== $this->option('table')) {
                continue;
            }

            try {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    DB::table($table)->truncate();
                    $this->line("✓ Очищена таблица: {$table}");
                    $cleared++;
                } else {
                    $this->warn("⚠ Таблица не существует: {$table}");
                    $skipped++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Ошибка при очистке {$table}: " . $e->getMessage());
                $errors++;
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info("Готово! Очищено: {$cleared}, пропущено: {$skipped}, ошибок: {$errors}");

        return 0;
    }
}
