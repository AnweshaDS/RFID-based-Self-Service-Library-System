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

                @if ($isBorrow)
            @php $spaceLeft = max(0, $limit - count($items)); @endphp
            <div style="margin-top:24px; border-radius:12px; background:rgba(246,247,245,0.06); padding:12px 16px; font-size:13px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <span>{{ $category['label'] }}: up to {{ $category['max_books'] }} books</span>
                <span>On loan {{ $onLoan }} &middot; In cart {{ count($items) }} &middot; Space left {{ $spaceLeft }}</span>
            </div>
            @if ($spaceLeft === 0)
                <p style="margin-top:8px; font-size:12px; color:#fbbf24;">
                    @if ($onLoan >= $category['max_books'])
                        You have reached your limit. Please return a book before borrowing more.
                    @else
                        Your cart is full. Remove a book to add another.
                    @endif
                </p>
            @endif
        @endif

        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-paper/50">
            Your cart ({{ count($items) }})
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
                      onsubmit="this.querySelector('button').disabled = true; document.getElementById('processing').style.display = 'flex';">
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
    <div id="processing" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(10,18,36,0.92); align-items:center; justify-content:center; text-align:center; color:#F6F7F5;">
        <div>
            <p style="font-size:24px; font-weight:700; margin:0;">Processing your request...</p>
            <p style="margin-top:8px; color:rgba(246,247,245,0.7);">Please wait, this can take a few seconds.</p>
        </div>
    </div>

    @include('kiosk.partials.book-scan')
</x-kiosk-layout>