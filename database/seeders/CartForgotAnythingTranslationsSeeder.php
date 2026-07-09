<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class CartForgotAnythingTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'cart.forgot_anything'],
            [
                'group' => 'cart',
                'value' => [
                    'uk' => 'Нічого не забули',
                    'ru' => 'Ничего не забыли',
                    'en' => 'Did not forget anything?',
                ],
                'description' => 'Cart recommendations block title',
            ]
        );

        foreach (['uk', 'ru', 'en'] as $locale) {
            Cache::forget("st:all:{$locale}");
        }

        $this->command?->info('Cart recommendations title translations seeded successfully.');
    }
}