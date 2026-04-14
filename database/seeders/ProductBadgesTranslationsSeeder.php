<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class ProductBadgesTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            [
                'group' => 'product',
                'slug' => 'product.badges.is_promo',
                'value' => [
                    'uk' => 'Акція',
                    'ru' => 'Акция',
                    'en' => 'Promo',
                ],
                'description' => 'Badge label for promo products',
            ],
            [
                'group' => 'product',
                'slug' => 'product.badges.is_vegan',
                'value' => [
                    'uk' => 'Веган',
                    'ru' => 'Веган',
                    'en' => 'Vegan',
                ],
                'description' => 'Badge label for vegan products',
            ],
            [
                'group' => 'product',
                'slug' => 'product.badges.is_hit',
                'value' => [
                    'uk' => 'Хіт',
                    'ru' => 'Хит',
                    'en' => 'Hit',
                ],
                'description' => 'Badge label for hit products',
            ],
            [
                'group' => 'product',
                'slug' => 'product.badges.is_product_of_day',
                'value' => [
                    'uk' => 'Пиріг дня',
                    'ru' => 'Пирог дня',
                    'en' => 'Pie of the day',
                ],
                'description' => 'Badge label for product of the day',
            ],
            [
                'group' => 'product',
                'slug' => 'product.badges.is_spicy',
                'value' => [
                    'uk' => 'Гострий',
                    'ru' => 'Острый',
                    'en' => 'Spicy',
                ],
                'description' => 'Badge label for spicy products',
            ],
        ];

        foreach ($translations as $translation) {
            SiteText::updateOrCreate(
                ['slug' => $translation['slug']],
                [
                    'group' => $translation['group'],
                    'value' => $translation['value'],
                    'description' => $translation['description'] ?? null,
                ]
            );

            foreach (['uk', 'ru', 'en'] as $locale) {
                Cache::forget("st:{$translation['slug']}:{$locale}");
            }
        }

        $this->command?->info('Product badges translations seeded successfully!');
    }
}
