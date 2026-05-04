<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidatePaginationPage
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // Skip admin/Filament area.
        if ($request->is('admin/*') || $request->is('admin')) {
            return $next($request);
        }

        $raw = $request->query('page');
        if ($raw === null) {
            return $next($request);
        }

        if (is_array($raw)) {
            return response()->view('404', [], 404);
        }

        $raw = trim((string) $raw);
        if ($raw === '' || ! ctype_digit($raw)) {
            return response()->view('404', [], 404);
        }

        // Must be >= 1. ("0", "00" are invalid)
        if ((int) $raw < 1) {
            return response()->view('404', [], 404);
        }

        // Protect from integer overflow: treat values > PHP_INT_MAX as invalid.
        // Compare as strings to avoid numeric overflow.
        $max = (string) PHP_INT_MAX;
        $rawNoZeros = ltrim($raw, '0');
        if ($rawNoZeros === '') {
            return response()->view('404', [], 404);
        }

        if (strlen($rawNoZeros) > strlen($max)) {
            return response()->view('404', [], 404);
        }
        if (strlen($rawNoZeros) === strlen($max) && strcmp($rawNoZeros, $max) > 0) {
            return response()->view('404', [], 404);
        }

        return $next($request);
    }
}
