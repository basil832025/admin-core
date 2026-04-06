<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class AboutMenuButtonTranslationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'about.go_to_menu'],
            [
                'group' => 'about',
                'value' => [
                    'uk' => 'Перейти до меню',
                    'ru' => 'Перейти в меню',
                    'en' => 'Go to menu',
                ],
                'description' => 'Кнопка на странице about: перейти в меню',
            ]
        );
    }
}
