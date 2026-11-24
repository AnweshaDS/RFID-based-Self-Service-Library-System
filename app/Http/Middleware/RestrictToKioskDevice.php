<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when it comes from the kiosk computer itself.
 *
 * The kiosk browser talks to the Laravel server on the same computer, so its address
 * is a loopback address. Anyone reaching the server over the network (Tailscale, Wi-Fi)
 * gets a 403. The allowed addresses come from KIOSK_ALLOWED_IPS in .env.
 */
class RestrictToKioskDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = (array) config('kiosk.allowed_ips', ['127.0.0.1', '::1']);

        abort_unless(in_array($request->ip(), $allowed, true), 403);

        return $next($request);
    }
}