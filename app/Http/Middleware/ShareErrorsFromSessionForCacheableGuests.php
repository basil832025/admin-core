<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ShareErrorsFromSessionForCacheableGuests extends ShareErrorsFromSession
{
    public function handle($request, Closure $next)
    {
        if ($this->shouldSkipSession($request)) {
            $this->view->share('errors', new \Illuminate\Support\ViewErrorBag);

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
