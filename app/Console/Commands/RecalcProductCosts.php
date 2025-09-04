<?php
//Пересчитывает суммы по всем калькуляциям (на случай массовых правок).
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shop\ProductCalculation;

class RecalcProductCosts extends Command
{
    protected $signature = 'products:recalc-cost {--id=}';
    protected $description = 'Recalculate total_cost for product calculations (optionally only for one product_id)';

    public function handle(): int
    {
        $q = ProductCalculation::query()
            ->with('items');

        if ($pid = $this->option('id')) {
            $q->where('product_id', $pid);
        }

        $bar = $this->output->createProgressBar($q->count());
        $bar->start();

        $q->chunkById(200, function ($rows) use ($bar) {
            foreach ($rows as $calc) {
                $calc->recalcTotals();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
