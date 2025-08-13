<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('client_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('city')->nullable();
            $table->string('street');
            $table->string('house');
            $table->string('apartment')->nullable();
            $table->string('intercom')->nullable();
            $table->string('floor')->nullable();
            $table->string('entrance')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_private_house')->default(false);
            $table->enum('type', ['Дом', 'Работа', 'Друзья'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_addresses');
    }
};
