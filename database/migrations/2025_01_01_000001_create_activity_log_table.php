<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name', 255)->nullable();
            $table->text('description');
            $table->string('subject_type', 255)->nullable();
            $table->string('event', 255)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type', 255)->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->json('properties')->nullable();
            $table->char('batch_uuid', 36)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->index(['causer_type', 'causer_id'], 'causer');
            $table->index(['log_name'], 'activity_log_log_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
