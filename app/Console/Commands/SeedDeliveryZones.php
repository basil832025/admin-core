<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Database\Seeders\DeliveryZoneSeeder;

class SeedDeliveryZones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delivery-zones:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Заполнить базу данных зонами доставки (Green, Blue, Red, Brown)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Начинаем заполнение зон доставки...');
        
        try {
            $seeder = new DeliveryZoneSeeder();
            $seeder->setCommand($this);
            $seeder->run();
            
            $this->info('✅ Зоны доставки успешно заполнены!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Ошибка при заполнении зон доставки: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
