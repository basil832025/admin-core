<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->json('name');
            $table->json('short_name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('bs_product_units')->insert([
            [
                'code' => 'sht',
                'name' => json_encode(['uk' => 'Штука', 'ru' => 'Штука', 'en' => 'Piece'], JSON_UNESCAPED_UNICODE),
                'short_name' => json_encode(['uk' => 'шт', 'ru' => 'шт', 'en' => 'pc'], JSON_UNESCAPED_UNICODE),
                'sort_order' => 10,
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'kg',
                'name' => json_encode(['uk' => 'Кілограм', 'ru' => 'Килограмм', 'en' => 'Kilogram'], JSON_UNESCAPED_UNICODE),
                'short_name' => json_encode(['uk' => 'кг', 'ru' => 'кг', 'en' => 'kg'], JSON_UNESCAPED_UNICODE),
                'sort_order' => 20,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'gr',
                'name' => json_encode(['uk' => 'Грам', 'ru' => 'Грамм', 'en' => 'Gram'], JSON_UNESCAPED_UNICODE),
                'short_name' => json_encode(['uk' => 'гр', 'ru' => 'гр', 'en' => 'g'], JSON_UNESCAPED_UNICODE),
                'sort_order' => 30,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ml',
                'name' => json_encode(['uk' => 'Мілілітр', 'ru' => 'Миллилитр', 'en' => 'Milliliter'], JSON_UNESCAPED_UNICODE),
                'short_name' => json_encode(['uk' => 'мл', 'ru' => 'мл', 'en' => 'ml'], JSON_UNESCAPED_UNICODE),
                'sort_order' => 40,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'l',
                'name' => json_encode(['uk' => 'Літр', 'ru' => 'Литр', 'en' => 'Liter'], JSON_UNESCAPED_UNICODE),
                'short_name' => json_encode(['uk' => 'л', 'ru' => 'л', 'en' => 'l'], JSON_UNESCAPED_UNICODE),
                'sort_order' => 50,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'portion',
                'name' => json_encode(['uk' => 'Порція', 'ru' => 'Порция', 'en' => 'Portion'], JSON_UNESCAPED_UNICODE),
                'short_name' => json_encode(['uk' => 'порц', 'ru' => 'порц', 'en' => 'portion'], JSON_UNESCAPED_UNICODE),
                'sort_order' => 60,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'bottle',
                'name' => json_encode(['uk' => 'Пляшка', 'ru' => 'Бутылка', 'en' => 'Bottle'], JSON_UNESCAPED_UNICODE),
                'short_name' => json_encode(['uk' => 'пляш', 'ru' => 'бут', 'en' => 'bottle'], JSON_UNESCAPED_UNICODE),
                'sort_order' => 70,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('bs_products', function (Blueprint $table): void {
            $table->foreignId('unit_id')
                ->nullable()
                ->after('price')
                ->constrained('bs_product_units')
                ->nullOnDelete();
        });

        $defaultUnitId = DB::table('bs_product_units')->where('code', 'sht')->value('id');

        if ($defaultUnitId) {
            DB::table('bs_products')->whereNull('unit_id')->update(['unit_id' => $defaultUnitId]);
        }
    }

    public function down(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::dropIfExists('bs_product_units');
    }
};
