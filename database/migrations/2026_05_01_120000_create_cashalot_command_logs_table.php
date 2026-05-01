<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_cashalot_command_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->string('command', 64)->index();
            $table->string('prro_num_fiscal', 64)->nullable()->index();

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            $table->string('status', 32)->default('unknown')->index();
            $table->string('error_code', 64)->nullable()->index();
            $table->text('error_message')->nullable();

            $table->string('result_num_fiscal', 128)->nullable()->index();
            $table->string('shift_id', 128)->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_cashalot_command_logs');
    }
};
