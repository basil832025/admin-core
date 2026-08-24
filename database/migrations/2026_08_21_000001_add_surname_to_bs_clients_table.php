<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_clients', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_clients', 'surname')) {
                $table->string('surname')->nullable()->after('name');
            }
        });

        DB::table('bs_clients')
            ->whereNull('surname')
            ->whereNotNull('name')
            ->orderBy('id')
            ->select(['id', 'name'])
            ->chunkById(200, function ($clients): void {
                foreach ($clients as $client) {
                    $name = trim(preg_replace('/\s+/u', ' ', (string) $client->name) ?? '');

                    if ($name === '' || ! str_contains($name, ' ')) {
                        continue;
                    }

                    [$firstName, $surname] = array_pad(explode(' ', $name, 2), 2, null);

                    DB::table('bs_clients')
                        ->where('id', $client->id)
                        ->update([
                            'name' => $firstName,
                            'surname' => $surname,
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bs_clients', 'surname')) {
            return;
        }

        DB::table('bs_clients')
            ->whereNotNull('surname')
            ->orderBy('id')
            ->select(['id', 'name', 'surname'])
            ->chunkById(200, function ($clients): void {
                foreach ($clients as $client) {
                    DB::table('bs_clients')
                        ->where('id', $client->id)
                        ->update([
                            'name' => trim((string) $client->name . ' ' . (string) $client->surname),
                        ]);
                }
            });

        Schema::table('bs_clients', function (Blueprint $table): void {
            $table->dropColumn('surname');
        });
    }
};
