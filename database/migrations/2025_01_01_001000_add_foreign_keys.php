<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add foreign keys after all tables are created

        Schema::table('bs_blog_comments', function (Blueprint $table) {
            $table->foreign('blog_id', 'blog_comments_blog_id_foreign')->references('id')->on('bs_blogs')->cascadeOnDelete();
            $table->foreign('parent_id', 'blog_comments_parent_id_foreign')->references('id')->on('bs_blog_comments')->cascadeOnDelete();
        });

        Schema::table('bs_blogs', function (Blueprint $table) {
            $table->foreign('blog_category_id', 'blogs_blog_category_id_foreign')->references('id')->on('bs_blog_categories')->cascadeOnDelete();
        });

        Schema::table('bs_category_characteristic', function (Blueprint $table) {
            $table->foreign('category_id', 'category_characteristic_category_id_foreign')->references('id')->on('bs_product_categories')->cascadeOnDelete();
            $table->foreign('characteristic_id', 'category_characteristic_characteristic_id_foreign')->references('id')->on('bs_characteristics')->cascadeOnDelete();
        });

        Schema::table('bs_category_variation', function (Blueprint $table) {
            $table->foreign('category_id', 'category_variation_category_id_foreign')->references('id')->on('bs_product_categories')->cascadeOnDelete();
            $table->foreign('variation_id', 'category_variation_variation_id_foreign')->references('id')->on('bs_variations')->cascadeOnDelete();
        });

        Schema::table('bs_characteristic_product', function (Blueprint $table) {
            $table->foreign('characteristic_id', 'characteristic_product_characteristic_id_foreign')->references('id')->on('bs_characteristics')->cascadeOnDelete();
            $table->foreign('product_id', 'characteristic_product_product_id_foreign')->references('id')->on('bs_products')->cascadeOnDelete();
        });

        Schema::table('bs_characteristic_values', function (Blueprint $table) {
            $table->foreign('characteristic_id', 'characteristic_values_characteristic_id_foreign')->references('id')->on('bs_characteristics')->cascadeOnDelete();
        });

        Schema::table('bs_characteristics', function (Blueprint $table) {
            $table->foreign('svg_image_id', 'bs_characteristics_svg_image_id_foreign')->references('id')->on('bs_svg_images')->nullOnDelete();
            $table->foreign('category_id', 'characteristics_category_id_foreign')->references('id')->on('bs_characteristic_categories')->cascadeOnDelete();
        });

        Schema::table('bs_client_addresses', function (Blueprint $table) {
            $table->foreign('client_id', 'client_addresses_client_id_foreign')->references('id')->on('bs_clients')->cascadeOnDelete();
        });

        Schema::table('bs_favorites', function (Blueprint $table) {
            $table->foreign('client_id', 'bs_favorites_client_id_foreign')->references('id')->on('bs_clients')->cascadeOnDelete();
            $table->foreign('product_id', 'bs_favorites_product_id_foreign')->references('id')->on('bs_products')->cascadeOnDelete();
        });

        Schema::table('bs_kitchen_ticket_events', function (Blueprint $table) {
            $table->foreign('kitchen_ticket_id', 'kitchen_ticket_events_kitchen_ticket_id_foreign')->references('id')->on('bs_kitchen_tickets')->cascadeOnDelete();
        });

        Schema::table('bs_kitchen_ticket_items', function (Blueprint $table) {
            $table->foreign('kitchen_ticket_id', 'kitchen_ticket_items_kitchen_ticket_id_foreign')->references('id')->on('bs_kitchen_tickets')->cascadeOnDelete();
        });

        Schema::table('bs_kitchen_tickets', function (Blueprint $table) {
            $table->foreign('order_id', 'kitchen_tickets_order_id_foreign')->references('id')->on('bs_shop_orders')->cascadeOnDelete();
        });

        Schema::table('bs_locations', function (Blueprint $table) {
            $table->foreign('svg_image_id', 'bs_locations_svg_image_id_foreign')->references('id')->on('bs_svg_images')->nullOnDelete();
        });

        Schema::table('bs_loyalty_accounts', function (Blueprint $table) {
            $table->foreign('client_id', 'bs_loyalty_accounts_client_id_foreign')->references('id')->on('bs_clients')->nullOnDelete();
        });

        Schema::table('bs_loyalty_transactions', function (Blueprint $table) {
            $table->foreign('account_id', 'bs_loyalty_transactions_account_id_foreign')->references('id')->on('bs_loyalty_accounts')->cascadeOnDelete();
            $table->foreign('order_id', 'bs_loyalty_transactions_order_id_foreign')->references('id')->on('bs_shop_orders')->nullOnDelete();
        });

        Schema::table('bs_product_calculation_items', function (Blueprint $table) {
            $table->foreign('calculation_id', 'product_calculation_items_calculation_id_foreign')->references('id')->on('bs_product_calculations')->cascadeOnDelete();
            $table->foreign('component_product_id', 'product_calculation_items_component_product_id_foreign')->references('id')->on('bs_products');
        });

        Schema::table('bs_product_calculations', function (Blueprint $table) {
            $table->foreign('product_id', 'product_calculations_product_id_foreign')->references('id')->on('bs_products')->cascadeOnDelete();
        });

        Schema::table('bs_product_characteristic_value', function (Blueprint $table) {
            $table->foreign('characteristic_id', 'product_characteristic_value_characteristic_id_foreign')->references('id')->on('bs_characteristics')->cascadeOnDelete();
            $table->foreign('characteristic_value_id', 'product_characteristic_value_characteristic_value_id_foreign')->references('id')->on('bs_characteristic_values')->nullOnDelete();
            $table->foreign('product_id', 'product_characteristic_value_product_id_foreign')->references('id')->on('bs_products')->cascadeOnDelete();
        });

        Schema::table('bs_product_images', function (Blueprint $table) {
            $table->foreign('product_id', 'product_images_product_id_foreign')->references('id')->on('bs_products')->cascadeOnDelete();
        });

        Schema::table('bs_product_item_modifiers', function (Blueprint $table) {
            $table->foreign('order_item_id', 'product_item_modifiers_order_item_id_foreign')->references('id')->on('bs_shop_order_items')->cascadeOnDelete();
        });

        Schema::table('bs_product_product_category', function (Blueprint $table) {
            $table->foreign('product_category_id', 'product_product_category_product_category_id_foreign')->references('id')->on('bs_product_categories')->cascadeOnDelete();
            $table->foreign('product_id', 'product_product_category_product_id_foreign')->references('id')->on('bs_products')->cascadeOnDelete();
        });

        Schema::table('bs_product_reviews', function (Blueprint $table) {
            $table->foreign('product_id', 'bs_product_reviews_product_id_foreign')->references('id')->on('bs_products')->cascadeOnDelete();
        });

        Schema::table('bs_product_variation', function (Blueprint $table) {
            $table->foreign('product_id', 'product_variation_product_id_foreign')->references('id')->on('bs_products')->cascadeOnDelete();
            $table->foreign('variation_id', 'product_variation_variation_id_foreign')->references('id')->on('bs_variations')->cascadeOnDelete();
        });

        Schema::table('bs_products', function (Blueprint $table) {
            $table->foreign('category_id', 'products_category_id_foreign')->references('id')->on('bs_product_categories')->onDelete('set null on update cascade')->cascadeOnUpdate();
            $table->foreign('parent_id', 'products_parent_id_foreign')->references('id')->on('bs_products')->onDelete('set null on update cascade')->cascadeOnUpdate();
        });

        Schema::table('bs_shop_order_adjustments', function (Blueprint $table) {
            $table->foreign('shop_order_id', 'shop_order_adjustments_shop_order_id_foreign')->references('id')->on('bs_shop_orders')->cascadeOnDelete();
            $table->foreign('shop_order_item_id', 'shop_order_adjustments_shop_order_item_id_foreign')->references('id')->on('bs_shop_order_items')->nullOnDelete();
        });

        Schema::table('bs_shop_order_items', function (Blueprint $table) {
            $table->foreign('product_id', 'shop_order_items_product_id_foreign')->references('id')->on('bs_products')->cascadeOnDelete();
            $table->foreign('shop_order_id', 'shop_order_items_shop_order_id_foreign')->references('id')->on('bs_shop_orders')->cascadeOnDelete();
        });

        Schema::table('bs_shop_orders', function (Blueprint $table) {
            $table->foreign('client_address_id', 'shop_orders_client_address_id_foreign')->references('id')->on('bs_client_addresses')->nullOnDelete();
            $table->foreign('clients_id', 'shop_orders_clients_id_foreign')->references('id')->on('bs_clients')->nullOnDelete();
        });

        Schema::table('bs_site_texts', function (Blueprint $table) {
            $table->foreign('group_id', 'bs_site_texts_group_id_foreign')->references('id')->on('bs_site_text_groups')->nullOnDelete();
        });

        Schema::table('bs_variation_characteristic_value', function (Blueprint $table) {
            $table->foreign('characteristic_id', 'variation_characteristic_value_characteristic_id_foreign')->references('id')->on('bs_characteristics')->cascadeOnDelete();
            $table->foreign('characteristic_value_id', 'variation_characteristic_value_characteristic_value_id_foreign')->references('id')->on('bs_characteristic_values')->cascadeOnDelete();
            $table->foreign('variation_id', 'variation_characteristic_value_variation_id_foreign')->references('id')->on('bs_variations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Drop foreign keys (optional)
        Schema::table('bs_blog_comments', function (Blueprint $table) {
            $table->dropForeign('blog_comments_blog_id_foreign');
            $table->dropForeign('blog_comments_parent_id_foreign');
        });

        Schema::table('bs_blogs', function (Blueprint $table) {
            $table->dropForeign('blogs_blog_category_id_foreign');
        });

        Schema::table('bs_category_characteristic', function (Blueprint $table) {
            $table->dropForeign('category_characteristic_category_id_foreign');
            $table->dropForeign('category_characteristic_characteristic_id_foreign');
        });

        Schema::table('bs_category_variation', function (Blueprint $table) {
            $table->dropForeign('category_variation_category_id_foreign');
            $table->dropForeign('category_variation_variation_id_foreign');
        });

        Schema::table('bs_characteristic_product', function (Blueprint $table) {
            $table->dropForeign('characteristic_product_characteristic_id_foreign');
            $table->dropForeign('characteristic_product_product_id_foreign');
        });

        Schema::table('bs_characteristic_values', function (Blueprint $table) {
            $table->dropForeign('characteristic_values_characteristic_id_foreign');
        });

        Schema::table('bs_characteristics', function (Blueprint $table) {
            $table->dropForeign('bs_characteristics_svg_image_id_foreign');
            $table->dropForeign('characteristics_category_id_foreign');
        });

        Schema::table('bs_client_addresses', function (Blueprint $table) {
            $table->dropForeign('client_addresses_client_id_foreign');
        });

        Schema::table('bs_favorites', function (Blueprint $table) {
            $table->dropForeign('bs_favorites_client_id_foreign');
            $table->dropForeign('bs_favorites_product_id_foreign');
        });

        Schema::table('bs_kitchen_ticket_events', function (Blueprint $table) {
            $table->dropForeign('kitchen_ticket_events_kitchen_ticket_id_foreign');
        });

        Schema::table('bs_kitchen_ticket_items', function (Blueprint $table) {
            $table->dropForeign('kitchen_ticket_items_kitchen_ticket_id_foreign');
        });

        Schema::table('bs_kitchen_tickets', function (Blueprint $table) {
            $table->dropForeign('kitchen_tickets_order_id_foreign');
        });

        Schema::table('bs_locations', function (Blueprint $table) {
            $table->dropForeign('bs_locations_svg_image_id_foreign');
        });

        Schema::table('bs_loyalty_accounts', function (Blueprint $table) {
            $table->dropForeign('bs_loyalty_accounts_client_id_foreign');
        });

        Schema::table('bs_loyalty_transactions', function (Blueprint $table) {
            $table->dropForeign('bs_loyalty_transactions_account_id_foreign');
            $table->dropForeign('bs_loyalty_transactions_order_id_foreign');
        });

        Schema::table('bs_product_calculation_items', function (Blueprint $table) {
            $table->dropForeign('product_calculation_items_calculation_id_foreign');
            $table->dropForeign('product_calculation_items_component_product_id_foreign');
        });

        Schema::table('bs_product_calculations', function (Blueprint $table) {
            $table->dropForeign('product_calculations_product_id_foreign');
        });

        Schema::table('bs_product_characteristic_value', function (Blueprint $table) {
            $table->dropForeign('product_characteristic_value_characteristic_id_foreign');
            $table->dropForeign('product_characteristic_value_characteristic_value_id_foreign');
            $table->dropForeign('product_characteristic_value_product_id_foreign');
        });

        Schema::table('bs_product_images', function (Blueprint $table) {
            $table->dropForeign('product_images_product_id_foreign');
        });

        Schema::table('bs_product_item_modifiers', function (Blueprint $table) {
            $table->dropForeign('product_item_modifiers_order_item_id_foreign');
        });

        Schema::table('bs_product_product_category', function (Blueprint $table) {
            $table->dropForeign('product_product_category_product_category_id_foreign');
            $table->dropForeign('product_product_category_product_id_foreign');
        });

        Schema::table('bs_product_reviews', function (Blueprint $table) {
            $table->dropForeign('bs_product_reviews_product_id_foreign');
        });

        Schema::table('bs_product_variation', function (Blueprint $table) {
            $table->dropForeign('product_variation_product_id_foreign');
            $table->dropForeign('product_variation_variation_id_foreign');
        });

        Schema::table('bs_products', function (Blueprint $table) {
            $table->dropForeign('products_category_id_foreign');
            $table->dropForeign('products_parent_id_foreign');
        });

        Schema::table('bs_shop_order_adjustments', function (Blueprint $table) {
            $table->dropForeign('shop_order_adjustments_shop_order_id_foreign');
            $table->dropForeign('shop_order_adjustments_shop_order_item_id_foreign');
        });

        Schema::table('bs_shop_order_items', function (Blueprint $table) {
            $table->dropForeign('shop_order_items_product_id_foreign');
            $table->dropForeign('shop_order_items_shop_order_id_foreign');
        });

        Schema::table('bs_shop_orders', function (Blueprint $table) {
            $table->dropForeign('shop_orders_client_address_id_foreign');
            $table->dropForeign('shop_orders_clients_id_foreign');
        });

        Schema::table('bs_site_texts', function (Blueprint $table) {
            $table->dropForeign('bs_site_texts_group_id_foreign');
        });

        Schema::table('bs_variation_characteristic_value', function (Blueprint $table) {
            $table->dropForeign('variation_characteristic_value_characteristic_id_foreign');
            $table->dropForeign('variation_characteristic_value_characteristic_value_id_foreign');
            $table->dropForeign('variation_characteristic_value_variation_id_foreign');
        });
    }
};
