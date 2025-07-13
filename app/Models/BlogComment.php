<?php
// app/Models/BlogComment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'blog_id',
        'user_id',
        'author_name',
        'author_email',
        'content',
        'parent_id',
        'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    /**
     * Запись блога, к которой относится комментарий
     */
    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }

    /**
     * Автор-юзер (если привязан к таблице users)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Родительский комментарий (для вложенности)
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Дочерние комментарии (ответы)
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
