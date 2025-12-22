<?php

return [
    'nav' => [
        'navigation_label'   => 'Dictionary (translations)',
        'model_label'        => 'Translation string',
        'plural_model_label' => 'Translation strings',
    ],

    'fields' => [
        'group_id'      => 'Group',
        'slug'          => 'Slug',
        'description'   => 'Description',
        'value'         => 'Title',
        'group_slug'    => 'System name (slug)',
        'group_title'   => 'Name',
    ],

    'columns' => [
        'group'         => 'Group',
        'slug'          => 'Slug',
        'value'         => 'Text (current locale)',
        'updated_at'    => 'Updated',
    ],

    'filters' => [
        'group_id'      => 'Group',
    ],

    'actions' => [
        'new_group'         => 'New group',
        'edit_group'       => 'Edit',
        'create_group'      => 'Create group',
        'save_group'       => 'Save',
    ],

    'modals' => [
        'new_group_heading' => 'New group',
        'edit_group_heading' => 'Edit group',
    ],

    'helpers' => [
        'slug_example'   => 'E.g.: header.menu.all_pies',
        'copy_message'    => 'Copied',
    ],
];

