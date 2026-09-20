<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $email = strtolower($googleUser->getEmail());
        $domain = substr(strrchr($email, '@'), 1);
        $allowedDomains = config('services.google.allowed_domains', []);

        if (! in_array($domain, $allowedDomains, true)) {
            return redirect()->route('login')->withErrors([
                'email' => 'Please sign in with your official KUET email address ('.implode(' or ', $allowedDomains).').',
            ]);
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $email)
            ->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ]);
        } elseif (! $user->google_id) {
            $user->forceFill(['google_id' => $googleUser->getId()])->save();
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}