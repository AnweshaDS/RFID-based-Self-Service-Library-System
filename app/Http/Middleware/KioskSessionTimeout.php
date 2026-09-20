<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class KioskSessionTimeout
{
    protected int $timeoutSeconds = 60;

    public function handle(Request $request, Closure $next)
    {
        $lastActivity = Session::get('kiosk_last_activity');

        if ($lastActivity && now()->diffInSeconds($lastActivity) > $this->timeoutSeconds) {
            Session::forget('kiosk_patron');
            Session::forget('kiosk_last_activity');

            return redirect()->route('kiosk.welcome')
                ->with('status', 'Session timed out for your security.');
        }

        Session::put('kiosk_last_activity', now());

        return $next($request);
    }
}