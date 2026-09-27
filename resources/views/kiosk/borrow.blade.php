<x-kiosk-layout :scan-line="false" max-width="max-w-lg">
    <div class="rounded-2xl bg-paper/8 p-8">
        <div class="flex items-center justify-between">
            <div>
                <p class="font-display text-2xl">Borrow a Book</p>
                <p class="mt-1 text-xs text-paper/60">Scan or enter the book barcode to proceed.</p>
            </div>
            <a href="{{ route('kiosk.dashboard') }}" class="text-xs text-paper/50 underline hover:text-paper">
                Back to Dashboard
            </a>
        </div>

        @error('barcode')
            <p class="mt-6 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('kiosk.borrow.lookup') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="barcode" class="block text-xs font-medium text-paper/70">Book Barcode</label>
                <input type="text" id="barcode" name="barcode" value="{{ old('barcode') }}" placeholder="e.g. BOOK001" autofocus required
                       class="mt-1 block w-full rounded-lg border-paper/20 bg-transparent px-3.5 py-2.5 text-sm text-paper placeholder:text-paper/40 focus:border-signal-amber focus:ring-signal-amber">
            </div>

            <button type="submit" class="w-full rounded-lg bg-signal-amber px-4 py-3 text-sm font-semibold text-ink-navy transition hover:bg-signal-amber/90">
                Lookup Book
            </button>
        </form>

        <div class="mt-8 rounded-xl border border-dashed border-paper/20 p-4">
            <p class="text-xs uppercase tracking-wide text-paper/40">Simulation mode — Test Barcodes</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['BOOK001', 'BOOK002', 'BOOK003', 'BOOK004'] as $simBarcode)
                    <form method="POST" action="{{ route('kiosk.borrow.lookup') }}" class="inline">
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
