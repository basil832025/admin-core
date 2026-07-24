<?php

return [
    'nav' => [
        'navigation_group'   => 'Настройки',
        'navigation_label'   => 'Промокоды',
        'model_label'        => 'Промокод',
        'plural_model_label' => 'Промокоды',
    ],

    'sections' => [
        'parameters'    => 'Параметры промокода',
        'scope'         => 'Область действия',
        'statistics'    => 'Статистика (read-only)',
    ],

    'fields' => [
        'code'                      => 'Промокод',
        'discount_type'             => 'Тип скидки',
        'percent'                   => 'Скидка %',
        'amount'                    => 'Скидка, грн',
        'currency_suffix'           => 'грн',
        'is_active'                 => 'Активен',
        'starts_at'                 => 'Начало действия',
        'ends_at'                   => 'Окончание действия',
        'max_uses'                  => 'Глобальный лимит использований',
        'fulfillment_method'       => 'Способ получения',
        'per_client_limit'          => 'Лимит на клиента',
        'note'                      => 'Примечание',
        'categories'                => 'Категории (группы)',
        'products'                  => 'Товары',
        'characteristics'           => 'Характеристики (любой value)',
        'characteristic_values'     => 'Значения характеристик',
        'used_total'                => 'Использований всего',
        'remaining'                 => 'Осталось глобальных использований',
    ],

    'columns' => [
        'code'          => 'Код',
        'discount_type' => 'Тип',
        'discount'      => 'Скидка',
        'fulfillment_method' => 'Получение',
        'is_active'     => 'Активен',
        'starts_at'     => 'Начало',
        'ends_at'       => 'Окончание',
        'usages_count'  => 'Исп. всего',
        'remaining'     => 'Остаток',
    ],

    'filters' => [
        'is_active'     => 'Активен',
        'active_now'    => 'Действует сейчас',
    ],

    'helpers' => [
        'code_uppercase'            => 'Будет сохранён в UPPERCASE',
        'max_uses_empty'             => 'Пусто = без лимита',
        'characteristic_values_hint' => 'Сначала выберите характеристику(и); здесь отобразятся только их значения.',
        'scope_description'         => 'Ограничь промокод по группам, товарам и/или характеристикам',
        'fixed_amount_scope'        => 'Фиксированная сумма применяется только к товарам в области действия промокода. Если скидка больше суммы подходящих товаров — остаток сгорает.',
    ],

    'options' => [
        'fulfillment_method' => [
            'delivery' => 'Доставка',
            'pickup' => 'Самовывоз',
            'all' => 'Все',
        ],

        'discount_type' => [
            'percent' => 'Процентная',
            'fixed' => 'Фиксированная сумма',
        ],
    ],
];
