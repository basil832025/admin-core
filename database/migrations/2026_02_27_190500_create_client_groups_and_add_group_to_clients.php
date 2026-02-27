<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bs_client_groups')) {
            Schema::create('bs_client_groups', function (Blueprint $table): void {
                $table->id();
                $table->json('name');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_blacklist')->default(false);
                $table->timestamps();
            });
        }

        Schema::table('bs_clients', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_clients', 'client_group_id')) {
                $table->foreignId('client_group_id')
                    ->nullable()
                    ->after('note')
                    ->constrained('bs_client_groups')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_clients', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_clients', 'client_group_id')) {
                $table->dropConstrainedForeignId('client_group_id');
            }
        });

        Schema::dropIfExists('bs_client_groups');
    }
};
