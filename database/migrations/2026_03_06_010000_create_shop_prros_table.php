<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_prros', function (Blueprint $table) {
            $table->id();
            $table->date('registered_at')->index();
            $table->string('organization_name');
            $table->string('prro_number')->index();

            $table->string('certificate_path')->nullable();
            $table->longText('certificate_base64')->nullable();

            $table->string('key_path')->nullable();
            $table->longText('key_base64')->nullable();
            $table->string('key_password')->nullable();

            $table->boolean('use_for_liqpay')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_prros');
    }
};
