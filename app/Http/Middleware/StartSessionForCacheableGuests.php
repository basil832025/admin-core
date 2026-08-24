<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Session\Middleware\StartSession;

class StartSessionForCacheableGuests extends StartSession
{
    public function handle($request, Closure $next)
    {
        if ($this->shouldUseAdminGuard($request)) {
            Auth::shouldUse('admin');
        }

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

    private function shouldUseAdminGuard(Request $request): bool
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return true;
        }

        $routeName = $request->route()?->getName();

        if (is_string($routeName) && str_starts_with($routeName, 'filament.admin.')) {
            return true;
        }

        $referer = (string) $request->headers->get('referer', '');

        if ($referer === '') {
            return false;
        }

        $path = (string) parse_url($referer, PHP_URL_PATH);

        return $path === '/admin' || str_starts_with($path, '/admin/');
    }
}
