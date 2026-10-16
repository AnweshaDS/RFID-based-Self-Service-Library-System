@php
    $isBorrow = $mode === 'borrow';
    $heading = $isBorrow ? 'Borrow Books' : 'Return Books';
    $verb = $isBorrow ? 'Borrow' : 'Return';
    $inCart = collect($items)->pluck('barcode')->map(fn ($b) => strtolower($b))->all();
@endphp

<x-kiosk-layout :scan-line="false" max-width="max-w-2xl">
    <div class="rounded-2xl bg-paper/8 p-8">
        <div class="flex items-center justify-between">
            <div>
                <p class="font-display text-2xl">{{ $heading }}</p>
                <p class="mt-1 text-xs text-paper/60">Scan each book, check your cart, then confirm all at once.</p>
            </div>
            <a href="{{ route('kiosk.dashboard') }}" class="text-xs text-paper/50 underline hover:text-paper">Back to Dashboard</a>
        </div>

        @error('barcode')
            <p class="mt-6 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ $message }}</p>
        @enderror

        @if (session('status'))
            <p class="mt-6 rounded-lg bg-catalog-teal/20 px-4 py-2.5 text-sm font-medium text-paper">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route("kiosk.$mode.lookup") }}" style="margin-top:24px; display:flex; gap:12px;">
            @csrf
            <x-kiosk-input name="barcode" id="barcode" placeholder="Scan a book tag or type its barcode" autofocus required />
            <button type="submit" style="flex-shrink:0; background:#E4A72E; color:#16233E; border:none; border-radius:12px; padding:12px 24px; font-size:14px; font-weight:700; cursor:pointer; position:relative; z-index:999;">
                Add
            </button>
        </form>

        <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-paper/50">
            Your cart ({{ count($items) }}/{{ $max }})
        </p>
        <div class="mt-3 space-y-2">
            @forelse ($items as $item)
                <div class="flex items-center justify-between gap-3 rounded-xl bg-paper/10 px-4 py-3 text-sm">
                    <div>
                        <span class="block font-medium">{{ $item['title'] }}</span>
                        @if (!empty($item['author']))
                            <span class="block text-xs text-paper/50">by {{ $item['author'] }}</span>
                        @endif
                        <span class="block text-xs font-mono text-paper/40">Barcode: {{ $item['barcode'] }}</span>
                        @if (!empty($item['due']))
                            <span class="block text-xs text-paper/40">Due {{ \Illuminate\Support\Str::limit($item['due'], 10, '') }}</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route("kiosk.$mode.remove") }}">
                        @csrf
                        <input type="hidden" name="barcode" value="{{ $item['barcode'] }}">
                        <button type="submit" class="rounded-lg bg-red-500/15 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/25 transition">
                            Remove
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-paper/50">Your cart is empty. Scan a book to add it.</p>
            @endforelse
        </div>

        @if (count($items) > 0)
            <div style="margin-top:24px; display:flex; gap:12px;">
                <form method="POST" action="{{ route("kiosk.$mode.confirm.store") }}" style="flex:1;"
                      onsubmit="this.querySelector('button').disabled = true;">
                    @csrf
                    <input type="hidden" name="cart_token" value="{{ $token }}">
                    <button type="submit" style="width:100%; background:#E4A72E; color:#16233E; border:none; border-radius:12px; padding:14px 24px; font-size:15px; font-weight:700; cursor:pointer;">
                        Confirm {{ $verb }} ({{ count($items) }})
                    </button>
                </form>
                <form method="POST" action="{{ route("kiosk.$mode.clear") }}">
                    @csrf
                    <button type="submit" style="border:1px solid rgba(246,247,245,0.3); color:#F6F7F5; background:transparent; border-radius:12px; padding:14px 20px; font-size:15px; font-weight:600; cursor:pointer;">
                        Clear
                    </button>
                </form>
            </div>
        @endif

        @if (! $isBorrow)
            <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-paper/50">Your currently borrowed books</p>
            <div class="mt-3 space-y-2">
                @foreach ($patron['borrowed_books'] as $book)
                    @continue(empty($book['barcode']) || $book['barcode'] === 'N/A' || in_array(strtolower($book['barcode']), $inCart, true))
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-paper/10 px-4 py-3 text-sm">
                        <div>
                            <span class="block font-medium">{{ $book['title'] }}</span>
                            <span class="block text-xs font-mono text-paper/40">Barcode: {{ $book['barcode'] }}</span>
                        </div>
                        <form method="POST" action="{{ route('kiosk.return.lookup') }}">
                            @csrf
                            <input type="hidden" name="barcode" value="{{ $book['barcode'] }}">
                            <button type="submit" class="rounded-lg bg-signal-amber/20 px-3 py-1.5 text-xs font-semibold text-signal-amber hover:bg-signal-amber/30 transition">
                                Add to cart
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @include('kiosk.partials.book-scan')
</x-kiosk-layout>