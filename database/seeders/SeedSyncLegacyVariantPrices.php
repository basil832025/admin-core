<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SeedSyncLegacyVariantPrices extends Seeder
{
    public function run(): void
    {
        $this->call(SeedSyncMainSitePricesFromSnapshot::class);

        $this->command?->info('Local bs_products price sync completed from main-site snapshot.');
    }
}
