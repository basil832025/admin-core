<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_kitchen_ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_ticket_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // кто нажал кнопку
            $table->json('meta')->nullable();     // любые доп.данные (терминал, комментарий и т.п.)
            $table->timestamps();
            $table->index(['kitchen_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_kitchen_ticket_events');
    }
};
