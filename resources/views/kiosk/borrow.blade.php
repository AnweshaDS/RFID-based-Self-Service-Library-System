<x-kiosk-layout :scan-line="true" max-width="max-w-xl">
    <div class="rounded-2xl bg-paper/8 p-8 backdrop-blur shadow-2xl border border-paper/10">
        <div class="flex items-center justify-between border-b border-paper/10 pb-4">
            <div>
                <p class="font-display text-2xl text-paper">Borrow a Book</p>
                <p class="mt-1 text-xs text-paper/60">Place the book on the RFID reader tray to detect its RFID tag.</p>
            </div>
            <a href="{{ route('kiosk.dashboard') }}" class="rounded-lg border border-paper/20 px-3 py-1.5 text-xs text-paper/70 transition hover:border-paper/40 hover:text-paper">
                ← Dashboard
            </a>
        </div>

        @error('barcode')
            <div class="mt-6 flex items-center gap-2 rounded-xl bg-red-500/15 border border-red-500/30 px-4 py-3 text-sm text-red-200">
                <svg class="h-5 w-5 flex-shrink-0 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>{{ $message }}</span>
            </div>
        @enderror

        <!-- Animated RFID Scanner Graphic -->
        <div class="mt-6 flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-signal-amber/40 bg-ink-navy/40 p-8 text-center">
            <div class="relative flex h-24 w-24 items-center justify-center">
                <!-- Pulse rings -->
                <div class="absolute inset-0 animate-ping rounded-full bg-signal-amber/20"></div>
                <div class="absolute -inset-2 rounded-full border border-signal-amber/30 animate-pulse"></div>
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-signal-amber/20 border border-signal-amber/50">
                    <svg class="h-10 w-10 text-signal-amber" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                        <path d="M12 6v6"/>
                        <path d="M9 9h6"/>
                    </svg>
                </div>
            </div>
            <p class="mt-4 text-sm font-medium text-paper">RFID Reader Active</p>
            <p class="mt-1 text-xs text-paper/50">Waiting for book RFID tag to be placed on reader tray...</p>
        </div>

        <form method="POST" action="{{ route('kiosk.borrow.lookup') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="barcode" class="block text-xs font-medium text-paper/70">Book RFID Tag / Barcode</label>
                <div class="relative mt-1">
                    <input type="text" id="barcode" name="barcode" value="{{ old('barcode') }}" placeholder="Scan or enter book RFID tag..." autofocus required
                           class="block w-full rounded-xl border-paper/20 bg-ink-navy/50 px-4 py-3 pl-10 text-sm text-paper placeholder:text-paper/40 focus:border-signal-amber focus:ring-signal-amber">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-paper/40">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/></svg>
                    </div>
                </div>
            </div>

            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-signal-amber px-4 py-3 text-sm font-semibold text-ink-navy transition hover:bg-signal-amber/90 shadow-lg shadow-signal-amber/10">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Read RFID & Detect Book
            </button>
        </form>

        <div class="mt-8 rounded-xl border border-dashed border-paper/20 bg-paper/5 p-4">
            <p class="text-xs uppercase tracking-wide text-paper/40">Simulation mode — Click an RFID tag to test detection</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ([
                    'BOOK001' => 'Clean Code',
                    'BOOK002' => 'Design Patterns',
                    'BOOK003' => 'Refactoring',
                    'BOOK004' => 'Intro to Algorithms'
                ] as $simBarcode => $simTitle)
                    <form method="POST" action="{{ route('kiosk.borrow.lookup') }}" class="inline">
                        @csrf
                        <input type="hidden" name="barcode" value="{{ $simBarcode }}">
                        <button type="submit" class="flex items-center gap-1.5 rounded-lg bg-paper/10 px-3 py-2 text-xs text-paper transition hover:bg-signal-amber/20 hover:text-signal-amber">
                            <span class="font-mono font-semibold">{{ $simBarcode }}</span>
                            <span class="text-paper/50">— {{ $simTitle }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</x-kiosk-layout>
