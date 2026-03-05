<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bs_cashalot_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_order_id')->index();
            $table->unsignedBigInteger('liqpay_log_id')->nullable()->index();
            $table->string('status', 32)->default('pending')->index();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->string('num_fiscal', 64)->nullable()->index();
            $table->string('receipt_url', 500)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('fiscalized_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_cashalot_logs');
    }
};
