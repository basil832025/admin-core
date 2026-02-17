<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_kitchen_tickets', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority')
                ->default(100)
                ->index()
                ->after('delivery_type');
        });
    }

    public function down(): void
    {
        Schema::table('bs_kitchen_tickets', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
