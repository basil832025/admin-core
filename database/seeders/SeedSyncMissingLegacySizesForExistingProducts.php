<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SeedSyncMissingLegacySizesForExistingProducts extends Seeder
{
    public function run(): void
    {
        $this->call(SeedRestoreClassicPieVariantsFromBsProducts::class);

        $this->command?->info('Local bs_products missing-size sync completed via classic matrix seeder.');
    }
}
