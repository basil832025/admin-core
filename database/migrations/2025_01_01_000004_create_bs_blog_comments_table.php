<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_blog_comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('blog_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('author_name', 255)->nullable();
            $table->string('author_email', 255)->nullable();
            $table->text('content');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_approved')->default('0');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['blog_id'], 'blog_comments_blog_id_foreign');
            $table->index(['user_id'], 'blog_comments_user_id_foreign');
            $table->index(['parent_id'], 'blog_comments_parent_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_blog_comments');
    }
};
