<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'KUET Library') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-ink antialiased">
    <div class="grid min-h-screen bg-paper lg:grid-cols-2">

        {{-- Identity panel --}}
        <div class="relative hidden overflow-hidden bg-ink-navy px-12 py-10 text-paper lg:flex lg:flex-col lg:justify-between">
            <a href="/" class="flex items-center gap-3">
                <x-application-logo class="h-10 w-10 text-signal-amber" />
                <span class="font-display text-lg">KUET Library</span>
            </a>

            <div class="max-w-sm">
                <p class="font-display text-4xl leading-tight">
                    Tap in.<br>Sign in.<br>Check out.
                </p>
                <p class="mt-4 text-sm text-paper/70">
                    One account for the KUET Central Library - self-service checkout, RFID kiosks, and your borrowing history, all in one place.
                </p>
            </div>

            <p class="text-xs text-paper/50">Khulna University of Engineering &amp; Technology</p>

            <div class="scan-line" aria-hidden="true"></div>
        </div>

        {{-- Form panel --}}
        <div class="flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-16">
            <a href="/" class="mb-8 flex items-center gap-3 lg:hidden">
                <x-application-logo class="h-9 w-9 text-catalog-teal" />
                <span class="font-display text-lg text-ink-navy">KUET Library</span>
            </a>

            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>