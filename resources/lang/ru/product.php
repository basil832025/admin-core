<?php

return [
    // Навигация и названия моделей
    'nav' => [
        'cluster'            => 'Продукты',
        'group'              => 'Магазин',
        'navigation_label'   => 'Товары',
        'model_label'        => 'Товар',
        'plural_model_label' => 'Товары',
    ],

    // Вкладки формы
    'tabs' => [
        'main'           => 'Основные',
        'images'         => 'Изображения',
        'characteristics'=> 'Характеристики',
        'seo'            => 'SEO',
        'calculation'    => 'Калькуляция',
        'variations'     => 'Вариации',
    ],
    
    'pages' => [
        'list_title'       => 'Список товаров',
        'create_title'     => 'Создать товар',
        'edit_title'       => 'Редактировать товар',
        'edit_heading'     => 'Редактировать товар',
        'edit_breadcrumb'  => 'Редактировать',
        'create_breadcrumb'  => 'Создать',
    ],
    'actions' => [
        'save'   => 'Сохранить',
        'cancel' => 'Отменить',
        'delete' => 'Удалить',
        'create' => 'Создать',
        'create_another' => 'Создать и создать следующее',
    ],
    
    // Секции формы
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

    // Поля формы
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
        'is_promo'          => 'Акция',
        'is_vegan'          => 'Веган',
        'is_product_of_day' => 'Товар дня',
        'is_spicy'          => 'Острый',
        'exclude_from_promotions' => 'Не участвует в акциях',
        'category_id'       => 'Категория',
        'categories'        => 'Доп. категории',
        'categories_helper' => 'Необязательно. Можно выбрать одну или несколько.',
        'main_image'        => 'Главное изображение',
        'main_image_small'  => 'Миниатюра',
        'images'            => 'Галерея',
        'code2'             => 'Внешний код',
        // Calculation
        'calculations'      => 'Калькуляции для товара',
        'calc_name'         => 'Название/Версия',
        'valid_from'        => 'С даты',
        'valid_to'          => 'По дату',
        'valid_to_helper'   => 'Пусто — бессрочно',
        'component_product' => 'Компонент',
        'qty'               => 'Кол-во',
        'short_desc'        => 'Краткое описание',
        'unit_cost'         => 'С/с за 1',
        'row_cost'          => 'Сумма с/с',
        'total_cost'        => 'Итоговая себестоимость',
        'price_for_value'   => 'Цена: :value',
        'calc_items'        => 'Позиции',
    ],

    // Плейсхолдеры/сообщения
    'placeholders' => [
        'no_category_label'   => 'Нет категории',
        'no_category_content' => 'Сначала выберите категорию',
        'code2_placeholder'   => 'Напр., ABC-123',
    ],

    // Колонки таблицы
    'columns' => [
        'title'     => 'Название',
        'category'  => 'Категория',
        'price'     => 'Цена',
        'in_stock'  => 'Есть в наличии',
        'sort'      => 'Сортировка',
        'is_new'    => 'Новинка',
        'is_hit'    => 'Хит',
        'is_home'   => 'На главной',
        'is_promo'  => 'Акция',
        'is_vegan'  => 'Веган',
        'is_product_of_day' => 'Товар дня',
        'is_spicy'  => 'Острый',
        'exclude_from_promotions' => 'Без акций',
        'quantity'  => 'Остаток',
        'sku'       => 'SKU',
        'updated_at'=> 'Обновлено',
    ],

    // Фильтры таблицы
    'filters' => [
        'category'      => 'Категория',
        'category_all'  => 'Все категории',
        'is_new'        => 'Новинка',
        'is_hit'        => 'Хит',
        'is_home'       => 'На главной',
        'is_promo'      => 'Акция',
        'is_vegan'      => 'Веган',
        'is_product_of_day' => 'Товар дня',
        'is_spicy'      => 'Острый',
        'exclude_from_promotions' => 'Не участвует в акциях',
        'code2'         => 'Внешний код',
    ],

    'badges' => [
        'is_promo'          => 'Акция',
        'is_vegan'          => 'Веган',
        'is_hit'            => 'Хит',
        'is_product_of_day' => 'Пирог дня',
        'is_spicy'          => 'Острый',
    ],
];
