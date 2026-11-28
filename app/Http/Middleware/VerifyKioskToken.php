<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyKioskToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $sent = (string) $request->header('X-Kiosk-Token', '');
        $expected = (string) config('services.kiosk.token');

        if ($expected !== '' && $sent !== '' && hash_equals($expected, $sent)) {
            return $next($request);
        }

        return response()->json(['message' => 'Invalid kiosk token'], 401);
    }
}