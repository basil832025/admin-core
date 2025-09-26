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
        Schema::table('bs_products', function (Blueprint $table) {
            // путь/имя файла маленькой обложки (jpg/png/webp)
            $table->string('main_image_small', 255)
                ->nullable()
                ->after('main_image'); // если поля main_image нет — убери этот ->after()
        });
    }

    public function down(): void
    {
        Schema::table('bs_products', function (Blueprint $table) {
            $table->dropColumn('main_image_small');
        });
    }
};
