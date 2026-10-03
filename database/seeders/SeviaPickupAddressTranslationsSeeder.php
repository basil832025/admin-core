<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class SeviaPickupAddressTranslationsSeeder extends Seeder
{
    /**
     * Add or update the Sevia showroom pickup labels used at checkout and in emails.
     */
    public function run(): void
    {
        $translations = [
            [
                'group' => 'frontend-sevia-auth',
                'slug' => 'frontend-sevia-auth.sevia_pickup',
                'value' => [
                    'uk' => 'Шоу-рум Sevia · самовивіз',
                    'ru' => 'Шоу-рум Sevia · самовывоз',
                    'en' => 'Sevia showroom · pickup',
                ],
                'description' => 'Назва способу отримання "самовивіз" у checkout Sevia',
            ],
            [
                'group' => 'frontend-sevia-auth',
                'slug' => 'frontend-sevia-auth.sevia_pickup_meta',
                'value' => [
                    'uk' => 'сьогодні · вул. Михайла Максимовича 32 Б, Київ, з 11:00',
                    'ru' => 'сегодня · ул. Михаила Максимовича 32 Б, Киев, с 11:00',
                    'en' => 'today · 32 B Mykhaila Maksymovycha St, Kyiv, from 11:00',
                ],
                'description' => 'Адреса та графік самовивозу у checkout Sevia',
            ],
            [
                'group' => 'emails',
                'slug' => 'emails.pickup_address',
                'value' => [
                    'uk' => 'вул. Михайла Максимовича 32 Б, Київ',
                    'ru' => 'ул. Михаила Максимовича 32 Б, Киев',
                    'en' => '32 B Mykhaila Maksymovycha St, Kyiv',
                ],
                'description' => 'Адреса самовивозу в листах про замовлення Sevia',
            ],
        ];

        foreach ($translations as $translation) {
            SiteText::updateOrCreate(
                ['slug' => $translation['slug']],
                [
                    'group' => $translation['group'],
                    'value' => $translation['value'],
                    'description' => $translation['description'],
                ],
            );
        }

        $this->command?->info('Sevia pickup address translations added/updated.');
    }
}
