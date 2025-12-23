<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_fixed_discounts', function (Blueprint $table) {
            $table->id();
            $table->json('name')->nullable(); // Мультиязычное название (было string(128))
            // процент скидки: от 0.01 до 100.00
            $table->decimal('percent', 5, 2);
            $table->boolean('is_active')->default(true)->index();
            // на будущее: область применения (пока используем только 'all')
            $table->enum('applies_to', ['all', 'client', 'segment'])->default('all')->index();
            // задел под будущее (гибкие условия) — пока null
            $table->json('applies_payload')->nullable();
            // период действия (опционально; можно не заполнять)
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_fixed_discounts');
    }
};
