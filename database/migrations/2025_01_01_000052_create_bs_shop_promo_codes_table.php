<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_promo_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 64);
            $table->decimal('percent', 5, 2);
            $table->boolean('is_active')->default('1');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('per_client_limit')->default('1');
            $table->text('note');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unique(['code'], 'spc_code_unique');
            $table->index(['is_active'], 'spc_active_idx');
            $table->index(['starts_at'], 'spc_starts_idx');
            $table->index(['ends_at'], 'spc_ends_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_promo_codes');
    }
};
