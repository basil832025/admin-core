<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_cc_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('base_url');
            $table->string('api_key');
            $table->boolean('is_active')->default(true);
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('last_catalog_synced_at')->nullable();
            $table->timestamp('last_orders_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bs_cc_source_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('bs_cc_sources')->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->string('external_parent_id', 64)->nullable();
            $table->string('alias')->nullable();
            $table->json('title')->nullable();
            $table->foreignId('local_category_id')->nullable()->constrained('bs_product_categories')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id'], 'bs_cc_source_categories_source_external_unique');
        });

        Schema::create('bs_cc_source_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('bs_cc_sources')->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->string('external_parent_id', 64)->nullable();
            $table->string('external_category_id', 64)->nullable();
            $table->string('alias')->nullable();
            $table->string('title')->nullable();
            $table->string('size_label')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->foreignId('local_product_id')->nullable()->constrained('bs_products')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id'], 'bs_cc_source_products_source_external_unique');
            $table->index(['source_id', 'local_product_id'], 'bs_cc_source_products_source_local_idx');
        });

        Schema::create('bs_cc_source_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('bs_cc_sources')->cascadeOnDelete();
            $table->string('external_id', 64)->nullable();
            $table->string('external_phone', 32)->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('local_client_id')->nullable()->constrained('bs_clients')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_phone'], 'bs_cc_source_clients_source_phone_unique');
            $table->index(['source_id', 'external_id'], 'bs_cc_source_clients_source_external_idx');
        });

        Schema::create('bs_cc_source_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('bs_cc_sources')->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->foreignId('local_order_id')->nullable()->constrained('bs_shop_orders')->nullOnDelete();
            $table->string('sync_status', 32)->default('imported');
            $table->text('last_error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id'], 'bs_cc_source_orders_source_external_unique');
        });

        Schema::create('bs_cc_source_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_order_id')->constrained('bs_cc_source_orders')->cascadeOnDelete();
            $table->string('external_item_id', 64)->nullable();
            $table->string('external_product_id', 64)->nullable();
            $table->string('title')->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->foreignId('local_product_id')->nullable()->constrained('bs_products')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index('external_product_id', 'bs_cc_source_order_items_external_product_idx');
        });

        Schema::create('bs_cc_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('bs_cc_sources')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('status', 32)->default('success');
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_cc_sync_runs');
        Schema::dropIfExists('bs_cc_source_order_items');
        Schema::dropIfExists('bs_cc_source_orders');
        Schema::dropIfExists('bs_cc_source_clients');
        Schema::dropIfExists('bs_cc_source_products');
        Schema::dropIfExists('bs_cc_source_categories');
        Schema::dropIfExists('bs_cc_sources');
    }
};
