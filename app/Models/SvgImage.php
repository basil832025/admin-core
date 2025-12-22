<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SvgImage extends Model
{
    protected $table = 'bs_svg_images';

    protected $fillable = [
        'slug',
        'title',
        'description',
        'svg_code',
        'file_path',
        'color_variants',
        'default_color',
        'is_attr',
    ];
    protected $casts = [
        'color_variants' => 'array',
        'is_attr'        => 'bool',
    ];
    // Удобный скоуп для выборки иконок-характеристик
    public function scopeForAttributes($q)
    {
        return $q->where('is_attr', true);
    }
    // Удобный аксессор: вернёт массив цветов (без пустых)
    public function getColorListAttribute(): array
    {
        $arr = is_array($this->color_variants) ? $this->color_variants : [];
        if ($this->default_color) {
            array_unshift($arr, $this->default_color);
        }
        return array_values(array_filter(array_unique($arr)));
    }
    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        $path = $this->file_path ?? null;
        if (!$path) {
            return null;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        // тут просто собираем урл без storage
        return url($path);
    }
    protected static function booted(): void
    {
        static::saving(function (SvgImage $model) {
            // гарантируем латиницу/дефисы в slug
            $model->slug = trim(strtolower(preg_replace('/[^a-z0-9\-]+/i', '-', $model->slug)), '-');
        });

        static::saved(function (SvgImage $model) {
            // пишем файл в public/image/svg/{slug}.svg
            $dir = public_path('images/svg');
            if (! File::exists($dir)) {
                File::makeDirectory($dir, 0775, true);
            }

            $path = $dir . DIRECTORY_SEPARATOR . $model->slug . '.svg';
            File::put($path, $model->svg_code);

            // сохраняем относительный путь для удобства
            $relative = 'images/svg/' . $model->slug . '.svg';
            if ($model->file_path !== $relative) {
                $model->file_path = $relative;
                // избежать рекурсии saved->saved — сохраняем тихо без триггеров:
                $model->timestamps = false;
                $model->updateQuietly(['file_path' => $relative]);
                $model->timestamps = true;
            }
        });

        static::deleting(function (SvgImage $model) {
            // удалим файл при удалении записи (по желанию)
            if ($model->file_path) {
                $full = public_path($model->file_path);
                if (File::exists($full)) {
                    File::delete($full);
                }
            }
        });
    }

    public function getPublicUrlAttribute(): string
    {
        return asset($this->file_path ?: ('images/svg/' . $this->slug . '.svg'));
    }
}
