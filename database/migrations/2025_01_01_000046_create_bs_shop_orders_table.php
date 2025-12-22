<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('clients_id')->nullable();
            $table->unsignedBigInteger('client_address_id')->nullable();
            $table->string('number', 32)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->text('status')->default('new');
            $table->decimal('subtotal', 12, 2)->default('0.00');
            $table->decimal('discount_total', 12, 2)->default('0.00');
            $table->decimal('shipping_total', 12, 2)->default('0.00');
            $table->decimal('tax_total', 12, 2)->default('0.00');
            $table->decimal('grand_total', 12, 2)->default('0.00');
            $table->text('extra_reason');
            $table->string('currency', 255)->default('UAH');
            $table->decimal('shipping_price', 8, 2)->nullable();
            $table->string('shipping_method', 255)->nullable();
            $table->text('notes');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->json('status_times')->nullable();
            $table->date('dat')->nullable();
            $table->text('time_start')->nullable();
            $table->text('time_order')->nullable();
            $table->date('date_order')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->boolean('self_pickup')->default('0');
            $table->boolean('as_soon_possible')->default('0');
            $table->tinyInteger('payment')->default('1');
            $table->string('reason_non_payment', 255)->nullable();
            $table->decimal('sale_prc', 5, 2)->default('0.00');
            $table->decimal('sale_sum', 12, 2)->default('0.00');
            $table->decimal('total_price_sale', 12, 2)->default('0.00');
            $table->string('short_name', 191)->nullable();
            $table->unique(['number'], 'shop_orders_number_unique');
            $table->index(['clients_id'], 'shop_orders_clients_id_foreign');
            $table->index(['client_address_id'], 'shop_orders_client_address_id_foreign');
            $table->index(['paid_at'], 'bs_shop_orders_paid_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_orders');
    }
};
