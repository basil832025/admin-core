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
        Schema::table('product_characteristic_value', function (Blueprint $table) {
        //    $table->unsignedBigInteger('characteristic_value_id')->nullable()->after('characteristic_id');
            $table->text('value_text')->nullable()->after('characteristic_value_id');
            $table->decimal('value_number', 10, 2)->nullable()->after('value_text');
            $table->dateTime('value_datetime')->nullable()->after('value_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_characteristic_value', function (Blueprint $table) {
            //
        });
    }
};
