<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bs_sms_logs', function (Blueprint $table): void {
            $table->text('message_text')->nullable()->after('message_preview');
        });
    }

    public function down(): void
    {
        Schema::table('bs_sms_logs', function (Blueprint $table): void {
            $table->dropColumn('message_text');
        });
    }
};
