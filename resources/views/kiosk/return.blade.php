<x-kiosk-layout :scan-line="false" max-width="max-w-2xl">
    <div class="rounded-2xl bg-paper/8 p-8">
        <div class="flex items-center justify-between">
            <div>
                <p class="font-display text-2xl">Return a Book</p>
                <p class="mt-1 text-xs text-paper/60">Scan or select a borrowed book to return.</p>
            </div>
            <a href="{{ route('kiosk.dashboard') }}" class="text-xs text-paper/50 underline hover:text-paper">
                Back to Dashboard
            </a>
        </div>

        @error('barcode')
            <p class="mt-6 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ $message }}</p>
        @enderror

        @if (session('status'))
            <p class="mt-6 rounded-lg bg-catalog-teal/20 px-4 py-2.5 text-sm font-medium text-paper">{{ session('status') }}</p>
        @endif

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-paper/50">Your Currently Borrowed Books</p>
        <div class="mt-3 space-y-2">
            @forelse ($patron['borrowed_books'] as $book)
                <div class="flex items-center justify-between gap-3 rounded-xl bg-paper/10 px-4 py-3 text-sm">
                    <div class="flex items-center gap-3">
                        <svg class="h-4 w-4 flex-shrink-0 text-paper/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        <div>
                            <span class="block font-medium">{{ $book['title'] }}</span>
                            @if (!empty($book['author']))
                                <span class="block text-xs text-paper/50">by {{ $book['author'] }}</span>
                            @endif
                            @if (!empty($book['barcode']))
                                <span class="block text-xs font-mono text-paper/40">Barcode: {{ $book['barcode'] }}</span>
                            @endif
                        </div>
                    </div>
                    @if (!empty($book['barcode']))
                        <form method="POST" action="{{ route('kiosk.return.lookup') }}">
                            @csrf
                            <input type="hidden" name="barcode" value="{{ $book['barcode'] }}">
                            <button type="submit" class="rounded-lg bg-signal-amber/20 px-3 py-1.5 text-xs font-semibold text-signal-amber hover:bg-signal-amber/30 transition">
                                Select to Return
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-paper/50">No books currently borrowed on your account.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('kiosk.return.lookup') }}" class="mt-8 space-y-4">
            @csrf
            <div>
                <label for="barcode" class="block text-xs font-medium text-paper/70">Or Enter Book Barcode</label>
                <input type="text" id="barcode" name="barcode" value="{{ old('barcode') }}" placeholder="e.g. BOOK001" autofocus required
                       class="mt-1 block w-full rounded-lg border-paper/20 bg-transparent px-3.5 py-2.5 text-sm text-paper placeholder:text-paper/40 focus:border-signal-amber focus:ring-signal-amber">
            </div>

            <button type="submit" class="w-full rounded-lg bg-signal-amber px-4 py-3 text-sm font-semibold text-ink-navy transition hover:bg-signal-amber/90">
                Lookup Book to Return
            </button>
        </form>

        <div class="mt-8 rounded-xl border border-dashed border-paper/20 p-4">
            <p class="text-xs uppercase tracking-wide text-paper/40">Simulation mode — Test Barcodes</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['BOOK001', 'BOOK002', 'BOOK003', 'BOOK004'] as $simBarcode)
                    <form method="POST" action="{{ route('kiosk.return.lookup') }}" class="inline">
                        @csrf
                        <input type="hidden" name="barcode" value="{{ $simBarcode }}">
                        <button type="submit" class="rounded-md bg-paper/10 px-3 py-1.5 text-xs text-paper hover:bg-paper/20">
                            {{ $simBarcode }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</x-kiosk-layout>
