<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_languages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('code', 5);
            $table->string('country_code', 3);
            $table->unsignedInteger('position')->default('0');
            $table->boolean('active')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['code'], 'languages_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_languages');
    }
};
