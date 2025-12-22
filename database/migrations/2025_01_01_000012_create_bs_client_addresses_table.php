<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_client_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id');
            $table->string('city', 255)->nullable();
            $table->string('street', 255);
            $table->string('house', 255);
            $table->string('apartment', 255)->nullable();
            $table->string('intercom', 255)->nullable();
            $table->string('floor', 255)->nullable();
            $table->string('entrance', 255)->nullable();
            $table->text('note');
            $table->boolean('is_private_house')->default('0');
            $table->text('type')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['client_id'], 'client_addresses_client_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_client_addresses');
    }
};
