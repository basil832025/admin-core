<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bs_cashalot_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_cashalot_logs', 'consumer_service_type')) {
                $table->unsignedTinyInteger('consumer_service_type')->nullable()->after('receipt_url');
            }

            if (! Schema::hasColumn('bs_cashalot_logs', 'consumer_phone')) {
                $table->string('consumer_phone', 32)->nullable()->after('consumer_service_type');
            }

            if (! Schema::hasColumn('bs_cashalot_logs', 'consumer_status')) {
                $table->string('consumer_status', 32)->nullable()->index()->after('consumer_phone');
            }

            if (! Schema::hasColumn('bs_cashalot_logs', 'consumer_error_code')) {
                $table->string('consumer_error_code', 64)->nullable()->after('consumer_status');
            }

            if (! Schema::hasColumn('bs_cashalot_logs', 'consumer_error_message')) {
                $table->text('consumer_error_message')->nullable()->after('consumer_error_code');
            }

            if (! Schema::hasColumn('bs_cashalot_logs', 'consumer_response_payload')) {
                $table->json('consumer_response_payload')->nullable()->after('response_payload');
            }

            if (! Schema::hasColumn('bs_cashalot_logs', 'sent_to_consumer_at')) {
                $table->timestamp('sent_to_consumer_at')->nullable()->after('fiscalized_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_cashalot_logs', function (Blueprint $table): void {
            foreach ([
                'consumer_service_type',
                'consumer_phone',
                'consumer_status',
                'consumer_error_code',
                'consumer_error_message',
                'consumer_response_payload',
                'sent_to_consumer_at',
            ] as $column) {
                if (Schema::hasColumn('bs_cashalot_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
