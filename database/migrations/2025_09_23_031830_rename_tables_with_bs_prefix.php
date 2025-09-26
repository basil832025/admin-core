<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Список таблиц для переименования: old => new */
    private array $map = [
        // контент / блог
        'blogs'                                  => 'bs_blogs',
        'blog_categories'                        => 'bs_blog_categories',
        'blog_comments'                          => 'bs_blog_comments',

        // справочники и каталоги
        'currencies'                             => 'bs_currencies',
        'languages'                              => 'bs_languages',
        'pages'                                  => 'bs_pages',
        'positions'                              => 'bs_positions',
        'settings'                               => 'bs_settings',

        // клиенты и адреса
        'clients'                                => 'bs_clients',
        'client_addresses'                       => 'bs_client_addresses',
      //  'users'                                  => 'bs_users', // если это ваши пользователи админки/сайта

        // характеристики и вариации
        'characteristics'                        => 'bs_characteristics',
        'characteristic_values'                  => 'bs_characteristic_values',
        'characteristic_categories'              => 'bs_characteristic_categories',
        'characteristic_product'                 => 'bs_characteristic_product',
        'variations'                             => 'bs_variations',
        'variation_characteristic_value'         => 'bs_variation_characteristic_value',
        'category_characteristic'                => 'bs_category_characteristic',
        'category_variation'                     => 'bs_category_variation',

        // товары и связки
        'products'                               => 'bs_products',
        'product_images'                         => 'bs_product_images',
        'product_categories'                     => 'bs_product_categories',
        'product_product_category'               => 'bs_product_product_category',
        'product_item_modifiers'                 => 'bs_product_item_modifiers',
        'product_characteristic_value'           => 'bs_product_characteristic_value',
        'product_variation'                      => 'bs_product_variation',

        // калькуляции/себестоимость
        'product_calculations'                   => 'bs_product_calculations',
        'product_calculation_items'              => 'bs_product_calculation_items',

        // кухня / производство
        'kitchen_tickets'                        => 'bs_kitchen_tickets',
        'kitchen_ticket_items'                   => 'bs_kitchen_ticket_items',
        'kitchen_ticket_events'                  => 'bs_kitchen_ticket_events',

        // заказы и скидки (shop_*)
        'shop_orders'                            => 'bs_shop_orders',
        'shop_order_items'                       => 'bs_shop_order_items',
        'shop_order_adjustments'                 => 'bs_shop_order_adjustments',

        'shop_fixed_discounts'                   => 'bs_shop_fixed_discounts',

        'shop_promo_codes'                       => 'bs_shop_promo_codes',
        'shop_promo_code_categories'             => 'bs_shop_promo_code_categories',
        'shop_promo_code_products'               => 'bs_shop_promo_code_products',
        'shop_promo_code_characteristics'        => 'bs_shop_promo_code_characteristics',
        'shop_promo_code_characteristic_values'  => 'bs_shop_promo_code_characteristic_values',
        'shop_promo_code_usages'                 => 'bs_shop_promo_code_usages',

        'shop_time_discounts'                    => 'bs_shop_time_discounts',
        'shop_time_discount_categories'          => 'bs_shop_time_discount_categories',
        'shop_time_discount_products'            => 'bs_shop_time_discount_products',
        'shop_time_discount_characteristics'     => 'bs_shop_time_discount_characteristics',
        'shop_time_discount_characteristic_values'=> 'bs_shop_time_discount_characteristic_values',
    ];

    public function up(): void
    {
        // На всякий случай отключаем проверки внешних ключей на время переименования
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->map as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // обратное переименование: new => old
        foreach ($this->map as $old => $new) {
            if (Schema::hasTable($new) && ! Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
