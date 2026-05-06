<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bs_site_template_override_versions')) {
            Schema::create('bs_site_template_override_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_template_override_id');
                $table->longText('body');
                $table->text('change_note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('site_template_override_id', 'stov_site_template_override_id_idx');
            });
        }

        $foreignExists = collect(DB::select('SHOW CREATE TABLE `bs_site_template_override_versions`'))
            ->pluck('Create Table')
            ->contains(fn ($sql) => is_string($sql) && str_contains($sql, 'stov_template_fk'));

        if (! $foreignExists) {
            Schema::table('bs_site_template_override_versions', function (Blueprint $table) {
                $table->foreign('site_template_override_id', 'stov_template_fk')
                    ->references('id')
                    ->on('bs_site_template_overrides')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bs_site_template_override_versions')) {
            Schema::table('bs_site_template_override_versions', function (Blueprint $table) {
                try {
                    $table->dropForeign('stov_template_fk');
                } catch (\Throwable $e) {
                }
            });

            Schema::drop('bs_site_template_override_versions');
        }
    }
};
