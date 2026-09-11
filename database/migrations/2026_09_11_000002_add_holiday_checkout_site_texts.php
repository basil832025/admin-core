<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            'checkout.holiday.title' => [
                'uk' => 'Увага',
                'ru' => 'Внимание',
                'en' => 'Attention',
                'description' => 'Title for day-off warning modal',
            ],
            'checkout.holiday.default_message' => [
                'uk' => 'Сьогодні ми не працюємо. Ви можете оформити передзамовлення на доступну дату.',
                'ru' => 'Сегодня мы не работаем. Вы можете оформить предзаказ на доступную дату.',
                'en' => 'We are closed today. You can place a preorder for an available date.',
                'description' => 'Fallback message for day-off warning modal',
            ],
            'checkout.holiday.preorder' => [
                'uk' => 'Зробити передзамовлення',
                'ru' => 'Сделать предзаказ',
                'en' => 'Place a preorder',
                'description' => 'Preorder button in day-off warning modal',
            ],
            'checkout.holiday.cancel' => [
                'uk' => 'Скасувати',
                'ru' => 'Отмена',
                'en' => 'Cancel',
                'description' => 'Cancel button in day-off warning modal',
            ],
        ];

        foreach ($rows as $slug => $payload) {
            $value = [
                'uk' => $payload['uk'],
                'ru' => $payload['ru'],
                'en' => $payload['en'],
            ];

            $record = DB::table('bs_site_texts')->where('slug', $slug)->first();

            if ($record) {
                $existing = json_decode((string) $record->value, true);
                $merged = is_array($existing) ? array_merge($existing, $value) : $value;

                DB::table('bs_site_texts')
                    ->where('id', $record->id)
                    ->update([
                        'group' => $record->group ?: 'checkout',
                        'value' => json_encode($merged, JSON_UNESCAPED_UNICODE),
                        'description' => $payload['description'],
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('bs_site_texts')->insert([
                'group' => 'checkout',
                'slug' => $slug,
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                'description' => $payload['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('bs_site_texts')->whereIn('slug', [
            'checkout.holiday.title',
            'checkout.holiday.default_message',
            'checkout.holiday.preorder',
            'checkout.holiday.cancel',
        ])->delete();
    }
};
