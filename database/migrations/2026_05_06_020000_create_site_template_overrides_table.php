<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_site_template_overrides')) {
            return;
        }

        Schema::create('bs_site_template_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('source_path');
            $table->string('engine')->default('blade');
            $table->longText('original_snapshot')->nullable();
            $table->longText('override_body')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('original_hash', 40)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('bs_site_template_overrides')) {
            Schema::drop('bs_site_template_overrides');
        }
    }
};
