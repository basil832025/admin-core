<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $slug = 'order.success.view_order_status';

        $record = DB::table('bs_site_texts')->where('slug', $slug)->first();

        $value = [];
        if ($record?->value) {
            $decoded = json_decode((string) $record->value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        $value = array_merge($value, [
            'uk' => 'Переглянути статус замовлення',
            'ru' => 'Просмотреть статус заказа',
            'en' => 'View order status',
        ]);

        $payload = [
            'group' => 'site',
            'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'description' => 'Button label on checkout success page to open order status page',
            'updated_at' => now(),
        ];

        if ($record) {
            DB::table('bs_site_texts')->where('id', $record->id)->update($payload);
            return;
        }

        $payload['slug'] = $slug;
        $payload['created_at'] = now();
        DB::table('bs_site_texts')->insert($payload);
    }

    public function down(): void
    {
        DB::table('bs_site_texts')
            ->where('slug', 'order.success.view_order_status')
            ->delete();
    }
};
