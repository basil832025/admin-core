<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_kitchen_ticket_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('kitchen_ticket_id');
            $table->string('from_stage', 255)->nullable();
            $table->string('to_stage', 255);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['user_id'], 'kitchen_ticket_events_user_id_foreign');
            $table->index(['kitchen_ticket_id', 'created_at'], 'kitchen_ticket_events_kitchen_ticket_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_kitchen_ticket_events');
    }
};
