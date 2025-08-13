<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class OrderStatusPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // какой guard использовать (по умолчанию web)
        $guard = config('auth.defaults.guard', 'web');

        // Общее право: можно ли вообще менять статус
        Permission::firstOrCreate([
            'name'       => 'set_order_status',
            'guard_name' => $guard,
        ]);

        // Права на установку каждого статуса по отдельности
        foreach (OrderStatus::cases() as $case) {
            Permission::firstOrCreate([
                'name'       => "set_order_status_{$case->value}",
                'guard_name' => $guard,
            ]);
        }
    }
}
