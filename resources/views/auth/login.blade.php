<x-guest-layout>
    <h1 class="font-display text-3xl text-ink-navy">Welcome back</h1>
    <p class="mt-2 text-sm text-slate">Staff sign-in for the KUET Library system.</p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="identifier" value="Card number or User ID" />
            <x-text-input id="identifier" type="text" name="identifier" :value="old('identifier')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('identifier')" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate">
            <input id="remember_me" type="checkbox" name="remember" class="rounded border-slate/30 text-catalog-teal focus:ring-catalog-teal">
            Remember me
        </label>

        <x-primary-button>Log in</x-primary-button>
    </form>

    <p class="mt-6 text-sm text-slate">
        Forgot your password? Use the password recovery on the library catalog login, or ask the library desk.
    </p>
</x-guest-layout>