<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class ReviewsHeaderTranslationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'reviews.reviews'],
            [
                'group' => 'reviews',
                'value' => [
                    'uk' => 'Відгуки',
                    'ru' => 'Отзывы',
                    'en' => 'Reviews',
                ],
                'description' => 'Заголовок секции отзывов',
            ]
        );
    }
}
