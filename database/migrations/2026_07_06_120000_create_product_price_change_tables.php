<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_price_change_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 32)->index();
            $table->string('operation', 32);
            $table->decimal('value', 14, 4);
            $table->string('old_price_mode', 32);
            $table->boolean('include_variants')->default(false);
            $table->json('category_ids')->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('affected_count')->default(0);
            $table->string('status', 24)->default('processing')->index();
            $table->text('error')->nullable();
            $table->foreignId('reverted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bs_product_price_change_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('bs_product_price_change_batches')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('bs_products')->nullOnDelete();
            $table->string('product_label')->nullable();
            $table->decimal('old_price', 14, 2);
            $table->decimal('new_price', 14, 2);
            $table->decimal('old_old_price', 14, 2)->nullable();
            $table->decimal('new_old_price', 14, 2)->nullable();
            $table->decimal('old_discount_percent', 7, 2)->nullable();
            $table->decimal('new_discount_percent', 7, 2)->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'product_id'], 'product_price_change_batch_product_unique');
        });

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['name' => 'bulk_update_product_prices', 'guard_name' => 'admin'],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_price_change_items');
        Schema::dropIfExists('bs_product_price_change_batches');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', 'bulk_update_product_prices')
                ->where('guard_name', 'admin')
                ->delete();
        }
    }
};
