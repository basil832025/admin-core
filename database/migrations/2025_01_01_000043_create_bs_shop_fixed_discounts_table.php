<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_fixed_discounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('name')->nullable();
            $table->decimal('percent', 5, 2);
            $table->boolean('is_active')->default('1');
            $table->text('applies_to')->default('all');
            $table->json('applies_payload')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('note');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['is_active'], 'shop_fixed_discounts_is_active_index');
            $table->index(['applies_to'], 'shop_fixed_discounts_applies_to_index');
            $table->index(['starts_at'], 'shop_fixed_discounts_starts_at_index');
            $table->index(['ends_at'], 'shop_fixed_discounts_ends_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_fixed_discounts');
    }
};
