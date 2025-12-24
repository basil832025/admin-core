<?php

/**
 * Улучшенный скрипт для объединения миграций
 * Правильно объединяет миграции создания таблиц с миграциями добавления колонок
 */

$oldDir = __DIR__ . '/migrations_old';
$newDir = __DIR__ . '/migrations';

if (!is_dir($newDir)) {
    mkdir($newDir, 0755, true);
}

// Карта переименования таблиц
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

function getFinalTableName($tableName, $renameMap) {
    return $renameMap[$tableName] ?? $tableName;
}

// Извлекает все строки определения колонок из alter миграции
function extractTableDefinitions($content, $targetTable) {
    $definitions = [];

    // Ищем Schema::table для нужной таблицы
    $pattern = '/Schema::table\([\'"](?:[^\'"]*' . preg_quote($targetTable, '/') . '[^\'"]*)[\'"].*?function\s*\([^)]*\)\s*\{((?:[^{}]++|\{(?:[^{}]++|\{[^{}]*+\})*+\})*+)\}\s*\)\s*;/s';

    if (preg_match($pattern, $content, $matches)) {
        $body = $matches[1];

        // Извлекаем все строки с $table-> (исключая dropColumn, dropForeign и т.д.)
        preg_match_all('/\$table->(?!drop)([^;]+);/s', $body, $lines);

        foreach ($lines[0] as $line) {
            // Удаляем after() модификаторы, так как порядок в create не критичен
            $line = preg_replace('/->after\([^)]+\)/', '', $line);
            $line = trim($line);

            // Убираем лишние пробелы
            $line = preg_replace('/\s+/', ' ', $line);
            $line = str_replace('$table->', '$table->', $line); // нормализуем

            if (!empty($line) && !preg_match('/dropColumn|dropForeign|dropIndex|dropUnique/', $line)) {
                $definitions[] = $line;
            }
        }
    }

    return $definitions;
}

// Получаем все файлы миграций
$files = glob($oldDir . '/*.php');
usort($files, function($a, $b) {
    return basename($a) <=> basename($b);
});

$createMigrations = [];
$alterMigrations = [];
$dataMigrations = []; // convert, refactor и т.д.

foreach ($files as $file) {
    $filename = basename($file);
    $content = file_get_contents($file);

    // Пропускаем миграцию переименования
    if (strpos($filename, 'rename_tables_with_bs_prefix') !== false) {
        continue;
    }

    // Определяем миграции создания таблиц
    if (preg_match('/create_([a-z_]+)(?:_table)?\.php$/i', $filename, $matches)) {
        $possibleTableName = $matches[1];

        // Извлекаем имя таблицы из кода
        if (preg_match("/Schema::create\(['\"](\w+)['\"]/", $content, $codeMatches)) {
            $actualTableName = $codeMatches[1];
            $finalTableName = getFinalTableName($actualTableName, $renameMap);

            $createMigrations[$finalTableName] = [
                'file' => $file,
                'original_name' => $actualTableName,
                'content' => $content,
                'filename' => $filename,
            ];
        }
    }
    // Определяем миграции добавления/изменения колонок
    elseif (preg_match('/add_.+_to_(.+)(?:_table)?\.php$/i', $filename, $matches) ||
            preg_match('/alter_(.+)(?:_table)?\.php$/i', $filename, $matches) ||
            preg_match('/extend_(.+)(?:_table)?\.php$/i', $filename, $matches)) {

        $possibleTableName = $matches[1];
        $finalTableName = getFinalTableName($possibleTableName, $renameMap);

        // Проверяем в коде - может быть уже с префиксом bs_
        if (preg_match("/Schema::table\(['\"](\w+)['\"]/", $content, $codeMatches)) {
            $finalTableName = $codeMatches[1];
        }

        if (!isset($alterMigrations[$finalTableName])) {
            $alterMigrations[$finalTableName] = [];
        }

        $alterMigrations[$finalTableName][] = [
            'file' => $file,
            'content' => $content,
            'filename' => $filename,
        ];
    }
    // Миграции преобразования данных
    elseif (preg_match('/(convert_|refactor_)/i', $filename)) {
        $dataMigrations[] = [
            'file' => $file,
            'filename' => $filename,
            'content' => $content,
        ];
    }
}

echo "Найдено миграций создания таблиц: " . count($createMigrations) . "\n";
echo "Найдено таблиц с миграциями изменения: " . count($alterMigrations) . "\n";
echo "Найдено миграций преобразования данных: " . count($dataMigrations) . "\n\n";

// Обрабатываем каждую таблицу
$processed = 0;
foreach ($createMigrations as $finalTableName => $createData) {
    $content = $createData['content'];
    $originalTableName = $createData['original_name'];

    // Извлекаем код создания таблицы
    $pattern = '/Schema::create\([\'"]([^\'"]+)[\'"].*?function\s*\([^)]*\)\s*\{((?:[^{}]++|\{(?:[^{}]++|\{[^{}]*+\})*+\})*+)\}\s*\)\s*;/s';

    if (preg_match($pattern, $content, $matches)) {
        $actualTableName = $matches[1];
        $tableDefinition = $matches[2];

        // Собираем все дополнительные колонки из alter миграций
        $additionalColumns = [];
        if (isset($alterMigrations[$finalTableName])) {
            foreach ($alterMigrations[$finalTableName] as $alterData) {
                $alterDefs = extractTableDefinitions($alterData['content'], $finalTableName);
                $additionalColumns = array_merge($additionalColumns, $alterDefs);
            }
        }

        // Разбиваем определение таблицы на строки
        $tableLines = explode("\n", $tableDefinition);
        $resultLines = [];

        // Обрабатываем существующие строки
        $hasTimestamps = false;
        $hasSoftDeletes = false;
        $timestampsIndex = -1;
        $softDeletesIndex = -1;

        foreach ($tableLines as $index => $line) {
            $trimmed = trim($line);

            if (preg_match('/\$table->timestamps\(/', $trimmed)) {
                $hasTimestamps = true;
                $timestampsIndex = $index;
            }
            if (preg_match('/\$table->softDeletes\(/', $trimmed)) {
                $hasSoftDeletes = true;
                $softDeletesIndex = $index;
            }

            // Пропускаем пустые строки в начале
            if (empty($trimmed) && count($resultLines) === 0) {
                continue;
            }

            $resultLines[] = $line;
        }

        // Добавляем дополнительные колонки перед timestamps/softDeletes
        if (!empty($additionalColumns)) {
            $insertIndex = $hasSoftDeletes && $softDeletesIndex >= 0 ? $softDeletesIndex :
                          ($hasTimestamps && $timestampsIndex >= 0 ? $timestampsIndex : count($resultLines));

            $additionalLines = [];
            foreach ($additionalColumns as $col) {
                // Форматируем строку
                $formatted = '            ' . $col . ';';
                // Восстанавливаем многострочные определения
                $formatted = preg_replace('/\s+/', ' ', $formatted);
                $additionalLines[] = $formatted;
            }

            // Вставляем перед timestamps/softDeletes
            array_splice($resultLines, $insertIndex, 0, $additionalLines);
        }

        // Формируем новую миграцию
        $newContent = "<?php\n\n";
        $newContent .= "use Illuminate\\Database\\Migrations\\Migration;\n";
        $newContent .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
        $newContent .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
        $newContent .= "return new class extends Migration\n";
        $newContent .= "{\n";
        $newContent .= "    public function up(): void\n";
        $newContent .= "    {\n";
        $newContent .= "        Schema::create('{$finalTableName}', function (Blueprint \$table) {\n";
        $newContent .= implode("\n", $resultLines) . "\n";
        $newContent .= "        });\n";
        $newContent .= "    }\n\n";
        $newContent .= "    public function down(): void\n";
        $newContent .= "    {\n";
        $newContent .= "        Schema::dropIfExists('{$finalTableName}');\n";
        $newContent .= "    }\n";
        $newContent .= "};\n";

        // Сохраняем
        $safeTableName = str_replace('bs_', '', $finalTableName);
        $newFilename = "2025_01_01_" . str_pad($processed, 6, '0', STR_PAD_LEFT) . "_create_{$safeTableName}_table.php";
        file_put_contents($newDir . '/' . $newFilename, $newContent);
        echo "Создан файл: {$newFilename} для таблицы {$finalTableName}\n";
        $processed++;
    }
}

// Копируем системные миграции (permission_tables, cache, jobs, users)
$systemFiles = [
    '0001_01_01_000000_create_users_table.php',
    '0001_01_01_000001_create_cache_table.php',
    '0001_01_01_000002_create_jobs_table.php',
    '2025_08_08_140502_create_permission_tables.php',
];

foreach ($systemFiles as $sysFile) {
    $sysPath = $oldDir . '/' . $sysFile;
    if (file_exists($sysPath)) {
        copy($sysPath, $newDir . '/' . $sysFile);
        echo "Скопирован системный файл: {$sysFile}\n";
    }
}

// Копируем миграции преобразования данных
foreach ($dataMigrations as $dataMig) {
    copy($dataMig['file'], $newDir . '/' . $dataMig['filename']);
    echo "Скопирован файл преобразования: {$dataMig['filename']}\n";
}

// Копируем миграцию site_text_groups (создает таблицу и изменяет другую)
$siteTextGroupsFile = $oldDir . '/2025_10_22_021020_create_site_text_groups.php';
if (file_exists($siteTextGroupsFile)) {
    copy($siteTextGroupsFile, $newDir . '/2025_10_22_021020_create_site_text_groups.php');
    echo "Скопирован файл site_text_groups (сложная логика)\n";
}

echo "\nГотово! Объединенные миграции сохранены в {$newDir}\n";
echo "Обработано таблиц: {$processed}\n";


