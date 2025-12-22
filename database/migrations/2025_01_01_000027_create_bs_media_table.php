<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_media', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('model_type', 255);
            $table->unsignedBigInteger('model_id');
            $table->char('uuid', 36)->nullable();
            $table->string('collection_name', 255);
            $table->string('name', 255);
            $table->string('file_name', 255);
            $table->string('mime_type', 255)->nullable();
            $table->string('disk', 255);
            $table->string('conversions_disk', 255)->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['uuid'], 'bs_media_uuid_unique');
            $table->index(['model_type', 'model_id'], 'bs_media_model_type_model_id_index');
            $table->index(['collection_name'], 'bs_media_collection_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_media');
    }
};
