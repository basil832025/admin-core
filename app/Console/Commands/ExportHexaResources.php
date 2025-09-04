<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ExportHexaResources extends Command
{
    protected $signature = 'hexa:export {--force}';
    protected $description = 'Copy Hexa Lite Role resources into app/Filament so you can customize them';

    public function handle(Filesystem $fs)
    {
        $root = base_path('vendor/hexters/hexa-lite/src/Resources/Roles');

        $map = [
            // основной ресурс
            $root.'/Tables/RoleResource.php' => app_path('Filament/Resources/RoleResource.php'),

            // страницы
            $root.'/Pages/ListRoles.php'     => app_path('Filament/Resources/RoleResource/Pages/ListRoles.php'),
            $root.'/Pages/CreateRole.php'    => app_path('Filament/Resources/RoleResource/Pages/CreateRole.php'),
            $root.'/Pages/EditRole.php'      => app_path('Filament/Resources/RoleResource/Pages/EditRole.php'),

            // схема формы
            $root.'/Schemas/RoleForm.php'    => app_path('Filament/Resources/RoleResource/RoleForm.php'),
        ];

        foreach ($map as $from => $to) {
            if (! $fs->exists($from)) {
                $this->error("Missing: $from");
                continue;
            }

            $fs->ensureDirectoryExists(dirname($to));
            $code = $fs->get($from);

            // namespaces -> в твой app
            $code = str_replace(
                [
                    'namespace Hexters\\HexaLite\\Resources\\Roles\\Tables;',
                    'namespace Hexters\\HexaLite\\Resources\\Roles\\Pages;',
                    'namespace Hexters\\HexaLite\\Resources\\Roles\\Schemas;',
                ],
                [
                    'namespace App\\Filament\\Resources;',
                    'namespace App\\Filament\\Resources\\RoleResource\\Pages;',
                    'namespace App\\Filament\\Resources\\RoleResource;',
                ],
                $code
            );

            // use на RoleForm
            $code = str_replace(
                'use Hexters\\HexaLite\\Resources\\Roles\\Schemas\\RoleForm;',
                'use App\\Filament\\Resources\\RoleResource\\RoleForm;',
                $code
            );

            if ($fs->exists($to) && ! $this->option('force')) {
                $this->warn("Skip (exists): $to (use --force to overwrite)");
                continue;
            }

            $fs->put($to, $code);
            $this->info("Written: $to");
        }

        $this->info('Done. Register your own RoleResource and (optionally) stop registering Hexa UI plugin in the panel.');
        return self::SUCCESS;
    }
}
