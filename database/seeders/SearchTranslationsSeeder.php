<?php

namespace Database\Seeders;

use App\Models\SiteText;
use Illuminate\Database\Seeder;

class SearchTranslationsSeeder extends Seeder
{
    public function run(): void
    {
        SiteText::updateOrCreate(
            ['slug' => 'search.title'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Результати пошуку',
                    'ru' => 'Результаты поиска',
                    'en' => 'Search results',
                ],
                'description' => 'Заголовок страницы поиска',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.found'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Знайдено',
                    'ru' => 'Найдено',
                    'en' => 'Found',
                ],
                'description' => 'Префикс счетчика найденного',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.products'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Товари',
                    'ru' => 'Товары',
                    'en' => 'Products',
                ],
                'description' => 'Заголовок секции товаров',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.products_count'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'товарів',
                    'ru' => 'товаров',
                    'en' => 'products',
                ],
                'description' => 'Слово "товаров" рядом с числом',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.categories'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Категорії',
                    'ru' => 'Категории',
                    'en' => 'Categories',
                ],
                'description' => 'Заголовок секции категорий',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.categories_count'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'категорій',
                    'ru' => 'категорий',
                    'en' => 'categories',
                ],
                'description' => 'Слово "категорий" рядом с числом',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.enter_query'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Введіть запит для пошуку',
                    'ru' => 'Введите запрос для поиска',
                    'en' => 'Enter a search query',
                ],
                'description' => 'Текст для пустого запроса поиска',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.not_found'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Нічого не знайдено для запиту',
                    'ru' => 'Ничего не найдено по запросу',
                    'en' => 'Nothing found for query',
                ],
                'description' => 'Текст когда ничего не найдено (перед строкой запроса)',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.product_default_title'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Товар',
                    'ru' => 'Товар',
                    'en' => 'Product',
                ],
                'description' => 'Фолбек-название товара, если нет title',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.placeholder'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Я шукаю…',
                    'ru' => 'Я ищу…',
                    'en' => 'Search…',
                ],
                'description' => 'Placeholder поля поиска',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.suggest.loading'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Шукаю…',
                    'ru' => 'Ищу…',
                    'en' => 'Searching…',
                ],
                'description' => 'Текст загрузки подсказок поиска',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.suggest.go_to_category'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Перейти у категорію',
                    'ru' => 'Перейти в категорию',
                    'en' => 'Go to category',
                ],
                'description' => 'Заголовок блока категорий в подсказках поиска',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'search.suggest.not_found'],
            [
                'group' => 'search',
                'value' => [
                    'uk' => 'Нічого не знайдено для ":q"',
                    'ru' => 'Ничего не найдено по запросу «:q»',
                    'en' => 'Nothing found for “:q”',
                ],
                'description' => 'Шаблон текста для подсказок поиска, :q заменяется на запрос',
            ]
        );

        SiteText::updateOrCreate(
            ['slug' => 'all.close'],
            [
                'group' => 'all',
                'value' => [
                    'uk' => 'Закрити',
                    'ru' => 'Закрыть',
                    'en' => 'Close',
                ],
                'description' => 'Общая подпись для закрытия',
            ]
        );
    }
}
