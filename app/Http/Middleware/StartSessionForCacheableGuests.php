<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

class StartSessionForCacheableGuests extends StartSession
{
    public function handle($request, Closure $next)
    {
        if ($this->shouldSkipSession($request)) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    private function shouldSkipSession(Request $request): bool
    {
        if (! $request->isMethodCacheable()) {
            return false;
        }

        if (! $this->isGuestStatelessRoute($request)) {
            return false;
        }

        $sessionCookie = (string) config('session.cookie');

        return $sessionCookie !== '' && ! $request->cookies->has($sessionCookie);
    }

    private function isGuestStatelessRoute(Request $request): bool
    {
        return (bool) $request->route('page_cache_candidate', false)
            || (bool) $request->route('guest_stateless', false);
    }
}
