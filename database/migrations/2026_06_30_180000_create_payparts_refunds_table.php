<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_payparts_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_order_id')->nullable()->constrained('bs_shop_orders')->nullOnDelete();
            $table->foreignId('payparts_transaction_id')->nullable()->constrained('bs_payparts_transactions')->nullOnDelete();
            $table->foreignId('payparts_bank_id')->nullable()->constrained('bs_payparts_banks')->nullOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('refund_pending')->index();
            $table->decimal('amount', 12, 2);
            $table->string('order_id')->index();
            $table->json('decline_request_payload')->nullable();
            $table->json('decline_response_payload')->nullable();
            $table->json('state_request_payload')->nullable();
            $table->json('state_response_payload')->nullable();
            $table->string('response_message')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['payparts_transaction_id', 'status'], 'payparts_refund_transaction_status_idx');
        });

        if (Schema::hasTable('permissions')) {
            $guard = 'admin';
            $permissionName = 'refund_payparts_payment';
            $exists = DB::table('permissions')
                ->where('name', $permissionName)
                ->where('guard_name', $guard)
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => $permissionName,
                    'guard_name' => $guard,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_payparts_refunds');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', 'refund_payparts_payment')
                ->where('guard_name', 'admin')
                ->delete();
        }
    }
};
