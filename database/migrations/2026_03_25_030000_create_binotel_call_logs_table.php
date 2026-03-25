<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_cc_binotel_call_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 32)->index();
            $table->string('status', 32)->index();
            $table->string('request_type', 64)->nullable();
            $table->string('call_type', 16)->nullable();
            $table->string('caller_phone', 32)->nullable()->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->string('client_name', 191)->nullable();
            $table->string('pbx_number', 32)->nullable()->index();
            $table->string('pbx_name', 191)->nullable();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('source_name', 191)->nullable();
            $table->string('point_name', 191)->nullable();
            $table->string('internal_number', 64)->nullable();
            $table->string('company_id', 64)->nullable();
            $table->string('general_call_id', 128)->nullable()->index();
            $table->string('crm_url', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('request_payload')->nullable();
            $table->timestamps();

            $table->foreign('source_id')
                ->references('id')
                ->on('bs_cc_sources')
                ->nullOnDelete();

            $table->foreign('client_id')
                ->references('id')
                ->on('bs_clients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_cc_binotel_call_logs');
    }
};
