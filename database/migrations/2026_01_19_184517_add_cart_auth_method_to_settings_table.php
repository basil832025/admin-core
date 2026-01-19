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
        Schema::table('bs_settings', function (Blueprint $table) {
            $table->string('cart_auth_method', 50)
                ->nullable()
                ->default('phone_password_sms')
                ->after('admin_settings')
                ->comment('Вариант авторизации на сайте: phone_sms (Только телефон и SMS) или phone_password_sms (Телефон и пароль + SMS)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bs_settings', function (Blueprint $table) {
            $table->dropColumn('cart_auth_method');
        });
    }
};
