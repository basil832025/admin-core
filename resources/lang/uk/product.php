<?php

return [
    // Навигация и названия моделей
    'nav' => [
        'cluster'            => 'Продукти',
        'group'              => 'Магазин',
        'navigation_label'   => 'Товари',
        'model_label'        => 'Товар',
        'plural_model_label' => 'Товари',
    ],

    // Вкладки формы
    'tabs' => [
        'main'          => 'Основні',
        'images'        => 'Зображення',
        'characteristics'=> 'Характеристики',
        'seo'           => 'SEO',
        'calculation'   => 'Калькуляція',
        'variations'    => 'Варіації',
    ],
    'pages' => [
        'list_title'       => 'Список товарів',
        'create_title'     => 'Створити товар',
        'edit_title'       => 'Редагувати товар',
        'edit_heading'     => 'Редагувати товар',
        'edit_breadcrumb'  => 'Редагувати',
        'create_breadcrumb'  => 'Створити',
    ],

    'actions' => [
        'save'   => 'Зберегти',
        'cancel' => 'Скасувати',
        'delete' => 'Видалити',
        'create' => 'Створити',
        'create_another' => 'Створити та стоврити наступне',
    ],
    // Секции формы
    'sections' => [
        'prices'        => 'Ціни',
        'stock'         => 'Склад',
        'extra'         => 'Додатково',
        'props'         => 'Властивості товару',
        'statuses'      => 'Статуси',
        'associations'  => 'Асоціації',
        'gallery'       => 'Галерея',
        'calc_versions' => 'Версії калькуляції',
        'calc_items'    => 'Склад (позиції)',
        'calc_summary'  => 'Підсумок',
    ],

    // Поля формы
    'fields' => [
        'title'             => 'Назва',
        'description'       => 'Опис',
        'price'             => 'Ціна',
        'old_price'         => 'Стара ціна',
        'sku'               => 'Артикул',
        'quantity'          => 'Залишок',
        'dop_info'          => 'Калькуляція/Додаткова інформація',
        'short_name'        => 'Коротка назва',
        'slug'              => 'Slug',
        'in_stock'          => 'Є в наявності',
        'is_new'            => 'Новинка',
        'is_hit'            => 'Хіт',
        'is_home'           => 'На головній',
        'category_id'       => 'Категорія',
        'categories'        => 'Додаткові категорії',
        'categories_helper' => 'Необов’язково. Можна обрати одну або декілька.',
        'main_image'        => 'Головне зображення',
        'main_image_small'        => 'Мініатюра',
        'images'            => 'Галерея',
        // Calculation
        'calculations'      => 'Калькуляції для товару',
        'calc_name'         => 'Назва/Версія',
        'valid_from'        => 'З дати',
        'valid_to'          => 'По дату',
        'valid_to_helper'   => 'Порожньо — безстроково',
        'component_product' => 'Компонент',
        'code2' => 'Код з програми',
        'qty'               => 'К-сть',
        'short_desc'               => 'Короткий опис',
        'unit_cost'         => 'С/в за 1',
        'row_cost'          => 'Сума с/в',
        'total_cost'        => 'Підсумкова собівартість',
        'price_for_value'   => 'Ціна: :value',
        'calc_items'   => 'Позиції',
    ],

    // Плейсхолдеры/сообщения
    'placeholders' => [
        'no_category_label'   => 'Немає категорії',
        'no_category_content' => 'Спочатку оберіть категорію',
        'code2_placeholder'   => 'Напр., ABC-123',
    ],

    // Колонки таблиці
    'columns' => [
        'title'     => 'Назва',
        'category'  => 'Категорія',
        'price'     => 'Ціна',
        'in_stock'  => 'В наявності',
        'sort'      => 'Сортування',
        'is_new'    => 'Новинка',
        'is_hit'    => 'Хіт',
        'is_home'   => 'На головній',
        'quantity'  => 'Залишок',
        'sku'  => 'sku',

        'updated_at'=> 'Оновлено',
    ],

    // Фільтри таблиці
    'filters' => [
        'category'      => 'Категорія',
        'category_all'  => 'Усі категорії',
        'is_new'        => 'Новинка',
        'is_hit'        => 'Хіт',
        'is_home'       => 'На головній',
        'code2'         => 'Зовнішній код',
    ],
];
