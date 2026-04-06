<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class BlogCardTranslationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'blog.card.read_more'],
            [
                'group' => 'blog',
                'value' => [
                    'uk' => 'Читати далі',
                    'ru' => 'Читать далее',
                    'en' => 'Read More',
                ],
                'description' => 'Кнопка на карточке блога: Читать далее',
            ]
        );
    }
}
