<?php

use Illuminate\Foundation\Inspiring;
use App\Enums\OrderStatus;
use App\Models\Shop\Order;
use App\Services\NovaPostApiClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payparts:sync-refunds')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Artisan::command('nova-post:sync-tracking', function (NovaPostApiClient $client) {
    if (config('project.name') !== 'duxi') {
        return;
    }

    $failed = 0;
    Order::query()
        ->whereIn('status', [OrderStatus::Assembled->value, OrderStatus::Shipped->value])
        ->whereNotNull('nova_ttn')
        ->where('nova_ttn', '!=', '')
        ->where(fn ($query) => $query->whereNull('nova_status')->orWhere('nova_status', '!=', 'cancelled'))
        ->chunkById(50, function ($orders) use ($client, &$failed): void {
            foreach ($orders as $order) {
                try {
                    $client->updateOrderTrackingStatus($order);
                } catch (\Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            }
        });

    return $failed > 0 ? 1 : 0;
})->purpose('Sync DUXI orders with Nova Post tracking');

if (config('project.name') === 'duxi') {
    Schedule::command('nova-post:sync-tracking')
        ->everyFifteenMinutes()
        ->withoutOverlapping();
}
