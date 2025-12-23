<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_site_text_groups', function (Blueprint $table) {
                $t->id();
                $t->string('slug')->unique();
                $t->json('title')->nullable();
                $t->string('description')->nullable();
                $t->unsignedInteger('position')->default(0);
                $t->boolean('active')->default(true);
                $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_site_text_groups');
    }
};
