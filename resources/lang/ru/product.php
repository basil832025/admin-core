<?php

return [
    'nav' => [
        'navigation_label'   => 'Товары',
        'model_label'        => 'Товар',
        'plural_model_label' => 'Товары',
    ],

    'tabs' => [
        'main'           => 'Основные',
        'images'         => 'Изображения',
        'characteristics'=> 'Характеристики',
        'seo'            => 'SEO',
        'calculation'    => 'Калькуляция',
        'variations'     => 'Вариации',
    ],

    'sections' => [
        'prices'        => 'Цены',
        'stock'         => 'Склад',
        'extra'         => 'Дополнительно',
        'props'         => 'Свойства товара',
        'statuses'      => 'Статусы',
        'associations'  => 'Ассоциации',
        'gallery'       => 'Галерея',
        'calc_versions' => 'Версии калькуляции',
        'calc_items'    => 'Состав (позиции)',
        'calc_summary'  => 'Итог',
    ],

    'fields' => [
        'title'             => 'Название',
        'description'       => 'Описание',
        'price'             => 'Цена',
        'old_price'         => 'Старая цена',
        'sku'               => 'Артикул',
        'quantity'          => 'Остаток',
        'dop_info'          => 'Калькуляция/Доп. информация',
        'short_name'        => 'Короткое название',
        'slug'              => 'Slug',
        'in_stock'          => 'Есть в наличии',
        'is_new'            => 'Новинка',
        'is_hit'            => 'Хит',
        'is_home'           => 'На главной',
        'category_id'       => 'Категория',
        'categories'        => 'Доп. категории',
        'categories_helper' => 'Необязательно. Можно выбрать одну или несколько.',
        'main_image'        => 'Главное изображение',
        'main_image_small'        => 'Миниатюра',
        'code2' => 'Код з програми',
        'images'            => 'Галерея',
        // Calculation
        'calculations'      => 'Калькуляции для товара',
        'calc_name'         => 'Название/Версия',
        'valid_from'        => 'С даты',
        'valid_to'          => 'По дату',
        'valid_to_helper'   => 'Пусто — бессрочно',
        'component_product' => 'Компонент',
        'qty'               => 'Кол-во',
        'short_desc'               => 'Краткое описание',
        'unit_cost'         => 'С/с за 1',
        'row_cost'          => 'Сумма с/с',
        'total_cost'        => 'Итоговая себестоимость',
        'price_for_value'   => 'Цена: :value',
        'calc_items'   => 'Позиции',

    ],

    'placeholders' => [
        'no_category_label'   => 'Нет категории',
        'no_category_content' => 'Сначала выберите категорию',
        'code2_placeholder'   => 'Напр., ABC-123',
    ],

    'columns' => [
        'title'     => 'Название',
        'category'  => 'Категория',
        'price'     => 'Цена',
        'in_stock'  => 'Есть в наличии',
        'sort'      => 'Сортировка',
        'is_new'    => 'Новинка',
        'is_hit'    => 'Хит',
        'is_home'   => 'На главной',
        'quantity'  => 'Остаток',
        'updated_at'=> 'Обновлено',
    ],

    'filters' => [
        'category'      => 'Категория',
        'category_all'  => 'Все категории',
        'is_new'        => 'Новинка',
        'is_hit'        => 'Хит',
        'is_home'       => 'На главной',
        'code2'         => 'Внешний код',
    ],
];
