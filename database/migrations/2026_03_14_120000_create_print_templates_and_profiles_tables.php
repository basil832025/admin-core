<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_print_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 32)->default('receipt')->index();
            $table->string('engine', 32)->default('twig');
            $table->string('output_format', 32)->default('pdf');
            $table->text('description')->nullable();
            $table->longText('template_body');
            $table->json('parameters_schema')->nullable();
            $table->json('data_sources')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bs_print_operation_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('operation_code', 64)->unique();
            $table->foreignId('print_template_id')->nullable()->constrained('bs_print_templates')->nullOnDelete();
            $table->unsignedBigInteger('printer_id')->nullable();
            $table->string('printer_name')->nullable();
            $table->unsignedInteger('copies')->default(1);
            $table->json('paper_settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_print_operation_profiles');
        Schema::dropIfExists('bs_print_templates');
    }
};
