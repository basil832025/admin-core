<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            // базовые поля уже есть: id, shop_order_id, product_id, qty, unit_price

            $table->decimal('unit_price_effective', 12, 2)->after('unit_price')->nullable(); // итого за 1 шт. после скидок
            $table->decimal('subtotal', 12, 2)->after('unit_price_effective')->default(0);    // unit_price * qty (снимок)
            $table->decimal('discount_total', 12, 2)->after('subtotal')->default(0);          // сумма скидок по строке (<=0)
            $table->decimal('tax_rate', 5, 2)->after('discount_total')->default(0);
            $table->decimal('tax_total', 12, 2)->after('tax_rate')->default(0);
            $table->decimal('total', 12, 2)->after('tax_total')->default(0);                  // финал строки
          //  $table->string('currency', 3)->after('total')->default('UAH');

           // $table->string('sku', 64)->nullable()->after('product_id');
           // $table->json('product_snapshot')->nullable()->after('sku');   // название, вариации, характеристики, единицы
            //$table->json('promotion_data')->nullable()->after('product_snapshot'); // пояснение как применились акции

            // индексы на частые выборки
           // $table->index(['shop_order_id']);
         //   $table->index(['product_id']);
        });

        // агрегирующие поля на заказе (если ещё не добавлены)
        Schema::table('shop_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_orders', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('status');
                $table->decimal('discount_total', 12, 2)->default(0)->after('subtotal');
                $table->decimal('shipping_total', 12, 2)->default(0)->after('discount_total');
                $table->decimal('tax_total', 12, 2)->default(0)->after('shipping_total');
                $table->decimal('grand_total', 12, 2)->default(0)->after('tax_total');
                $table->string('currency', 3)->default('UAH')->after('grand_total');
            }
        });

        // универсальные корректировки (скидки/надбавки/доставка/налоги)
        Schema::create('shop_order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignId('shop_order_item_id')->nullable()
                ->constrained('shop_order_items')->nullOnDelete(); // null => корректировка на весь заказ

            $table->string('type', 32);  // promotion|coupon|manual|shipping|tax
            $table->string('label');     // «2-й товар −50%», «Промокод ABC»
            $table->decimal('amount', 12, 2); // скидка < 0, надбавка > 0
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->unsignedBigInteger('promo_code_id')->nullable();
            $table->json('meta')->nullable(); // любые детали правила
            $table->timestamps();

            $table->index(['shop_order_id']);
            $table->index(['shop_order_item_id']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_adjustments');

        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'unit_price_effective','subtotal','discount_total','tax_rate',
                'tax_total','total','currency','sku','product_snapshot','promotion_data'
            ]);
        });

        Schema::table('shop_orders', function (Blueprint $table) {
            foreach (['subtotal','discount_total','shipping_total','tax_total','grand_total','currency'] as $col) {
                if (Schema::hasColumn('shop_orders', $col)) $table->dropColumn($col);
            }
        });
    }
};
