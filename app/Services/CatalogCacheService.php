<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CatalogCacheService
{
    private const VERSION_KEY = 'catalog_cache_version';

    public function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn (): int => 1);
    }

    public function bump(): int
    {
        $nextVersion = $this->version() + 1;

        Cache::forever(self::VERSION_KEY, $nextVersion);

        return $nextVersion;
    }

    public function key(string $segment, array $scope = [], ?string $locale = null): string
    {
        $version = $this->version();
        $normalizedScope = collect($scope)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->sort()
            ->values()
            ->all();

        $scopeHash = md5(implode('|', $normalizedScope));
        $parts = ["catalog:v{$version}", $segment, $scopeHash];

        if ($locale !== null && $locale !== '') {
            $parts[] = strtolower($locale);
        }

        return implode(':', $parts);
    }
}
