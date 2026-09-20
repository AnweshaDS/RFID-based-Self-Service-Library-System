<x-kiosk-layout>
    <div class="rounded-2xl bg-paper/5 p-10 text-center backdrop-blur">
        <p class="font-display text-4xl">Welcome</p>
        <p class="mt-3 text-paper/70">Tap your library card to begin.</p>

        <div class="mx-auto mt-8 flex h-28 w-28 items-center justify-center rounded-full border-2 border-signal-amber/60">
            <svg class="h-12 w-12 text-signal-amber" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="7" y="9" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                <path d="M2 12C2 6.48 6.48 2 12 2s10 4.48 10 10-4.48 10-10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="2 4"/>
            </svg>
        </div>

        @error('uid')
            <p class="mt-6 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ $message }}</p>
        @enderror

        @if (session('status'))
            <p class="mt-6 rounded-lg bg-catalog-teal/10 px-4 py-2.5 text-sm text-catalog-teal">{{ session('status') }}</p>
        @endif
    </div>

    @if (config('kiosk.simulation_mode'))
        <div class="mt-6 rounded-2xl border border-dashed border-paper/20 p-6">
            <p class="text-xs uppercase tracking-wide text-paper/40">Simulation mode — no reader connected yet</p>
            <form method="POST" action="{{ route('kiosk.scan') }}" class="mt-3 flex gap-3">
                @csrf
                <input type="text" name="uid" placeholder="04:A3:91:7B:22:18"
                       class="flex-1 rounded-lg border-paper/20 bg-transparent px-3.5 py-2.5 text-sm text-paper placeholder:text-paper/40 focus:border-signal-amber focus:ring-signal-amber">
                <button type="submit" class="rounded-lg bg-signal-amber px-4 py-2.5 text-sm font-semibold text-ink-navy transition hover:bg-signal-amber/90">
                    Simulate scan
                </button>
            </form>
        </div>
    @endif
</x-kiosk-layout>