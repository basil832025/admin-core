<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_prros', function (Blueprint $table) {
            $table->date('certificate_expires_at')->nullable()->after('registered_at');
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_prros', function (Blueprint $table) {
            $table->dropColumn('certificate_expires_at');
        });
    }
};
