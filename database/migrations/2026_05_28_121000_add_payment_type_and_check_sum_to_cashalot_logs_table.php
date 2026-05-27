<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_cashalot_logs', function (Blueprint $table): void {
            $table->decimal('check_sum', 12, 2)->nullable()->after('receipt_url');
            $table->string('payment_type', 64)->nullable()->after('check_sum')->index();
        });
    }

    public function down(): void
    {
        Schema::table('bs_cashalot_logs', function (Blueprint $table): void {
            $table->dropColumn(['check_sum', 'payment_type']);
        });
    }
};
