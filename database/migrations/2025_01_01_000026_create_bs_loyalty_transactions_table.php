<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_loyalty_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->text('type');
            $table->string('source', 50)->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('remaining_amount', 10, 2)->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['order_id'], 'bs_loyalty_transactions_order_id_foreign');
            $table->index(['account_id', 'type'], 'bs_loyalty_transactions_account_id_type_index');
            $table->index(['account_id', 'order_id'], 'bs_loyalty_transactions_account_id_order_id_index');
            $table->index(['type', 'expires_at'], 'bs_loyalty_transactions_type_expires_at_index');
            $table->index(['expires_at'], 'bs_loyalty_transactions_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_loyalty_transactions');
    }
};
