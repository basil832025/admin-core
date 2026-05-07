<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;



class Kernel extends ConsoleKernel
{
    /**
     * Определяем кастомные artisan-команды приложения.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        \App\Console\Commands\DumpSeedData::class,
        \App\Console\Commands\ExpireLoyaltyBonuses::class,
    ];

    /**
     * Зарегистрировать расписания задач.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        $schedule->command('seo:sitemap')
            ->dailyAt('03:30')
            ->withoutOverlapping();

        $schedule->command('loyalty:expire-bonuses')
            ->dailyAt('01:00')
            ->withoutOverlapping();
    }

    /**
     * Зарегистрировать замыкания команд для приложения.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
