<?php

namespace App\Console\Commands;

use App\Services\SiteTemplates\TemplateSyncService;
use Illuminate\Console\Command;

class SyncSiteTemplates extends Command
{
    protected $signature = 'templates:sync';

    protected $description = 'Sync editable site templates from view files into the database';

    public function handle(TemplateSyncService $service): int
    {
        $count = $service->sync();

        $this->info("Synced {$count} site templates.");

        return self::SUCCESS;
    }
}
