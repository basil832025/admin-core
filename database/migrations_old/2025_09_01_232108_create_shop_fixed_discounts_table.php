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
        Schema::create('shop_fixed_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
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

        // CHECK-ограничение на percent (MySQL 8+; безопасно игнорируется на старых версиях)
        DB::statement("
            ALTER TABLE shop_fixed_discounts
            ADD CONSTRAINT chk_shop_fixed_discounts_percent
            CHECK (percent > 0 AND percent <= 100)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_fixed_discounts');
    }
};
