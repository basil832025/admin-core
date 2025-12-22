<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('phone', 255);
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('email', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->date('birthday')->nullable();
            $table->text('gender')->nullable();
            $table->string('password', 255)->nullable();
            $table->string('provider_name', 255)->nullable();
            $table->string('provider_id', 255)->nullable();
            $table->string('photo', 255)->nullable();
            $table->text('note');
            $table->boolean('is_active')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->index(['provider_id'], 'bs_clients_provider_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_clients');
    }
};
