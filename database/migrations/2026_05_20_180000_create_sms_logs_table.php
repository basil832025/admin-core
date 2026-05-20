<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bs_sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('bs_clients')->nullOnDelete();
            $table->string('channel', 32)->default('esputnik');
            $table->string('message_type', 64)->nullable()->index();
            $table->string('phone', 32);
            $table->string('normalized_phone', 32)->index();
            $table->string('sender', 64)->nullable();
            $table->string('provider_request_id', 128)->nullable()->index();
            $table->string('provider_status', 64)->nullable()->index();
            $table->string('delivery_status', 64)->nullable()->index();
            $table->unsignedSmallInteger('http_status')->nullable()->index();
            $table->boolean('success')->default(false)->index();
            $table->string('error_message', 1000)->nullable();
            $table->string('message_preview', 255)->nullable();
            $table->json('context')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('delivery_checked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['normalized_phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_sms_logs');
    }
};
