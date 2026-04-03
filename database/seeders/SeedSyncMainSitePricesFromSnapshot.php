<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedSyncMainSitePricesFromSnapshot extends Seeder
{
    public function run(): void
    {
        $snapshotPath = __DIR__ . '/dumps/main_site_prices.php';
        if (! is_file($snapshotPath)) {
            $this->command?->error('Price snapshot file not found: ' . $snapshotPath);
            return;
        }

        $snapshot = require $snapshotPath;
        if (! is_array($snapshot) || empty($snapshot)) {
            $this->command?->error('Price snapshot is empty or invalid.');
            return;
        }

        $normalized = [];
        foreach ($snapshot as $id => $price) {
            $pid = (int) $id;
            if ($pid <= 0 || ! is_numeric($price)) {
                continue;
            }
            $normalized[$pid] = (float) $price;
        }

        if (empty($normalized)) {
            $this->command?->error('No valid id => price entries in snapshot.');
            return;
        }

        $foundIds = [];
        $checked = 0;
        $updated = 0;

        DB::beginTransaction();

        try {
            foreach (array_chunk(array_keys($normalized), 300) as $chunk) {
                $rows = DB::table('bs_products')
                    ->whereIn('id', $chunk)
                    ->where(function ($query): void {
                        $query->whereNull('is_imported')->orWhere('is_imported', 0);
                    })
                    ->select(['id', 'price'])
                    ->get();

                foreach ($rows as $row) {
                    $pid = (int) $row->id;
                    $foundIds[$pid] = true;
                    $checked++;

                    $targetPrice = $normalized[$pid];
                    $currentPrice = (float) $row->price;

                    if (abs($currentPrice - $targetPrice) <= 0.009) {
                        continue;
                    }

                    DB::table('bs_products')
                        ->where('id', $pid)
                        ->update([
                            'price' => $targetPrice,
                            'updated_at' => now(),
                        ]);

                    $updated++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $missing = count($normalized) - count($foundIds);
        $this->command?->info("Main-site prices synced from snapshot. Snapshot rows: " . count($normalized) . ", Checked: {$checked}, Updated: {$updated}, Missing(non-imported/not found): {$missing}");
    }
}
