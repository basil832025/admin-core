<?php

/**
 * Скрипт для поиска и удаления дубликатов миграций
 * Оставляет только правильные версии (без ошибок типа $table->$table, ->->)
 */

$newDir = __DIR__ . '/migrations';
$files = glob($newDir . '/*.php');

$tables = []; // таблица => массив файлов

// Собираем информацию о всех файлах и какие таблицы они создают
foreach ($files as $file) {
    $filename = basename($file);
    $content = file_get_contents($file);

    // Извлекаем имя таблицы из Schema::create
    if (preg_match_all("/Schema::create\(['\"](\w+)['\"]/", $content, $matches)) {
        foreach ($matches[1] as $tableName) {
            if (!isset($tables[$tableName])) {
                $tables[$tableName] = [];
            }
            $tables[$tableName][] = [
                'file' => $file,
                'filename' => $filename,
                'content' => $content,
            ];
        }
    }
}

echo "Найдено уникальных таблиц: " . count($tables) . "\n\n";

$toDelete = [];
$toKeep = [];

// Проверяем каждую таблицу на дубликаты
foreach ($tables as $tableName => $fileList) {
    if (count($fileList) > 1) {
        echo "Дубликаты для таблицы '{$tableName}':\n";

        $bestFile = null;
        $bestScore = -1;

        foreach ($fileList as $fileInfo) {
            $content = $fileInfo['content'];
            $filename = $fileInfo['filename'];

            // Проверяем на ошибки
            $hasErrors = false;

            // Проверяем на $table->$table
            if (preg_match('/\$table->\$table/', $content)) {
                $hasErrors = true;
                echo "  ❌ {$filename} - содержит ошибку \$table->\$table\n";
            }

            // Проверяем на ->->
            if (preg_match('/->->/', $content)) {
                $hasErrors = true;
                echo "  ❌ {$filename} - содержит ошибку ->->\n";
            }

            // Проверяем на двойные точки с запятой ;;
            if (preg_match('/;;/', $content)) {
                $hasErrors = true;
                echo "  ❌ {$filename} - содержит двойные точки с запятой ;;\n";
            }

            // Проверяем на пустые строки перед закрывающими скобками (менее критично, но лучше исправить)
            $hasExtraEmptyLines = preg_match('/\s+\n\s+\n\s+\}\s*\)\s*;/s', $content);

            if (!$hasErrors && !$hasExtraEmptyLines) {
                echo "  ✅ {$filename} - правильный файл\n";
                if ($bestFile === null || $bestScore < 10) {
                    $bestFile = $fileInfo;
                    $bestScore = 10;
                }
            } elseif (!$hasErrors && $hasExtraEmptyLines) {
                echo "  ⚠️  {$filename} - правильный, но с лишними пустыми строками\n";
                if ($bestFile === null || $bestScore < 5) {
                    $bestFile = $fileInfo;
                    $bestScore = 5;
                }
            } elseif ($bestFile === null) {
                // Если нет правильных файлов, оставляем первый
                echo "  ⚠️  {$filename} - с ошибками, но оставляем как резервный\n";
                $bestFile = $fileInfo;
                $bestScore = 0;
            }
        }

        // Удаляем все файлы кроме лучшего
        if ($bestFile) {
            $toKeep[$bestFile['filename']] = true;
            echo "  📌 Оставляем: {$bestFile['filename']}\n";

            foreach ($fileList as $fileInfo) {
                if ($fileInfo['filename'] !== $bestFile['filename']) {
                    $toDelete[] = $fileInfo['file'];
                    echo "  🗑️  Удаляем: {$fileInfo['filename']}\n";
                }
            }
        }
        echo "\n";
    } else {
        // Если файл один, проверяем его на ошибки
        $fileInfo = $fileList[0];
        $content = $fileInfo['content'];
        $filename = $fileInfo['filename'];

        $hasErrors = false;
        if (preg_match('/\$table->\$table/', $content) || preg_match('/->->/', $content) || preg_match('/;;/', $content)) {
            $hasErrors = true;
            echo "⚠️  Файл {$filename} (таблица {$tableName}) содержит ошибки, но других версий нет\n";
        }

        if (!$hasErrors) {
            $toKeep[$filename] = true;
        }
    }
}

echo "\nИтого:\n";
echo "Файлов к удалению: " . count($toDelete) . "\n";
echo "Файлов к сохранению: " . count($toKeep) . "\n\n";

if (count($toDelete) > 0) {
    echo "Удаляем дубликаты...\n";
    foreach ($toDelete as $file) {
        if (unlink($file)) {
            echo "Удален: " . basename($file) . "\n";
        } else {
            echo "Ошибка при удалении: " . basename($file) . "\n";
        }
    }
    echo "\nГотово!\n";
} else {
    echo "Дубликатов не найдено.\n";
}

