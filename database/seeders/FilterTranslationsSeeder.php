<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class FilterTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'filter.title'],
            [
                'group' => 'filter',
                'value' => [
                    'uk' => 'Результати фільтру',
                    'ru' => 'Результаты фильтра',
                    'en' => 'Filter results',
                ],
                'description' => 'Заголовок страницы /filter',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'filter.empty'],
            [
                'group' => 'filter',
                'value' => [
                    'uk' => 'За вибраними фільтрами товари не знайдено.',
                    'ru' => 'По выбранным фильтрам товары не найдены.',
                    'en' => 'No products found for the selected filters.',
                ],
                'description' => 'Текст, когда по фильтру ничего не найдено',
            ]
        );
    }
}
