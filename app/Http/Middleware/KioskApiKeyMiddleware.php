<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KioskApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = (string) config('app.kiosk_api_key', '');
        $providedKey = (string) $request->header('X-Kiosk-Key', '');

        if ($expectedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized kiosk request.',
            ], 401);
        }

        return $next($request);
    }
}
