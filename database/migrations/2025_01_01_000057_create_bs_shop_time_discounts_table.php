<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_time_discounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('name')->nullable();
            $table->json('days')->nullable();
            $table->text('time_from')->nullable();
            $table->text('time_to')->nullable();
            $table->text('time_type')->default('order_created');
            $table->unsignedInteger('nth_item')->default('1');
            $table->decimal('percent', 5, 2);
            $table->boolean('is_active')->default('1');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('note');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['time_type'], 'shop_time_discounts_time_type_index');
            $table->index(['is_active'], 'shop_time_discounts_is_active_index');
            $table->index(['starts_at'], 'shop_time_discounts_starts_at_index');
            $table->index(['ends_at'], 'shop_time_discounts_ends_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_time_discounts');
    }
};
