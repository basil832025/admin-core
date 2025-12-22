<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_loyalty_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('balance', 10, 2)->default('0.00');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['client_id'], 'bs_loyalty_accounts_client_id_unique');
            $table->unique(['phone'], 'bs_loyalty_accounts_phone_unique');
            $table->index(['balance'], 'bs_loyalty_accounts_balance_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_loyalty_accounts');
    }
};
