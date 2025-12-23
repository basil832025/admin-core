<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')
                ->comment('Название валюты на вашем языке (например «Українська»)');
            $table->string('code', 5)
                ->unique()
                ->comment('Код валюты (uk, en, ru)');
            $table->unsignedInteger('position')
                ->default(0)
                ->comment('Порядок сортировки языка в списках');
            $table->boolean('active')
                ->default(true)
                ->comment('Активеность');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_currencies');
    }
};
