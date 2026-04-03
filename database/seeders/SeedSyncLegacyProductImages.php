<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedSyncLegacyProductImages extends Seeder
{
    public function run(): void
    {
        $legacyImagesPath = $this->resolveLegacyImagesPath();

        if ($legacyImagesPath === null || ! is_dir($legacyImagesPath)) {
            $this->command?->error('Legacy images path not found. Set LEGACY_IMAGES_PATH in env and run again.');
            return;
        }

        $targetMainDir = storage_path('app/public/products/main');
        $targetSmallDir = storage_path('app/public/products/small');

        if (! is_dir($targetMainDir)) {
            mkdir($targetMainDir, 0777, true);
        }

        if (! is_dir($targetSmallDir)) {
            mkdir($targetSmallDir, 0777, true);
        }

        $checked = 0;
        $copiedMain = 0;
        $copiedSmall = 0;
        $updatedRows = 0;
        $missingLegacyMain = 0;

        $parents = DB::table('bs_products')
            ->whereNull('parent_id')
            ->select(['id', 'main_image', 'main_image_small'])
            ->orderBy('id')
            ->get();

        foreach ($parents as $parent) {
            $checked++;
            $id = (int) $parent->id;

            $legacyMain = $this->firstExistingFile($legacyImagesPath, [
                "{$id}.1.b.png",
                "{$id}.1.b.jpg",
            ]);

            if ($legacyMain === null) {
                $missingLegacyMain++;
                continue;
            }

            $legacySmall = $this->firstExistingFile($legacyImagesPath, [
                "{$id}.1.s.jpg",
                "{$id}.1.s.png",
            ]);

            $mainExt = pathinfo($legacyMain, PATHINFO_EXTENSION);
            $targetMainPath = $targetMainDir . DIRECTORY_SEPARATOR . "{$id}.1.b.{$mainExt}";
            if (! is_file($targetMainPath) && copy($legacyMain, $targetMainPath)) {
                $copiedMain++;
            }

            $newMainRel = "products/main/{$id}.1.b.{$mainExt}";

            $newSmallRel = (string) ($parent->main_image_small ?? '');
            if ($legacySmall !== null) {
                $smallExt = pathinfo($legacySmall, PATHINFO_EXTENSION);
                $targetSmallPath = $targetSmallDir . DIRECTORY_SEPARATOR . "{$id}.1.s.{$smallExt}";
                if (! is_file($targetSmallPath) && copy($legacySmall, $targetSmallPath)) {
                    $copiedSmall++;
                }
                $newSmallRel = "products/small/{$id}.1.s.{$smallExt}";
            }

            $currentMain = (string) ($parent->main_image ?? '');
            $currentSmall = (string) ($parent->main_image_small ?? '');

            if ($currentMain !== $newMainRel || ($newSmallRel !== '' && $currentSmall !== $newSmallRel)) {
                DB::table('bs_products')
                    ->where('id', $id)
                    ->update([
                        'main_image' => $newMainRel,
                        'main_image_small' => $newSmallRel !== '' ? $newSmallRel : null,
                        'updated_at' => now(),
                    ]);
                $updatedRows++;
            }
        }

        $this->command?->info(
            "Legacy product image sync done. Checked: {$checked}, Copied main: {$copiedMain}, Copied small: {$copiedSmall}, Updated DB rows: {$updatedRows}, Missing legacy main image: {$missingLegacyMain}"
        );
    }

    private function resolveLegacyImagesPath(): ?string
    {
        $fromEnv = env('LEGACY_IMAGES_PATH');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return rtrim($fromEnv, '/\\');
        }

        $candidates = [
            base_path('../3piroga/images/catalog_products'),
            public_path('images/catalog_products'),
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function firstExistingFile(string $basePath, array $relativeFiles): ?string
    {
        foreach ($relativeFiles as $relative) {
            $fullPath = $basePath . DIRECTORY_SEPARATOR . $relative;
            if (is_file($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }
}
