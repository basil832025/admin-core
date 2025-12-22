<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_loyalty_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('valid_from');
            $table->tinyInteger('earn_percent')->unsigned()->default('10');
            $table->text('earn_expire_days')->default('30');
            $table->unsignedInteger('welcome_bonus_amount')->default('50');
            $table->text('welcome_bonus_expire_days')->default('7');
            $table->decimal('min_order_sum_for_earn', 10, 2)->nullable();
            $table->boolean('is_enabled')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['valid_from'], 'bs_loyalty_rules_valid_from_unique');
            $table->index(['valid_from'], 'bs_loyalty_rules_valid_from_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_loyalty_rules');
    }
};
