<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bs_establishment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')
                ->nullable()           // или ->constrained('bs_locations')->nullOnDelete();
                ->index()
                ->after('id');
            $table->string('author_name');

            $table->string('author_avatar')->nullable();
            $table->string('email')->nullable();
            $table->tinyInteger('rating')->default(5);
            $table->text('text');

            $table->boolean('is_active')->default(true);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

    }
        /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bs_establishment_reviews');
    }
};
