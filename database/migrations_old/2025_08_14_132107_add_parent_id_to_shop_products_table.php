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
        Schema::table('products', function (Blueprint $table) {
            // parent_id указывает на родительский товар (NULL = это родитель)
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('products')   // self-foreign key
                ->nullOnDelete()                 // при удалении родителя — обнуляем, не каскадим
                ->cascadeOnUpdate();             // при изменении id (почти не бывает) — обновим
        });

        // Если хочешь запретить "сам себе родитель" (MySQL 8+), можно добавить CHECK:
        // DB::statement('ALTER TABLE shop_products ADD CONSTRAINT chk_parent_self CHECK (parent_id IS NULL OR parent_id <> id)');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // удаляем внешний ключ и колонку
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');

            // Если добавлял CHECK — не забудь дропнуть:
            // DB::statement('ALTER TABLE shop_products DROP CHECK chk_parent_self');
        });
    }
};
