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
        Schema::create('bs_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('bs_clients')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('bs_products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bs_favorites', function (Blueprint $table) {
            //
        });
    }
};
