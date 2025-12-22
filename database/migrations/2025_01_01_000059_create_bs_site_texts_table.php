<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_site_texts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('group', 255)->nullable();
            $table->string('slug', 255);
            $table->json('value');
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'bs_site_texts_slug_unique');
            $table->index(['group'], 'bs_site_texts_group_index');
            $table->index(['group_id'], 'bs_site_texts_group_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_site_texts');
    }
};
