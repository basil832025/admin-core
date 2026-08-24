<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bs_client_recipients')) {
            Schema::create('bs_client_recipients', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('client_id')->constrained('bs_clients')->cascadeOnDelete();
                $table->string('surname');
                $table->string('name');
                $table->string('patronymic')->nullable();
                $table->string('phone', 32);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['client_id', 'phone']);
            });
        }

        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_shop_orders', 'client_recipient_id')) {
                $table->foreignId('client_recipient_id')
                    ->nullable()
                    ->after('client_address_id')
                    ->constrained('bs_client_recipients')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('bs_shop_orders', 'recipient_surname')) {
                $table->string('recipient_surname')->nullable()->after('client_recipient_id');
            }

            if (! Schema::hasColumn('bs_shop_orders', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('recipient_surname');
            }

            if (! Schema::hasColumn('bs_shop_orders', 'recipient_patronymic')) {
                $table->string('recipient_patronymic')->nullable()->after('recipient_name');
            }

            if (! Schema::hasColumn('bs_shop_orders', 'recipient_phone')) {
                $table->string('recipient_phone', 32)->nullable()->after('recipient_patronymic');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_shop_orders', 'client_recipient_id')) {
                $table->dropConstrainedForeignId('client_recipient_id');
            }

            foreach (['recipient_surname', 'recipient_name', 'recipient_patronymic', 'recipient_phone'] as $column) {
                if (Schema::hasColumn('bs_shop_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('bs_client_recipients');
    }
};
