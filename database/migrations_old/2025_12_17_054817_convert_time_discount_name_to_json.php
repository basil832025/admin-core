<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Временное поле JSON
        Schema::table('bs_shop_time_discounts', function (Blueprint $table) {
            $table->json('name_json')->nullable()->after('name');
        });

        // 2) Переносим текстовое name в JSON
        DB::table('bs_shop_time_discounts')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $text = $row->name;

                    if ($text === null || $text === '') {
                        $json = null;
                    } else {
                        $json = json_encode([
                            'uk' => $text,
                            'ru' => $text,
                            'en' => $text,
                        ], JSON_UNESCAPED_UNICODE);
                    }

                    DB::table('bs_shop_time_discounts')
                        ->where('id', $row->id)
                        ->update(['name_json' => $json]);
                }
            });

        // 3) Удаляем старую колонку name
        Schema::table('bs_shop_time_discounts', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        // 4) Переименовываем name_json -> name (JSON)
        DB::statement('ALTER TABLE bs_shop_time_discounts CHANGE COLUMN name_json name JSON NULL');
    }

    public function down(): void
    {
        // Откат обратно в VARCHAR(160)
        Schema::table('bs_shop_time_discounts', function (Blueprint $table) {
            $table->string('name_str', 160)->nullable()->after('name');
        });

        DB::table('bs_shop_time_discounts')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $value = $row->name;   // JSON

                    if (is_string($value)) {
                        $arr = json_decode($value, true) ?: [];
                    } else {
                        $arr = (array) $value;
                    }

                    $text = $arr['ru']
                        ?? $arr['uk']
                        ?? $arr['en']
                        ?? null;

                    DB::table('bs_shop_time_discounts')
                        ->where('id', $row->id)
                        ->update(['name_str' => $text]);
                }
            });

        Schema::table('bs_shop_time_discounts', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        DB::statement('ALTER TABLE bs_shop_time_discounts CHANGE COLUMN name_str name VARCHAR(160) NULL');
    }
};
