<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_report_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 140)->nullable()->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('bs_print_templates', function (Blueprint $table): void {
            $table->foreignId('report_group_id')
                ->nullable()
                ->after('type')
                ->constrained('bs_report_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bs_print_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('report_group_id');
        });

        Schema::dropIfExists('bs_report_groups');
    }
};
