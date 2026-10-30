<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     *
     * After the Laravel session ends, the browser is sent to Koha's logout address as a
     * normal page visit, so Koha ends the session for this browser too. The address
     * comes from the same setting as the Koha quick links, so both use the same host.
     * If that setting is empty, the user just goes to the Laravel login page.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $kohaStaffUrl = rtrim((string) config('services.koha.staff_url'), '/');

        if ($kohaStaffUrl !== '') {
            return redirect()->away($kohaStaffUrl . '/cgi-bin/koha/mainpage.pl?logout.x=1');
        }

        return redirect()->route('login');
    }
}