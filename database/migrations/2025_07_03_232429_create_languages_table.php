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
            Schema::create('languages', function (Blueprint $table) {
                $table->id();
                $table->string('name')
                    ->comment('Название языка на вашем языке (например «Українська»)');
                $table->string('code', 5)
                    ->unique()
                    ->comment('Код языка (uk, en, ru)');
                $table->string('country_code', 3)
                    ->comment('Трьохзначный код страны ISO (UA, US, RU)');
                $table->unsignedInteger('position')
                    ->default(0)
                    ->comment('Порядок сортировки языка в списках');
                $table->boolean('active')
                    ->default(true)
                    ->comment('Активен ли язык');
                $table->timestamps();
            });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
