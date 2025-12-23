<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_loyalty_transactions', function (Blueprint $table) {
            $table->id();
            // Какому счёту принадлежит операция
            $table->foreignId('account_id')
                ->constrained('bs_loyalty_accounts')
                ->cascadeOnDelete();
            // Привязка к заказу (если операция связана с заказом)
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('bs_shop_orders')
                ->nullOnDelete();
            // Тип операции: начисление / списание / сгорание / корректировка / реверс
            $table->enum('type', ['accrual', 'spend', 'expire', 'adjustment', 'reverse']);
            // Источник операции: order, welcome_bonus, manual, system_expire и т.п.
            $table->string('source', 50)->nullable();
            // Сумма операции:
            //  > 0 для начислений,
            //  < 0 для списаний/сгораний/реверсов
            $table->decimal('amount', 10, 2);
            // Остаток по начислению (чтобы тратить/сжигать по FIFO).
            // Только для type = 'accrual', в остальных случаях может быть null.
            $table->decimal('remaining_amount', 10, 2)->nullable();
            // Дата истечения начисления (для type = 'accrual')
            $table->dateTime('expires_at')->nullable()->index();
            // Дополнительная информация (например, accrual_id для expire, комментарий оператора и т.п.)
            $table->json('meta')->nullable();
            // Кэш баланса после операции (опционально, но удобно для истории)
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->timestamps();
            // Индексы для типичных выборок
            $table->index(['account_id', 'type']);
            $table->index(['account_id', 'order_id']);
            $table->index(['type', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_loyalty_transactions');
    }
};
