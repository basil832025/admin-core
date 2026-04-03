<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SeedSyncLegacyVariantPrices extends Seeder
{
    public function run(): void
    {
        $this->call(SeedRestoreClassicPieVariantsFromBsProducts::class);

        $this->command?->info('Local bs_products variant price sync completed via classic matrix seeder.');
    }
}
