<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bs_products')) {
            return;
        }

        Schema::table('bs_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_products', 'is_dont_forget')) {
                $table->boolean('is_dont_forget')->default(false);
            }

            if (! Schema::hasColumn('bs_products', 'is_recommended')) {
                $table->boolean('is_recommended')->default(false);
            }
        });
    }

    public function down(): void
    {
        // This is a corrective migration for databases where the original
        // migration was marked as ran without adding both columns.
    }
};
