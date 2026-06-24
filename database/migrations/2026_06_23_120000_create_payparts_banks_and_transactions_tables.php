<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_payparts_banks', function (Blueprint $table): void {
            $table->id();
            $table->string('bank_type')->unique();
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->json('terms')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('audience_mode')->default('all');
            $table->json('audience_client_ids')->nullable();
            $table->string('store_id')->nullable();
            $table->string('account_password')->nullable();
            $table->json('rules')->nullable();
            $table->timestamps();
        });

        Schema::create('bs_payparts_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_order_id')->nullable()->constrained('bs_shop_orders')->nullOnDelete();
            $table->foreignId('payparts_bank_id')->nullable()->constrained('bs_payparts_banks')->nullOnDelete();
            $table->string('status')->default('pending_payment');
            $table->string('merchant_type', 10)->nullable();
            $table->unsignedInteger('parts_count')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('order_id')->nullable()->index();
            $table->string('token')->nullable()->index();
            $table->string('signature')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('response_message')->nullable();
            $table->string('response_code')->nullable();
            $table->string('redirect_url')->nullable();
            $table->string('response_url')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_locale', 10)->nullable();
            $table->timestamps();
        });

        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_shop_orders', 'payparts_bank_id')) {
                $table->foreignId('payparts_bank_id')->nullable()->after('payment')->constrained('bs_payparts_banks')->nullOnDelete();
            }
            if (! Schema::hasColumn('bs_shop_orders', 'payparts_status')) {
                $table->string('payparts_status')->nullable()->after('payparts_bank_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_shop_orders', 'payparts_status')) {
                $table->dropColumn('payparts_status');
            }
            if (Schema::hasColumn('bs_shop_orders', 'payparts_bank_id')) {
                $table->dropConstrainedForeignId('payparts_bank_id');
            }
        });

        Schema::dropIfExists('bs_payparts_transactions');
        Schema::dropIfExists('bs_payparts_banks');
    }
};
