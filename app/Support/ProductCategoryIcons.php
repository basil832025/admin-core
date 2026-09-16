<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductCategoryIcons
{
    public const DEFAULT = 'heroicon-o-folder';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'heroicon-o-folder' => 'Категорія',
            'heroicon-o-tag' => 'Тег',
            'heroicon-o-shopping-bag' => 'Покупки',
            'heroicon-o-cake' => 'Десерти',
            'heroicon-o-gift' => 'Набори',
            'heroicon-o-sparkles' => 'Особливе',
            'heroicon-o-heart' => 'Улюблене',
            'heroicon-o-fire' => 'Гостре',
            'heroicon-o-beaker' => 'Напої',
            'heroicon-o-sun' => 'Сезонне',
            'heroicon-o-star' => 'Популярне',
            'heroicon-o-globe-alt' => 'Рослинне',
            'heroicon-o-cube' => 'Товари',
            'heroicon-o-squares-2x2' => 'Асортимент',
            'heroicon-o-rectangle-stack' => 'Група',
            'heroicon-o-archive-box' => 'Зберігання',
        ];
    }

    public static function valid(?string $icon): string
    {
        $icon = (string) $icon;
        return array_key_exists($icon, static::options()) || array_key_exists($icon, static::customOptions())
            ? $icon
            : static::DEFAULT;
    }

    /** @return array<string, string> */
    public static function customOptions(): array
    {
        $directory = public_path('images/category-icons');
        if (! is_dir($directory)) {
            return [];
        }

        $labels = [
            'all-products' => 'Усі товари', 'pies' => 'Пироги', 'cheese' => 'Сир',
            'meat' => "М’ясо", 'vegan' => 'Веган', 'sweets' => 'Солодощі',
            'traditional' => 'Традиційне', 'combo' => 'Комбо', 'tasting-set' => 'Дегустаційний сет',
            'drinks' => 'Напої', 'sauces' => 'Соуси', 'cakes' => 'Торти', 'dishes' => 'Посуд',
        ];
        $options = [];
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.svg') ?: [] as $path) {
            $name = pathinfo($path, PATHINFO_FILENAME);
            if (preg_match('/^[a-z0-9-]+$/', $name) && static::readSafeSvg($name) !== null) {
                $options["custom:{$name}"] = $labels[$name] ?? Str::headline($name);
            }
        }
        ksort($options);
        return $options;
    }

    public static function customSvg(string $icon): ?HtmlString
    {
        $name = str_starts_with($icon, 'custom:') ? substr($icon, 7) : '';
        $svg = static::readSafeSvg($name);
        return $svg === null ? null : new HtmlString($svg);
    }

    public static function installUploaded(string $sourcePath, string $originalName): string
    {
        if (! is_file($sourcePath) || filesize($sourcePath) > 256 * 1024) {
            throw ValidationException::withMessages(['category_icon_upload' => 'SVG-файл відсутній або перевищує 256 КБ.']);
        }
        return static::installCode((string) file_get_contents($sourcePath), pathinfo($originalName, PATHINFO_FILENAME));
    }

    public static function installCode(string $code, string $name): string
    {
        if (strlen($code) > 256 * 1024) {
            throw ValidationException::withMessages(['category_icon_svg' => 'SVG-код перевищує 256 КБ.']);
        }
        $svg = static::sanitizeSvg($code);
        if ($svg === null) {
            throw ValidationException::withMessages(['category_icon_svg' => 'SVG містить непідтримувані або небезпечні елементи.']);
        }

        $directory = public_path('images/category-icons');
        File::ensureDirectoryExists($directory);
        $base = Str::slug($name) ?: 'category-icon';
        $name = $base;
        for ($suffix = 2; is_file($directory . DIRECTORY_SEPARATOR . $name . '.svg'); $suffix++) {
            $name = $base . '-' . $suffix;
        }
        File::put($directory . DIRECTORY_SEPARATOR . $name . '.svg', $svg);
        return 'custom:' . $name;
    }

    public static function preview(string $code): ?HtmlString
    {
        $svg = static::sanitizeSvg($code);
        return $svg === null ? null : new HtmlString($svg);
    }

    protected static function readSafeSvg(string $name): ?string
    {
        if (! preg_match('/^[a-z0-9-]+$/', $name)) {
            return null;
        }
        $directory = realpath(public_path('images/category-icons'));
        $path = realpath(public_path("images/category-icons/{$name}.svg"));
        if ($directory === false || $path === false || ! str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
            return null;
        }
        $svg = file_get_contents($path);
        return $svg === false ? null : static::sanitizeSvg($svg);
    }

    protected static function sanitizeSvg(string $svg): ?string
    {
        $svg = trim((string) preg_replace('/^\s*<\?xml[^>]*>\s*/i', '', $svg));
        if (! preg_match('/^<svg\b[^>]*>[\s\S]*<\/svg>$/i', $svg)) {
            return null;
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY|<(script|foreignObject|image|iframe|object|embed|style|audio|video)\b|\bon\w+\s*=|\b(?:href|src|style)\s*=|url\s*\(/i', $svg)) {
            return null;
        }
        $svg = preg_replace_callback('/\b(fill|stroke)\s*=\s*(["\'])(.*?)\2/i', function (array $match): string {
            $value = strtolower(trim($match[3]));
            return $match[1] . '=' . $match[2] . (in_array($value, ['none', 'currentcolor'], true) ? $match[3] : 'currentColor') . $match[2];
        }, $svg);
        return preg_replace_callback('/<svg\b([^>]*)>/i', function (array $match): string {
            $attributes = preg_replace('/\s(?:width|height)\s*=\s*["\'][^"\']*["\']/i', '', $match[1]);
            if (! preg_match('/\bfill\s*=/i', $attributes)) {
                $attributes .= ' fill="currentColor"';
            }
            return '<svg width="100%" height="100%" aria-hidden="true"' . $attributes . '>';
        }, $svg, 1) ?: null;
    }
}
