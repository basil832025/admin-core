<?php

use Illuminate\Support\Facades\Route;

Route::get('/csrf-token', function () {
    return response()->json([
        'token' => csrf_token(),
    ]);
})->name('csrf.token');

Route::get('/lang/{locale}', function (string $locale) {
    $allowed = ['uk', 'ru', 'en'];
    abort_unless(in_array($locale, $allowed, true), 404);

    app()->setLocale($locale);

    $referer = (string) request()->headers->get('referer', '/');
    $host = request()->getSchemeAndHttpHost();
    $path = '/';
    $query = '';

    if ($referer !== '' && str_starts_with($referer, $host)) {
        $relative = substr($referer, strlen($host));
        if (is_string($relative) && $relative !== '') {
            $parts = parse_url($relative);
            $path = (string) ($parts['path'] ?? '/');
            $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
        }
    }

    $normalizedPath = preg_replace('#^/(ru|en)(?=/|$)#i', '', $path);
    $normalizedPath = is_string($normalizedPath) && $normalizedPath !== '' ? $normalizedPath : '/';

    $targetPath = $locale === 'uk'
        ? $normalizedPath
        : '/' . $locale . ($normalizedPath === '/' ? '' : $normalizedPath);

    return redirect($targetPath . $query, 303)
        ->cookie('locale', $locale, 60 * 24 * 365);
})->name('lang.switch');

Route::get('/debug/session', function () {
    session(['_dbg' => now()->toDateTimeString()]);

    return [
        'session_id' => session()->getId(),
        'has_cookie' => request()->hasCookie(config('session.cookie')),
        'dbg_value' => session('_dbg'),
        'guard_user_id' => auth()->id(),
        'cookie_name' => config('session.cookie'),
        'cookie_domain' => config('session.domain'),
        'cookie_secure' => config('session.secure'),
    ];
})->middleware('web');

require __DIR__ . '/web_admin.php';

$project = (string) config('project.name', '3piroga');
$frontendPackage = (string) config("projects.local.{$project}.frontend_package", "frontend-{$project}");
$frontendProviderClasses = [
    'frontend-3piroga' => \Basil832025\FrontendThreePiroga\FrontendThreePirogaServiceProvider::class,
    'frontend-sevia' => \Basil832025\FrontendSevia\FrontendSeviaServiceProvider::class,
];

$frontendRoutes = base_path("packages/{$frontendPackage}/routes/web.php");
$frontendProviderClass = $frontendProviderClasses[$frontendPackage] ?? null;

if (file_exists($frontendRoutes) && (! $frontendProviderClass || ! class_exists($frontendProviderClass))) {
    require $frontendRoutes;
}
