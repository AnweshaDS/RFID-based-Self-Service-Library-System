<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'KUET Library') }} - Kiosk</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative min-h-screen overflow-hidden bg-ink-navy font-sans text-paper antialiased">
    <div class="scan-line" aria-hidden="true"></div>

    <div class="flex min-h-screen flex-col items-center justify-center px-8 py-12">
        <div class="mb-10 flex items-center gap-3">
            <x-application-logo class="h-9 w-9 text-signal-amber" />
            <span class="font-display text-xl">KUET Library Kiosk</span>
        </div>

        <div class="w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>

    @stack('scripts')
</body>
</html>