<?php

return [
    'nav' => [
        'navigation_label'   => 'Словарь (переводы)',
        'model_label'        => 'Строка перевода',
        'plural_model_label' => 'Строки перевода',
    ],

    'fields' => [
        'group_id'      => 'Группа',
        'slug'          => 'Слаг',
        'description'   => 'Описание',
        'value'         => 'Заголовок',
        'group_slug'    => 'Системное имя (slug)',
        'group_title'   => 'Название',
    ],

    'columns' => [
        'group'         => 'Группа',
        'slug'          => 'Слаг',
        'value'         => 'Текст (текущая локаль)',
        'updated_at'    => 'Обновлено',
    ],

    'filters' => [
        'group_id'      => 'Группа',
    ],

    'actions' => [
        'new_group'         => 'Новая группа',
        'edit_group'        => 'Редактировать',
        'create_group'      => 'Создать группу',
        'save_group'        => 'Сохранить',
    ],

    'modals' => [
        'new_group_heading' => 'Новая группа',
        'edit_group_heading' => 'Редактировать группу',
    ],

    'helpers' => [
        'slug_example'   => 'Напр.: header.menu.all_pies',
        'copy_message'   => 'Скопировано',
    ],
];

