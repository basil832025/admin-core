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
        // 1) Добавляем временное JSON-поле
        Schema::table('bs_shop_fixed_discounts', function (Blueprint $table) {
            $table->json('name_json')->nullable()->after('name');
        });

        // 2) Переносим существующие строки в JSON
        DB::table('bs_shop_fixed_discounts')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $text = $row->name;

                    if ($text === null || $text === '') {
                        $json = null;
                    } else {
                        // Кладём старое название во все три языка
                        $json = json_encode([
                            'uk' => $text,
                            'ru' => $text,
                            'en' => $text,
                        ], JSON_UNESCAPED_UNICODE);
                    }

                    DB::table('bs_shop_fixed_discounts')
                        ->where('id', $row->id)
                        ->update(['name_json' => $json]);
                }
            });

        // 3) Удаляем старое строковое поле
        Schema::table('bs_shop_fixed_discounts', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        // 4) Переименовываем name_json -> name (сырое SQL, чтобы не нужен был doctrine/dbal)
        DB::statement('ALTER TABLE bs_shop_fixed_discounts CHANGE COLUMN name_json name JSON NULL');
    }

    public function down(): void
    {
        // Откат: вернём обычную строку (берём из JSON ru/uk/en)
        Schema::table('bs_shop_fixed_discounts', function (Blueprint $table) {
            $table->string('name_str', 128)->nullable()->after('name');
        });

        DB::table('bs_shop_fixed_discounts')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $value = $row->name; // JSON в колонке name

                    if (is_string($value)) {
                        $arr = json_decode($value, true) ?: [];
                    } else {
                        $arr = (array) $value;
                    }

                    $text = $arr['ru']
                        ?? $arr['uk']
                        ?? $arr['en']
                        ?? null;

                    DB::table('bs_shop_fixed_discounts')
                        ->where('id', $row->id)
                        ->update(['name_str' => $text]);
                }
            });

        Schema::table('bs_shop_fixed_discounts', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        DB::statement('ALTER TABLE bs_shop_fixed_discounts CHANGE COLUMN name_str name VARCHAR(128) NULL');
    }
};
