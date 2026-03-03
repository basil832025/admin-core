<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_products', 'is_imported')) {
                $table->boolean('is_imported')->default(false)->after('code2')->index();
            }

            if (! Schema::hasColumn('bs_products', 'import_source_id')) {
                $table->unsignedBigInteger('import_source_id')->nullable()->after('is_imported')->index();
            }
        });

        DB::table('bs_products')
            ->where('slug', 'like', 'src-%-p-%')
            ->update(['is_imported' => 1]);

        DB::statement(
            'UPDATE bs_products p '
            . 'INNER JOIN bs_cc_source_products sp ON sp.local_product_id = p.id '
            . 'SET p.is_imported = 1, p.import_source_id = sp.source_id'
        );
    }

    public function down(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_products', 'import_source_id')) {
                $table->dropColumn('import_source_id');
            }

            if (Schema::hasColumn('bs_products', 'is_imported')) {
                $table->dropColumn('is_imported');
            }
        });
    }
};
