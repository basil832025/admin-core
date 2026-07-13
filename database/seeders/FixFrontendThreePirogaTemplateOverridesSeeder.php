<?php

namespace Database\Seeders;

use App\Models\SiteTemplateOverride;
use App\Services\SiteTemplates\TemplateRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixFrontendThreePirogaTemplateOverridesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $registry = app(TemplateRegistry::class)->all();

        DB::transaction(function () use ($registry, $now): void {
            foreach ($registry as $key => $meta) {
                $sourcePath = (string) ($meta['source_path'] ?? '');
                $absolutePath = base_path($sourcePath);

                if ($sourcePath === '' || ! is_file($absolutePath)) {
                    $this->command?->warn("Skipped {$key}: file not found at {$sourcePath}");
                    continue;
                }

                $body = file_get_contents($absolutePath);
                if ($body === false) {
                    $this->command?->warn("Skipped {$key}: unable to read {$sourcePath}");
                    continue;
                }

                $template = SiteTemplateOverride::query()->firstOrNew(['key' => $key]);

                $template->fill([
                    'title' => (string) ($meta['title'] ?? $key),
                    'source_path' => $sourcePath,
                    'engine' => 'blade',
                    'original_snapshot' => $body,
                    'override_body' => $body,
                    'original_hash' => sha1($body),
                    'last_synced_at' => $now,
                    'updated_by' => null,
                ]);

                if (! $template->exists) {
                    $template->is_active = false;
                    $template->created_by = null;
                }

                $template->save();
            }
        });
    }
}