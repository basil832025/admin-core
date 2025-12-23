<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clients_id')->nullable()->constrained('bs_clients')->nullOnDelete();
            $table->string('number', 32)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->enum('status', ['cart','new','processing','shipped','delivered','cancelled','on_hold','filling','molding','baking','prepared','assembled'])->default('new');
            $table->string('currency');
            $table->decimal('shipping_price')->nullable();
            $table->string('shipping_method')->nullable();
            $table->string('short_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('client_address_id')->nullable();
            $table->foreign('client_address_id')
                ->references('id')
                ->on('bs_client_addresses')
                ->nullOnDelete();
            $table->json('status_times')->nullable();
            $table->text('extra_reason')->nullable();
            $table->date('dat')
                ->nullable()
                ->comment('Дата создания заказа (только дата)');
            $table->time('time_start')
                ->nullable()
                ->comment('Время создания заказа');
            $table->time('time_order')
                ->nullable()
                ->comment('Время выполнения заказа');
            $table->date('date_order')
                ->nullable()
                ->comment('Дата выполнения заказа');
            $table->boolean('self_pickup')
                ->default(false)
                ->comment('Самовывоз');
            $table->boolean('as_soon_possible')
                ->default(false)
                ->comment('Как можно скорее');
            $table->tinyInteger('payment')
                ->default(1)
                ->comment('Способ оплаты');
            $table->string('reason_non_payment')
                ->nullable()
                ->comment('Причина неоплаты');
            $table->decimal('sale_prc', 5, 2)
                ->default(0)
                ->comment('Процентная скидка');
            $table->decimal('sale_sum', 12, 2)
                ->default(0)
                ->comment('Суммовая скидка');
            $table->decimal('total_price_sale', 12, 2)
                ->default(0)
                ->comment('Итоговая сумма со скидкой');
            $table->timestamp('paid_at')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_orders');
    }
};
