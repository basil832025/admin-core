<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LinkFrontendBuild extends Command
{
    protected $signature = 'frontend:link-build
        {--public-root= : Absolute web document root, e.g. /home/user/web/domain/public_html}
        {--replace : Back up an existing build directory before linking}';

    protected $description = 'Link the active project build directly to its frontend package';

    public function handle(): int
    {
        $package = match (config('project.name')) {
            '3piroga' => 'frontend-3piroga',
            'duxi' => 'frontend-sevia',
            default => null,
        };

        if ($package === null) {
            $this->error('Unsupported APP_PROJECT. Expected 3piroga or duxi.');

            return self::FAILURE;
        }

        $root = realpath($this->option('public-root') ?: public_path());
        $source = realpath(base_path("packages/{$package}/public/build/{$package}"));

        if ($root === false || $source === false || ! is_file($source.'/manifest.json')) {
            $this->error('Public root or package build manifest does not exist.');

            return self::FAILURE;
        }

        $manifest = json_decode(file_get_contents($source.'/manifest.json'), true);
        if (! is_array($manifest) || $manifest === []) {
            $this->error('Invalid package build manifest.');

            return self::FAILURE;
        }
        foreach ($manifest as $entry) {
            foreach (array_merge([$entry['file'] ?? ''], $entry['css'] ?? [], $entry['assets'] ?? []) as $file) {
                if ($file === '' || ! is_file($source.'/'.$file)) {
                    $this->error('Incomplete package build: '.$file);

                    return self::FAILURE;
                }
            }
        }

        $parent = $root.DIRECTORY_SEPARATOR.'build';
        $target = $parent.DIRECTORY_SEPARATOR.$package;
        if (realpath($target) === $source) {
            $this->info('Build already linked: '.$target);

            return self::SUCCESS;
        }
        if (! is_dir($parent) && ! mkdir($parent, 0755, true)) {
            $this->error('Cannot create build directory.');

            return self::FAILURE;
        }

        $backup = null;
        if (file_exists($target) || is_link($target)) {
            if (! $this->option('replace')) {
                $this->error('Build path exists. Use --replace to preserve it as a backup and create the link.');

                return self::FAILURE;
            }
            $backup = $target.'.backup-'.date('YmdHis').'-'.bin2hex(random_bytes(3));
            if (! rename($target, $backup)) {
                $this->error('Cannot back up existing build path.');

                return self::FAILURE;
            }
        }

        clearstatcache(true, $target);
        if (! @symlink($source, $target)) {
            if ($backup !== null) {
                rename($backup, $target);
            }
            $this->error('Cannot create symbolic link. Check filesystem permissions and symlink support.');

            return self::FAILURE;
        }

        $this->info($target.' -> '.$source);
        if ($backup !== null) {
            $this->info('Previous build preserved: '.$backup);
        }
        $this->info('The web server must allow symbolic links and be able to read the package build.');

        return self::SUCCESS;
    }
}
