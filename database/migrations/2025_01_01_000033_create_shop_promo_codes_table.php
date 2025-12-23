<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique('spc_code_unique'); // сам промокод (храним в UPPERCASE)
            $table->decimal('percent', 5, 2);                      // 0.01..100.00
            $table->boolean('is_active')->default(true)->index('spc_active_idx');
            $table->timestamp('starts_at')->nullable()->index('spc_starts_idx');
            $table->timestamp('ends_at')->nullable()->index('spc_ends_idx');
            // лимиты
            $table->unsignedInteger('max_uses')->nullable();        // глобальный лимит использований (null = без лимита)
            $table->unsignedInteger('per_client_limit')->default(1);// лимит на клиента (по ТЗ: 1)
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_promo_codes');
    }
};
