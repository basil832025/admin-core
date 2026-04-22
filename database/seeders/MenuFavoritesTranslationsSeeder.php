<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class MenuFavoritesTranslationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'menu.favorites'],
            [
                'group' => 'menu',
                'value' => [
                    'uk' => 'Обране',
                    'ru' => 'Избранное',
                    'en' => 'Favorites',
                ],
                'description' => 'Пункт меню и заголовок страницы избранного',
            ]
        );
    }
}
