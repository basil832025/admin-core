<?php

namespace App\Services\SiteTemplates;

use App\Models\SiteTemplateOverride;

class TemplateSyncService
{
    public function __construct(private readonly TemplateRegistry $registry) {}

    public function sync(): int
    {
        $count = 0;

        foreach ($this->registry->all() as $key => $meta) {
            $absolutePath = base_path($meta['source_path']);
            if (! is_file($absolutePath)) {
                continue;
            }

            $body = file_get_contents($absolutePath);
            if ($body === false) {
                continue;
            }

            SiteTemplateOverride::query()->updateOrCreate(
                ['key' => $key],
                [
                    'title' => $meta['title'],
                    'source_path' => $meta['source_path'],
                    'engine' => 'blade',
                    'original_snapshot' => $body,
                    'original_hash' => sha1($body),
                    'last_synced_at' => now(),
                    'updated_by' => auth()->id(),
                ]
            );

            $count++;
        }

        return $count;
    }
}
