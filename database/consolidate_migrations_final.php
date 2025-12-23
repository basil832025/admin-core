<?php

/**
 * Финальный скрипт для объединения миграций
 * Правильно объединяет все миграции создания таблиц с миграциями добавления колонок
 */

$oldDir = __DIR__ . '/migrations_old';
$newDir = __DIR__ . '/migrations';

if (!is_dir($newDir)) {
    mkdir($newDir, 0755, true);
}

// Карта переименования таблиц (старое имя => новое имя)
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
    'orders' => 'bs_shop_orders',
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

// Обратная карта для поиска
$reverseRenameMap = array_flip($renameMap);

function getFinalTableName($tableName, $renameMap) {
    // Если таблица уже с префиксом bs_, возвращаем как есть
    if (strpos($tableName, 'bs_') === 0) {
        return $tableName;
    }
    // Иначе применяем карту переименования
    return $renameMap[$tableName] ?? $tableName;
}

// Извлекает все определения колонок из alter миграции
function extractColumnDefinitions($content, $targetTable) {
    $definitions = [];

    // Паттерн для поиска Schema::table с нужной таблицей
    $pattern = '/Schema::table\([\'"]([^\'"]+)[\'"].*?function\s*\([^)]*\)\s*\{((?:[^{}]++|\{(?:[^{}]++|\{[^{}]*+\})*+\})*+)\}\s*\)\s*;/s';

    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tableInCode = $match[1];
            // Проверяем, что это наша таблица (с учетом переименования)
            if ($tableInCode === $targetTable ||
                (strpos($tableInCode, 'bs_') !== 0 && strpos($targetTable, 'bs_') === 0 && str_replace('bs_', '', $targetTable) === $tableInCode)) {

                $body = $match[2];

                // Разбиваем тело на строки для более точного парсинга
                $lines = preg_split('/\r?\n/', $body);
                $currentDefinition = '';
                $inDefinition = false;

                foreach ($lines as $line) {
                    $trimmed = trim($line);

                    // Пропускаем пустые строки
                    if (empty($trimmed)) {
                        continue;
                    }

                    // Пропускаем однострочные комментарии
                    if (preg_match('/^\/\//', $trimmed)) {
                        continue;
                    }

                    // Проверяем, начинается ли строка с $table->
                    if (preg_match('/^\$table->(?!drop)([^;]+)/', $trimmed, $lineMatch)) {
                        // Если есть предыдущее определение, сохраняем его
                        if ($inDefinition && !empty(trim($currentDefinition))) {
                            $def = trim($currentDefinition);
                            // Пропускаем drop-операции и индексы
                            if (!preg_match('/dropColumn|dropForeign|dropIndex|dropUnique|^index\(|^unique\(/', $def)) {
                                // Удаляем after() модификаторы
                                $def = preg_replace('/->after\([^)]+\)/', '', $def);
                                // Удаляем лишние пробелы между операторами
                                $def = preg_replace('/\s*->\s*/', '->', $def);
                                $def = preg_replace('/\s+/', ' ', $def);
                                $def = trim($def);
                                // Удаляем $table-> в начале
                                $def = preg_replace('/^\$table->/', '', $def);
                                // Удаляем точку с запятой в конце
                                $def = rtrim($def, ';');
                                if (!empty($def)) {
                                    $definitions[] = $def;
                                }
                            }
                        }
                        // Начинаем новое определение
                        $currentDefinition = $lineMatch[1];
                        $inDefinition = true;
                    } elseif ($inDefinition) {
                        // Продолжение определения (многострочное)
                        // Проверяем, не является ли это комментарием в строке
                        if (strpos($trimmed, '//') !== false) {
                            // Удаляем комментарий
                            $trimmed = preg_replace('/\/\/.*$/', '', $trimmed);
                            $trimmed = trim($trimmed);
                        }

                        if (!empty($trimmed) && preg_match('/^->/', $trimmed)) {
                            // Добавляем продолжение
                            $currentDefinition .= ' ' . $trimmed;
                        } elseif (strpos($trimmed, ';') !== false) {
                            // Конец определения
                            $trimmed = str_replace(';', '', $trimmed);
                            if (!empty(trim($trimmed)) && preg_match('/^->/', $trimmed)) {
                                $currentDefinition .= ' ' . $trimmed;
                            }
                            $inDefinition = false;
                        }
                    }
                }

                // Сохраняем последнее определение, если есть
                if ($inDefinition && !empty(trim($currentDefinition))) {
                    $def = trim($currentDefinition);
                    if (!preg_match('/dropColumn|dropForeign|dropIndex|dropUnique|^index\(|^unique\(/', $def)) {
                        $def = preg_replace('/->after\([^)]+\)/', '', $def);
                        $def = preg_replace('/\s*->\s*/', '->', $def);
                        $def = preg_replace('/\s+/', ' ', $def);
                        $def = trim($def);
                        $def = preg_replace('/^\$table->/', '', $def);
                        $def = rtrim($def, ';');
                        if (!empty($def)) {
                            $definitions[] = $def;
                        }
                    }
                }
            }
        }
    }

    return $definitions;
}

// Список системных миграций, которые нужно только копировать, не обрабатывать
$systemMigrations = [
    '0001_01_01_000000_create_users_table.php',
    '0001_01_01_000001_create_cache_table.php',
    '0001_01_01_000002_create_jobs_table.php',
    '2025_08_08_140502_create_permission_tables.php',
];

// Получаем все файлы миграций
$files = glob($oldDir . '/*.php');
usort($files, function($a, $b) {
    return basename($a) <=> basename($b);
});

$createMigrations = [];
$alterMigrations = [];
$dataMigrations = [];

foreach ($files as $file) {
    $filename = basename($file);
    $content = file_get_contents($file);

    // Пропускаем миграцию переименования
    if (strpos($filename, 'rename_tables_with_bs_prefix') !== false) {
        continue;
    }

    // Пропускаем системные миграции - они будут скопированы отдельно
    if (in_array($filename, $systemMigrations)) {
        continue;
    }

    // Определяем миграции создания таблиц
    if (preg_match('/create_([a-z_]+)(?:_table)?\.php$/i', $filename, $matches)) {
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

        // Пытаемся найти таблицу в коде
        if (preg_match("/Schema::table\(['\"](\w+)['\"]/", $content, $codeMatches)) {
            $tableInCode = $codeMatches[1];
            $finalTableName = getFinalTableName($tableInCode, $renameMap);

            if (!isset($alterMigrations[$finalTableName])) {
                $alterMigrations[$finalTableName] = [];
            }

            $alterMigrations[$finalTableName][] = [
                'file' => $file,
                'content' => $content,
                'filename' => $filename,
                'table_in_code' => $tableInCode,
            ];
        }
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
                $alterDefs = extractColumnDefinitions($alterData['content'], $finalTableName);
                // Если не нашли для финального имени, пробуем исходное имя из кода
                if (empty($alterDefs)) {
                    $alterDefs = extractColumnDefinitions($alterData['content'], $alterData['table_in_code']);
                }
                $additionalColumns = array_merge($additionalColumns, $alterDefs);
            }
        }

        // Разбиваем определение таблицы на строки
        $tableLines = explode("\n", $tableDefinition);
        $resultLines = [];
        $timestampsIndex = -1;
        $softDeletesIndex = -1;

        foreach ($tableLines as $index => $line) {
            $trimmed = trim($line);

            if (preg_match('/\$table->timestamps\(/', $trimmed)) {
                $timestampsIndex = count($resultLines);
            }
            if (preg_match('/\$table->softDeletes\(/', $trimmed)) {
                $softDeletesIndex = count($resultLines);
            }

            if (!empty($trimmed) || count($resultLines) > 0) {
                $resultLines[] = $line;
            }
        }

        // Добавляем дополнительные колонки перед timestamps/softDeletes
        if (!empty($additionalColumns)) {
            $insertIndex = $softDeletesIndex >= 0 ? $softDeletesIndex :
                          ($timestampsIndex >= 0 ? $timestampsIndex : count($resultLines));

            $additionalLines = [];
            foreach ($additionalColumns as $col) {
                // Форматируем строку правильно
                $col = trim($col);

                // Если определение длинное (с несколькими ->), разбиваем на несколько строк
                if (substr_count($col, '->') > 1) {
                    // Разбиваем по -> и формируем многострочное определение
                    $parts = explode('->', $col);
                    $firstPart = trim(array_shift($parts));
                    $formatted = '            $table->' . $firstPart;
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if (!empty($part)) {
                            $formatted .= "\n                ->" . $part;
                        }
                    }
                    $formatted .= ';';
                } else {
                    // Однострочное определение
                    $col = preg_replace('/\s+/', ' ', $col); // Убираем лишние пробелы
                    $formatted = '            $table->' . $col . ';';
                }

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
        // Удаляем пустые строки в конце
        $cleanLines = array_filter($resultLines, function($line) {
            return trim($line) !== '';
        });
        $newContent .= implode("\n", $cleanLines) . "\n";
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

// Копируем системные миграции (они были пропущены в основном цикле обработки)
foreach ($systemMigrations as $sysFile) {
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

// Копируем миграцию site_text_groups
$siteTextGroupsFile = $oldDir . '/2025_10_22_021020_create_site_text_groups.php';
if (file_exists($siteTextGroupsFile)) {
    copy($siteTextGroupsFile, $newDir . '/2025_10_22_021020_create_site_text_groups.php');
    echo "Скопирован файл site_text_groups (сложная логика)\n";
}

echo "\nГотово! Объединенные миграции сохранены в {$newDir}\n";
echo "Обработано таблиц: {$processed}\n";

