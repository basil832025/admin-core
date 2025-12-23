<?php

/**
 * Скрипт для объединения миграций
 * Объединяет миграции создания таблиц с миграциями добавления колонок
 * Учитывает переименование таблиц с префиксом bs_
 */

$oldDir = __DIR__ . '/migrations_old';
$newDir = __DIR__ . '/migrations';

if (!is_dir($newDir)) {
    mkdir($newDir, 0755, true);
}

// Карта переименования таблиц (из миграции rename_tables_with_bs_prefix.php)
$renameMap = [
    'blogs' => 'bs_blogs',
    'blog_categories' => 'bs_blog_categories',
    'blog_comments' => 'bs_blog_comments',
    'currencies' => 'bs_currencies',
    'languages' => 'bs_languages',
    'pages' => 'bs_pages',
    'positions' => 'bs_positions',
    'settings' => 'bs_settings',
    'clients' => 'bs_clients',
    'client_addresses' => 'bs_client_addresses',
    'characteristics' => 'bs_characteristics',
    'characteristic_values' => 'bs_characteristic_values',
    'characteristic_categories' => 'bs_characteristic_categories',
    'characteristic_product' => 'bs_characteristic_product',
    'variations' => 'bs_variations',
    'variation_characteristic_value' => 'bs_variation_characteristic_value',
    'category_characteristic' => 'bs_category_characteristic',
    'category_variation' => 'bs_category_variation',
    'products' => 'bs_products',
    'product_images' => 'bs_product_images',
    'product_categories' => 'bs_product_categories',
    'product_product_category' => 'bs_product_product_category',
    'product_item_modifiers' => 'bs_product_item_modifiers',
    'product_characteristic_value' => 'bs_product_characteristic_value',
    'product_variation' => 'bs_product_variation',
    'product_calculations' => 'bs_product_calculations',
    'product_calculation_items' => 'bs_product_calculation_items',
    'kitchen_tickets' => 'bs_kitchen_tickets',
    'kitchen_ticket_items' => 'bs_kitchen_ticket_items',
    'kitchen_ticket_events' => 'bs_kitchen_ticket_events',
    'shop_orders' => 'bs_shop_orders',
    'shop_order_items' => 'bs_shop_order_items',
    'shop_fixed_discounts' => 'bs_shop_fixed_discounts',
    'shop_promo_codes' => 'bs_shop_promo_codes',
    'shop_promo_code_categories' => 'bs_shop_promo_code_categories',
    'shop_promo_code_products' => 'bs_shop_promo_code_products',
    'shop_promo_code_characteristics' => 'bs_shop_promo_code_characteristics',
    'shop_promo_code_characteristic_values' => 'bs_shop_promo_code_characteristic_values',
    'shop_promo_code_usages' => 'bs_shop_promo_code_usages',
    'shop_time_discounts' => 'bs_shop_time_discounts',
    'shop_time_discount_categories' => 'bs_shop_time_discount_categories',
    'shop_time_discount_products' => 'bs_shop_time_discount_products',
    'shop_time_discount_characteristics' => 'bs_shop_time_discount_characteristics',
    'shop_time_discount_characteristic_values' => 'bs_shop_time_discount_characteristic_values',
];

// Функция для получения финального имени таблицы (с учетом переименования)
function getFinalTableName($tableName, $renameMap) {
    return $renameMap[$tableName] ?? $tableName;
}

// Получаем все файлы миграций и сортируем по дате
$files = glob($oldDir . '/*.php');
usort($files, function($a, $b) {
    return basename($a) <=> basename($b);
});

$createMigrations = [];
$alterMigrations = [];
$otherMigrations = [];

foreach ($files as $file) {
    $filename = basename($file);
    $content = file_get_contents($file);

    // Пропускаем миграцию переименования - её обработаем отдельно
    if (strpos($filename, 'rename_tables_with_bs_prefix') !== false) {
        continue;
    }

    // Определяем миграции создания таблиц
    if (preg_match('/create_([a-z_]+)_table\.php$/i', $filename, $matches) ||
        preg_match('/create_([a-z_]+)\.php$/i', $filename, $matches)) {
        $tableName = $matches[1];
        $finalTableName = getFinalTableName($tableName, $renameMap);

        // Извлекаем имя таблицы из кода (может отличаться от имени файла)
        if (preg_match("/Schema::create\(['\"](\w+)['\"]/", $content, $codeMatches)) {
            $actualTableName = $codeMatches[1];
            $finalTableName = getFinalTableName($actualTableName, $renameMap);
            $createMigrations[$finalTableName] = [
                'file' => $file,
                'original_name' => $actualTableName,
                'content' => $content,
            ];
        }
    }
    // Определяем миграции добавления колонок
    elseif (preg_match('/add_(.+)_to_([a-z_]+)_table\.php$/i', $filename, $matches) ||
            preg_match('/add_(.+)_to_([a-z_]+)\.php$/i', $filename, $matches)) {
        $tableName = $matches[2];
        $finalTableName = getFinalTableName($tableName, $renameMap);

        // Проверяем, может быть таблица уже с префиксом bs_
        if (strpos($filename, 'bs_') !== false) {
            // Ищем имя таблицы в коде
            if (preg_match("/Schema::table\(['\"](\w+)['\"]/", $content, $codeMatches)) {
                $finalTableName = $codeMatches[1];
            }
        }

        if (!isset($alterMigrations[$finalTableName])) {
            $alterMigrations[$finalTableName] = [];
        }
        $alterMigrations[$finalTableName][] = [
            'file' => $file,
            'content' => $content,
        ];
    }
    // Определяем alter миграции
    elseif (preg_match('/alter_([a-z_]+)_table\.php$/i', $filename, $matches) ||
            preg_match('/alter_([a-z_]+)\.php$/i', $filename, $matches)) {
        $tableName = $matches[1];
        $finalTableName = getFinalTableName($tableName, $renameMap);

        // Проверяем в коде
        if (preg_match("/Schema::table\(['\"](\w+)['\"]/", $content, $codeMatches)) {
            $finalTableName = $codeMatches[1];
        }

        if (!isset($alterMigrations[$finalTableName])) {
            $alterMigrations[$finalTableName] = [];
        }
        $alterMigrations[$finalTableName][] = [
            'file' => $file,
            'content' => $content,
        ];
    }
    // Другие типы миграций (extend, convert, refactor и т.д.)
    else {
        // Пытаемся найти таблицу в коде
        if (preg_match("/Schema::table\(['\"](\w+)['\"]/", $content, $matches)) {
            $tableName = $matches[1];
            if (!isset($alterMigrations[$tableName])) {
                $alterMigrations[$tableName] = [];
            }
            $alterMigrations[$tableName][] = [
                'file' => $file,
                'content' => $content,
            ];
        } else {
            $otherMigrations[] = $file;
        }
    }
}

echo "Найдено миграций создания таблиц: " . count($createMigrations) . "\n";
echo "Найдено таблиц с миграциями изменения: " . count($alterMigrations) . "\n";
echo "Найдено других миграций: " . count($otherMigrations) . "\n\n";

// Обрабатываем каждую таблицу
$processed = 0;
foreach ($createMigrations as $finalTableName => $createData) {
    $content = $createData['content'];
    $originalTableName = $createData['original_name'];

    // Извлекаем код создания таблицы
    if (preg_match('/Schema::create\([\'"]([^\'"]+)[\'"].*?function\s*\(Blueprint\s+\$table\)\s*\{((?:[^{}]++|\{(?:[^{}]++|\{[^{}]*+\})*+\})*+)\}\s*\)\s*;/s', $content, $matches)) {
        $actualTableName = $matches[1];
        $tableDefinition = $matches[2];

        // Добавляем колонки из alter миграций
        if (isset($alterMigrations[$finalTableName])) {
            foreach ($alterMigrations[$finalTableName] as $alterData) {
                $alterContent = $alterData['content'];

                // Извлекаем код добавления колонок
                if (preg_match('/Schema::table\([\'"]' . preg_quote($finalTableName, '/') . '[\'"].*?function\s*\(Blueprint\s+\$table\)\s*\{((?:[^{}]++|\{(?:[^{}]++|\{[^{}]*+\})*+\})*+)\}\s*\)\s*;/s', $alterContent, $alterMatches)) {
                    $alterDefinition = $alterMatches[1];

                    // Удаляем комментарии, но сохраняем код
                    $alterDefinition = preg_replace('/\/\/.*$/m', '', $alterDefinition);
                    $alterDefinition = preg_replace('/\/\*.*?\*\//s', '', $alterDefinition);

                    // Удаляем dropColumn из down метода
                    $alterDefinition = preg_replace('/\$table->dropColumn\([^)]+\);\s*/', '', $alterDefinition);

                    // Добавляем колонки перед timestamps() если есть
                    if (preg_match('/(\$table->timestamps\(\);?\s*)$/s', $tableDefinition, $tsMatches)) {
                        $tableDefinition = str_replace($tsMatches[1], trim($alterDefinition) . "\n            " . $tsMatches[1], $tableDefinition);
                    } elseif (preg_match('/(\$table->softDeletes\(\);?\s*)$/s', $tableDefinition, $sdMatches)) {
                        $tableDefinition = str_replace($sdMatches[1], trim($alterDefinition) . "\n            " . $sdMatches[1], $tableDefinition);
                    } else {
                        // Добавляем в конец
                        $tableDefinition .= "\n            " . trim($alterDefinition);
                    }
                }
            }
        }

        // Формируем новую миграцию с финальным именем таблицы
        $newContent = "<?php\n\n";
        $newContent .= "use Illuminate\\Database\\Migrations\\Migration;\n";
        $newContent .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
        $newContent .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
        $newContent .= "return new class extends Migration\n";
        $newContent .= "{\n";
        $newContent .= "    public function up(): void\n";
        $newContent .= "    {\n";
        $newContent .= "        Schema::create('{$finalTableName}', function (Blueprint \$table) {\n";

        // Очищаем и форматируем определение таблицы
        $tableDefinition = trim($tableDefinition);
        $tableDefinition = preg_replace('/\n\s*\n/', "\n", $tableDefinition); // Удаляем пустые строки
        $tableDefinition = preg_replace('/^\s+/m', '            ', $tableDefinition); // Правильные отступы

        $newContent .= $tableDefinition . "\n";
        $newContent .= "        });\n";
        $newContent .= "    }\n\n";
        $newContent .= "    public function down(): void\n";
        $newContent .= "    {\n";
        $newContent .= "        Schema::dropIfExists('{$finalTableName}');\n";
        $newContent .= "    }\n";
        $newContent .= "};\n";

        // Сохраняем в новый файл
        $newFilename = "2025_01_01_" . str_pad($processed, 6, '0', STR_PAD_LEFT) . "_create_" . str_replace('bs_', '', $finalTableName) . "_table.php";
        file_put_contents($newDir . '/' . $newFilename, $newContent);
        echo "Создан файл: {$newFilename} для таблицы {$finalTableName}\n";
        $processed++;
    }
}

// Копируем другие миграции (permission_tables, cache, jobs, users и т.д.)
$systemMigrations = [
    '0001_01_01_000000_create_users_table.php',
    '0001_01_01_000001_create_cache_table.php',
    '0001_01_01_000002_create_jobs_table.php',
    '2025_08_08_140502_create_permission_tables.php',
];

foreach ($systemMigrations as $sysMig) {
    $sysFile = $oldDir . '/' . $sysMig;
    if (file_exists($sysFile)) {
        copy($sysFile, $newDir . '/' . $sysMig);
        echo "Скопирован системный файл: {$sysMig}\n";
    }
}

// Копируем другие миграции, которые не относятся к созданию/изменению таблиц
foreach ($otherMigrations as $otherFile) {
    $otherFilename = basename($otherFile);
    if (strpos($otherFilename, 'convert_') !== false ||
        strpos($otherFilename, 'refactor_') !== false) {
        // Это миграции преобразования данных - копируем как есть
        copy($otherFile, $newDir . '/' . $otherFilename);
        echo "Скопирован файл преобразования: {$otherFilename}\n";
    }
}

echo "\nГотово! Объединенные миграции сохранены в {$newDir}\n";
echo "Обработано таблиц: {$processed}\n";
