<x-kiosk-layout :scan-line="false" max-width="max-w-lg">
    <div class="rounded-2xl bg-paper/8 p-8 backdrop-blur shadow-2xl border border-paper/10">
        <!-- Detection Header -->
        <div class="flex items-center gap-2 rounded-xl bg-catalog-teal/20 border border-catalog-teal/40 px-3.5 py-2 text-xs font-semibold text-catalog-teal">
            <svg class="h-4 w-4 flex-shrink-0 text-catalog-teal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            Book RFID Detected Successfully
        </div>

        <p class="mt-5 font-display text-2xl text-paper">Book Details</p>
        <p class="mt-1 text-xs text-paper/60">Please review the detected book information before confirming the loan.</p>

        @error('barcode')
            <p class="mt-4 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ $message }}</p>
        @enderror

        <!-- Book Details Card -->
        <div class="mt-6 rounded-2xl bg-paper/10 p-6 space-y-4 border border-paper/10">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-signal-amber/15 text-signal-amber border border-signal-amber/30">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <span class="inline-block rounded-full bg-signal-amber/20 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-signal-amber">Ready to Borrow</span>
                    <h3 class="mt-1 font-display text-xl leading-tight text-paper truncate">{{ $item['biblio']['title'] ?? $item['title'] ?? 'Unknown title' }}</h3>
                    @if (!empty($item['biblio']['author']) || !empty($item['author']))
                        <p class="mt-1 text-sm text-paper/70">by {{ $item['biblio']['author'] ?? $item['author'] }}</p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-3 border-t border-paper/10 text-xs">
                <div class="rounded-xl bg-paper/5 p-3">
                    <p class="text-paper/50">RFID Tag / Barcode</p>
                    <p class="mt-0.5 font-mono font-semibold text-paper/90">{{ $item['barcode'] ?? 'N/A' }}</p>
                </div>
                <div class="rounded-xl bg-paper/5 p-3">
                    <p class="text-paper/50">Item ID</p>
                    <p class="mt-0.5 font-mono font-semibold text-paper/90">#{{ $item['item_id'] ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <div class="mt-6 rounded-xl bg-paper/5 px-4 py-3 text-xs text-paper/70 flex items-center gap-2">
            <svg class="h-4 w-4 text-signal-amber flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>Borrowing to account: <strong class="text-paper">{{ $patron['name'] }}</strong></span>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 flex flex-col sm:flex-row items-center gap-3">
            <form method="POST" action="{{ route('kiosk.borrow.confirm.store') }}" class="w-full sm:flex-1">
                @csrf
                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-signal-amber px-4 py-3 text-sm font-semibold text-ink-navy transition hover:bg-signal-amber/90 shadow-lg shadow-signal-amber/10">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Confirm Borrow
                </button>
            </form>
            <a href="{{ route('kiosk.borrow.cancel') }}" class="w-full sm:w-auto text-center rounded-xl border border-paper/20 px-4 py-3 text-sm font-semibold text-paper/70 transition hover:border-paper/40 hover:text-paper">
                Scan Another Book
            </a>
        </div>
    </div>
</x-kiosk-layout>
