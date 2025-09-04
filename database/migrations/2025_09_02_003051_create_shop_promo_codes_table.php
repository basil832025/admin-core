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
        Schema::create('shop_promo_codes', function (Blueprint $table) {
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

        // CHECK-и (если СУБД поддерживает)
        try {
            DB::statement("ALTER TABLE shop_promo_codes
                ADD CONSTRAINT spc_percent_chk CHECK (percent > 0 AND percent <= 100)");
            DB::statement("ALTER TABLE shop_promo_codes
                ADD CONSTRAINT spc_limits_chk CHECK (per_client_limit >= 1)");
        } catch (\Throwable $e) {
            // пропускаем на сборках без CHECK
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_promo_codes');
    }
};
