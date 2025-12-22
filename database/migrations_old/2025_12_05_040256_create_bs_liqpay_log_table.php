<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_liqpay_log', function (Blueprint $table) {
            $table->id();

            $table->dateTime('log_date')->nullable();
            $table->string('signature', 255)->nullable();

            $table->string('payment_id', 64)->nullable();
            $table->string('action', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('paytype', 50)->nullable();
            $table->string('acq_id', 50)->nullable();

            // наш внутренний ID заказа (удобно линковать)
            $table->unsignedBigInteger('shop_order_id')->nullable()->index();

            // оригинальные поля LiqPay
            $table->string('order_id', 64)->nullable();
            $table->string('liqpay_order_id', 64)->nullable();

            $table->text('description')->nullable();

            $table->string('sender_phone', 32)->nullable();
            $table->string('sender_first_name', 191)->nullable();
            $table->string('sender_last_name', 191)->nullable();
            $table->string('sender_card_mask2', 32)->nullable();
            $table->string('sender_card_bank', 191)->nullable();
            $table->string('sender_card_type', 64)->nullable();
            $table->string('sender_card_country', 8)->nullable();

            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 4)->nullable();

            $table->decimal('sender_commission', 10, 2)->nullable();
            $table->decimal('receiver_commission', 10, 2)->nullable();
            $table->decimal('amount_debit', 10, 2)->nullable();
            $table->decimal('amount_credit', 10, 2)->nullable();
            $table->decimal('commission_debit', 10, 2)->nullable();
            $table->decimal('commission_credit', 10, 2)->nullable();

            $table->string('language', 8)->nullable();
            $table->string('create_date', 32)->nullable();
            $table->string('end_date', 32)->nullable();
            $table->string('transaction_id', 64)->nullable();

            // на всякий пожарный – полный json LiqPay
            $table->json('payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_liqpay_log');
    }
};
