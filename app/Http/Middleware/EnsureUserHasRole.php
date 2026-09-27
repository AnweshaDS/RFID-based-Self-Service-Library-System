<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $authorized = false;
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                $authorized = true;
                break;
            }
        }

        if (! $authorized) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
