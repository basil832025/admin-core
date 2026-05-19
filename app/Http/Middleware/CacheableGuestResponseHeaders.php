<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CacheableGuestResponseHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $this->shouldMarkCacheable($request, $response)) {
            return $response;
        }

        $publicMaxAge = max(0, (int) config('app.page_cache_public_max_age', 60));
        $sharedMaxAge = max($publicMaxAge, (int) config('app.page_cache_shared_max_age', 300));

        $response->headers->set('Cache-Control', sprintf('public, max-age=%d, s-maxage=%d', $publicMaxAge, $sharedMaxAge));
        $response->headers->set('X-Page-Cache-Candidate', 'guest');

        return $response;
    }

    private function shouldMarkCacheable(Request $request, $response): bool
    {
        if (! $request->isMethodCacheable()) {
            return false;
        }

        if (! (bool) $request->route('page_cache_candidate', false)) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && ! str_contains(strtolower($contentType), 'text/html')) {
            return false;
        }

        $sessionCookie = (string) config('session.cookie');
        if ($sessionCookie !== '' && $request->cookies->has($sessionCookie)) {
            return false;
        }

        return true;
    }
}
