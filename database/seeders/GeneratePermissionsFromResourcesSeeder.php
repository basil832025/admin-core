<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Filament\Facades\Filament;

class GeneratePermissionsFromResourcesSeeder extends Seeder
{
    public function run(): void
    {
        $actions = ['view', 'create', 'edit', 'delete'];

        $resourceClasses = Filament::getResources();

        foreach ($resourceClasses as $resourceClass) {
            $name = class_basename($resourceClass);
            $slug = str($name)->replace('Resource', '')->lower()->plural();

            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action} {$slug}",
                ]);
            }
        }

        $this->command->info('Permissions generated for Filament resources.');
    }
}
