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
        Schema::create('bs_loyalty_accounts', function (Blueprint $table) {
            $table->id();

            // Привязка к клиенту (если есть)
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('bs_clients')
                ->nullOnDelete();

            // Привязка по номеру телефона (для гостей и в целом идентификации)
            $table->string('phone', 20)->nullable();

            // Текущий баланс бонусов (агрегат по транзакциям, для ускорения)
            $table->decimal('balance', 10, 2)->default(0);

            $table->timestamps();

            // Уникальный счёт на клиента
            $table->unique('client_id');

            // Уникальный счёт на телефон (MySQL допускает несколько NULL-ов, но один конкретный номер)
            $table->unique('phone');

            // Для быстрых выборок
            $table->index('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bs_loyalty_accounts');
    }
};
