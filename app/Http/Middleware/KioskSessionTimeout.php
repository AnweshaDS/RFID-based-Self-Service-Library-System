<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

class KioskSessionTimeout
{
    public function handle(Request $request, Closure $next)
    {
        // 5 extra seconds so the on-screen countdown always finishes first
        $limit = (int) config('kiosk.idle_timeout', 60) + 5;
        $lastActivity = Session::get('kiosk_last_activity');

        if ($lastActivity && now()->timestamp - Carbon::parse($lastActivity)->timestamp > $limit) {
                        Session::invalidate();
            Session::regenerateToken();

            return redirect()->route('kiosk.welcome')
                ->with('status', 'Session timed out for your security.');
        }

        Session::put('kiosk_last_activity', now());

        return $next($request);
    }
}