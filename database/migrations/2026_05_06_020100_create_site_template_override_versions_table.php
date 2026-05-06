<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bs_site_template_override_versions')) {
            return;
        }

        Schema::create('bs_site_template_override_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_template_override_id')
                ->constrained('bs_site_template_overrides')
                ->cascadeOnDelete();
            $table->longText('body');
            $table->text('change_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('bs_site_template_override_versions')) {
            Schema::drop('bs_site_template_override_versions');
        }
    }
};
