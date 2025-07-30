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
        Schema::table('category_characteristic', function (Blueprint $table) {
         //   $table->boolean('is_required')->default(false);
          //  $table->boolean('affects_price')->default(false);
         //   $table->boolean('is_expanded')->default(false); // или 'expanded', если у тебя такое название
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('category_characteristic', function (Blueprint $table) {
            //
        });
    }
};
