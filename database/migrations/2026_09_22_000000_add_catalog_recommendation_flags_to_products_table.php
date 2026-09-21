<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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
        Schema::table('bs_products', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_products', 'is_recommended')) {
                $table->dropColumn('is_recommended');
            }

            if (Schema::hasColumn('bs_products', 'is_dont_forget')) {
                $table->dropColumn('is_dont_forget');
            }
        });
    }
};
