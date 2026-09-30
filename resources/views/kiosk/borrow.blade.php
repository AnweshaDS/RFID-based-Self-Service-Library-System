<x-kiosk-layout :scan-line="true" max-width="max-w-xl">
    <div class="rounded-2xl bg-paper/8 p-8 backdrop-blur shadow-2xl border border-paper/10 text-center relative overflow-hidden">
        <div class="flex items-center justify-between border-b border-paper/10 pb-4 mb-6">
            <h2 class="font-display text-2xl text-paper">Borrow a Book</h2>
            <a href="{{ route('kiosk.dashboard') }}" class="rounded-lg border border-paper/20 px-3 py-1.5 text-xs text-paper/70 transition hover:border-paper/40 hover:text-paper">
                ← Dashboard
            </a>
        </div>

        @error('barcode')
            <div class="mb-6 flex items-center gap-2 rounded-xl bg-red-500/15 border border-red-500/30 px-4 py-3 text-sm text-red-200">
                <svg class="h-5 w-5 flex-shrink-0 text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>{{ $message }}</span>
            </div>
        @enderror

        <div class="py-12 flex flex-col items-center justify-center relative z-0">
            <!-- RFID scanning animation -->
            <div class="relative w-32 h-32 mb-8 pointer-events-none">
                <div class="absolute inset-0 rounded-full border-4 border-signal-amber/30 animate-ping"></div>
                <div class="absolute inset-2 rounded-full border-4 border-signal-amber/60 animate-pulse"></div>
                <div class="absolute inset-4 rounded-full bg-signal-amber flex items-center justify-center text-ink-navy shadow-lg shadow-signal-amber/50">
                    <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                        <path d="M12 6v6"/>
                        <path d="M9 9h6"/>
                    </svg>
                </div>
            </div>

            <p class="text-lg font-medium text-paper">Please place the book on the RFID scanner tray</p>
            <p class="mt-2 text-sm text-paper/50">The system will automatically detect the book and proceed.</p>
        </div>

        <form method="POST" action="{{ route('kiosk.borrow.lookup') }}" id="rfid-form" class="mt-8 w-full max-w-sm mx-auto relative z-50">
            @csrf
            <div class="flex gap-2 bg-ink-navy/80 p-2 rounded-2xl shadow-xl border border-paper/10">
                <input type="text" id="barcode" name="barcode" value="{{ old('barcode') }}" autofocus required autocomplete="off" 
                       placeholder="Enter RFID tag here..."
                       style="color: #ffffff; background-color: rgba(255,255,255,0.1);"
                       class="block w-full rounded-xl border-0 px-4 py-3 text-lg font-bold placeholder:text-paper/40 focus:ring-2 focus:ring-signal-amber shadow-inner">
                <button type="submit" class="shrink-0 flex items-center justify-center rounded-xl bg-signal-amber px-6 py-3 text-sm font-bold text-ink-navy transition hover:bg-signal-amber/80 shadow-lg shadow-signal-amber/20">
                    <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Scan
                </button>
            </div>
        </form>
    </div>
</x-kiosk-layout>
