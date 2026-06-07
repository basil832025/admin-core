<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class AuthSmsHelpTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            [
                'slug' => 'auth.sms_help_phone',
                'value' => [
                    'uk' => '097 898 4333',
                    'ru' => '097 898 4333',
                    'en' => '097 898 4333',
                ],
                'description' => 'Phone number shown in the SMS code help card.',
            ],
            [
                'slug' => 'auth.sms_help_call_label',
                'value' => [
                    'uk' => 'Зателефонувати',
                    'ru' => 'Позвонить',
                    'en' => 'Call',
                ],
                'description' => 'Accessible label for the call link in the SMS code help card.',
            ],
            [
                'slug' => 'auth.sms_help_title',
                'value' => [
                    'uk' => 'Не прийшов код?',
                    'ru' => 'Не пришёл код?',
                    'en' => 'Did not get the code?',
                ],
                'description' => 'Title in the SMS code help card.',
            ],
            [
                'slug' => 'auth.sms_help_call_us',
                'value' => [
                    'uk' => 'Зателефонуйте нам на',
                    'ru' => 'Позвоните нам на',
                    'en' => 'Call us at',
                ],
                'description' => 'Text before the phone number in the SMS code help card.',
            ],
            [
                'slug' => 'auth.sms_help_we_create_order',
                'value' => [
                    'uk' => 'і ми оформимо ваше замовлення в телефонному режимі.',
                    'ru' => 'и мы оформим ваш заказ в телефонном режиме.',
                    'en' => 'and we will place your order by phone.',
                ],
                'description' => 'Text after the phone number in the SMS code help card.',
            ],
        ];

        foreach ($translations as $translation) {
            SiteText::updateOrCreate(
                ['slug' => $translation['slug']],
                [
                    'group' => 'auth',
                    'value' => $translation['value'],
                    'description' => $translation['description'],
                ],
            );
        }

        $this->command?->info('Auth SMS help translations seeded successfully.');
    }
}
