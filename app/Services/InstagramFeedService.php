<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramFeedService
{
    /**
     * @return array<int, array<string, string>>
     */
    public function latest(int $limit = 6): array
    {
        $limit = max(1, min($limit, 12));
        $token = trim((string) config('services.instagram.access_token', ''));

        if ($token === '') {
            return [];
        }

        $cacheKey = 'instagram:feed:' . str_replace('/', ':', $this->endpointPath()) . ':' . $limit;
        $ttl = max(60, (int) config('services.instagram.cache_ttl', 3600));

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get('https://graph.instagram.com/' . $this->endpointPath(), [
                    'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
                    'limit' => $limit,
                    'access_token' => $token,
                ]);

            if (! $response->ok()) {
                Log::warning('Instagram feed request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $posts = collect($response->json('data', []))
                ->map(function (array $item): ?array {
                    $mediaType = (string) ($item['media_type'] ?? '');
                    $image = (string) ($item['thumbnail_url'] ?? $item['media_url'] ?? '');

                    if ($image === '' || (string) ($item['permalink'] ?? '') === '') {
                        return null;
                    }

                    return [
                        'id' => (string) ($item['id'] ?? ''),
                        'caption' => trim((string) ($item['caption'] ?? '')),
                        'media_type' => $mediaType,
                        'image' => $image,
                        'permalink' => (string) $item['permalink'],
                        'timestamp' => (string) ($item['timestamp'] ?? ''),
                    ];
                })
                ->filter()
                ->take($limit)
                ->values()
                ->all();

            Cache::put($cacheKey, $posts, now()->addSeconds($ttl));

            return $posts;
        } catch (\Throwable $e) {
            Log::warning('Instagram feed request exception', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function endpointPath(): string
    {
        $userId = trim((string) config('services.instagram.user_id', ''));

        return ($userId !== '' ? rawurlencode($userId) : 'me') . '/media';
    }
}
