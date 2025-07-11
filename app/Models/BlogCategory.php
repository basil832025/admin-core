<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
class BlogCategory extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'image',
    ];

    protected $casts = [
        'name'             => 'array',
        'description'      => 'array',
        'meta_title'       => 'array',
        'meta_description' => 'array',
        'meta_keywords'    => 'array',
        'is_active'        => 'boolean',
    ];
    // список переводимых полей
    public $translatable = [
        'name',
        'content',
    ];
}
