<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Shop\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillOrderStatusActivityLogsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('activity_log') || ! Schema::hasTable('bs_shop_orders')) {
            return;
        }

        Order::query()
            ->whereNotNull('status_times')
            ->orderBy('id')
            ->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    $this->backfillOrder($order);
                }
            });
    }

    private function backfillOrder(Order $order): void
    {
        $times = $order->status_times;
        if (! is_array($times) || $times === []) {
            return;
        }

        $events = collect($times)
            ->map(fn ($time, string $status): array => [
                'status' => $status,
                'time' => Carbon::parse($time),
            ])
            ->sortBy('time')
            ->values();

        $previousStatus = null;

        foreach ($events as $event) {
            $status = (string) $event['status'];

            if ($previousStatus === null) {
                $previousStatus = $status;
                continue;
            }

            if ($this->statusActivityExists($order->id, $previousStatus, $status)) {
                $previousStatus = $status;
                continue;
            }

            DB::table('activity_log')->insert([
                'log_name' => 'order',
                'description' => 'Изменение заказа',
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'event' => 'updated',
                'causer_type' => null,
                'causer_id' => null,
                'properties' => json_encode([
                    'old' => ['status' => $previousStatus],
                    'attributes' => ['status' => $status],
                    'backfilled_from_status_times' => true,
                ], JSON_UNESCAPED_UNICODE),
                'batch_uuid' => null,
                'created_at' => $event['time'],
                'updated_at' => $event['time'],
            ]);

            $previousStatus = $status;
        }
    }

    private function statusActivityExists(int $orderId, string $from, string $to): bool
    {
        return DB::table('activity_log')
            ->where('log_name', 'order')
            ->where('subject_id', $orderId)
            ->whereIn('subject_type', [Order::class, \App\Models\Callcenter\Order::class])
            ->where('properties->old->status', $from)
            ->where('properties->attributes->status', $to)
            ->exists();
    }
}
