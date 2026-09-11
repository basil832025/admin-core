<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_holiday_periods', function (Blueprint $table): void {
            $table->id();
            $table->date('date_from')->index();
            $table->date('date_to')->index();
            $table->json('comment')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_holiday_periods');
    }
};
