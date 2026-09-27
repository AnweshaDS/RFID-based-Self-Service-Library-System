<x-kiosk-layout :scan-line="false" max-width="max-w-lg">
    <div class="rounded-2xl bg-paper/8 p-8">
        <p class="font-display text-2xl">Confirm Return</p>
        <p class="mt-1 text-xs text-paper/60">Please review the book details below before confirming return.</p>

        @error('barcode')
            <p class="mt-6 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ $message }}</p>
        @enderror

        <div class="mt-6 rounded-xl bg-paper/10 p-5 space-y-3">
            <div>
                <p class="text-xs text-paper/50">Book Title</p>
                <p class="font-display text-lg text-paper">{{ $item['biblio']['title'] ?? $item['title'] ?? 'Untitled Book' }}</p>
            </div>

            @if (!empty($item['biblio']['author']) || !empty($item['author']))
                <div>
                    <p class="text-xs text-paper/50">Author</p>
                    <p class="text-sm text-paper/90">{{ $item['biblio']['author'] ?? $item['author'] }}</p>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-paper/10 text-xs">
                <div>
                    <p class="text-paper/50">Barcode</p>
                    <p class="font-mono text-paper/90">{{ $item['barcode'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-paper/50">Item ID</p>
                    <p class="font-mono text-paper/90">{{ $item['item_id'] ?? 'N/A' }}</p>
                </div>
            </div>

            <div class="pt-2 border-t border-paper/10 text-xs">
                <p class="text-paper/50">Returning Patron</p>
                <p class="font-medium text-paper/90">{{ $patron['name'] }} (Patron ID {{ $patron['patron_id'] }})</p>
            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <form method="POST" action="{{ route('kiosk.return.confirm.store') }}" class="flex-1">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-signal-amber px-4 py-3 text-sm font-semibold text-ink-navy transition hover:bg-signal-amber/90">
                    Confirm Return
                </button>
            </form>
            <a href="{{ route('kiosk.return.cancel') }}" class="rounded-lg border border-paper/20 px-4 py-3 text-sm font-semibold text-paper/70 transition hover:border-paper/40 hover:text-paper">
                Cancel
            </a>
        </div>
    </div>
</x-kiosk-layout>
