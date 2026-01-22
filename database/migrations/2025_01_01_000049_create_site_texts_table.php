<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_site_texts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')
                ->nullable()
                ->index();
            $table->string('group')->nullable()->index(); // напр. 'header', 'footer'
            $table->string('slug')->unique();             // напр. 'menu.all_pies'
            $table->json('value');                        // переводы (Spatie Translatable)
            $table->string('description')->nullable();    // подсказка в админке
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_site_texts');
    }
};
