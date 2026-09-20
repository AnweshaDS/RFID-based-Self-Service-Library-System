<x-guest-layout>
    <h1 class="font-display text-3xl text-ink-navy">Welcome back</h1>
    <p class="mt-2 text-sm text-slate">Sign in to your KUET Library account.</p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <a href="{{ route('auth.google.redirect') }}"
       class="mt-6 flex w-full items-center justify-center gap-3 rounded-lg border border-slate/30 bg-white px-4 py-2.5 text-sm font-semibold text-ink transition hover:border-catalog-teal hover:text-catalog-teal">
        <svg class="h-5 w-5" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.9-2.26 5.36-4.78 7.02l7.73 6c4.51-4.18 7.09-10.36 7.09-17.49z"/>
            <path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24 24 0 0 0 0 21.56l7.98-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.9l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Continue with KUET Google account
    </a>
    <p class="mt-2 text-xs text-slate">Only official @kuet.ac.bd / @stud.kuet.ac.bd accounts can sign in this way.</p>

    <div class="my-6 flex items-center gap-3">
        <span class="h-px flex-1 bg-slate/20"></span>
        <span class="text-xs text-slate">or sign in with email</span>
        <span class="h-px flex-1 bg-slate/20"></span>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate">
                <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate/30 text-catalog-teal focus:ring-catalog-teal">
                Remember me
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-catalog-teal hover:underline">
                    Forgot password?
                </a>
            @endif
        </div>

        <x-primary-button>Log in</x-primary-button>
    </form>
</x-guest-layout>