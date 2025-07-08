<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->comment('Название сайта');
            $table->string('logo_path')->nullable()->comment('Путь к логотипу');
            $table->string('favicon_path')->nullable()->comment('Путь к favicon');
            $table->string('phone')->nullable()->comment('Телефон для контакта');
            $table->string('email')->nullable()->comment('Email для контакта');
            $table->json('social_links')->nullable()->comment('Ссылки на соцсети');
            $table->string('default_language_code', 5)->nullable()->comment('Код языка по умолчанию');
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
