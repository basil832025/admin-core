<?php

$base = require __DIR__ . '/../ru/validation.php';

if (! is_array($base)) {
    $base = [];
}

$base['required'] = 'Поле :attribute є обов\'язковим.';
$base['required_if'] = 'Поле :attribute є обов\'язковим, коли :other має значення :value.';
$base['required_with'] = 'Поле :attribute є обов\'язковим, коли заповнено :values.';
$base['required_without'] = 'Поле :attribute є обов\'язковим, коли :values не заповнено.';

return $base;
