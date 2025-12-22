<?php

return [
    'nav' => [
        'navigation_label'   => 'Словник (переклади)',
        'model_label'        => 'Рядок перекладу',
        'plural_model_label' => 'Рядки перекладу',
    ],

    'fields' => [
        'group_id'      => 'Група',
        'slug'          => 'Слаг',
        'description'   => 'Опис',
        'value'         => 'Заголовок',
        'group_slug'    => 'Системне ім\'я (slug)',
        'group_title'   => 'Назва',
    ],

    'columns' => [
        'group'         => 'Група',
        'slug'          => 'Слаг',
        'value'         => 'Текст (поточна локаль)',
        'updated_at'    => 'Оновлено',
    ],

    'filters' => [
        'group_id'      => 'Група',
    ],

    'actions' => [
        'new_group'         => 'Нова група',
        'edit_group'        => 'Редагувати',
        'create_group'      => 'Створити групу',
        'save_group'        => 'Зберегти',
    ],

    'modals' => [
        'new_group_heading' => 'Нова група',
        'edit_group_heading' => 'Редагувати групу',
    ],

    'helpers' => [
        'slug_example'   => 'Напр.: header.menu.all_pies',
        'copy_message'   => 'Скопійовано',
    ],
];

