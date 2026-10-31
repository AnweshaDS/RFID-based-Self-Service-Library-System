@php
    $isBorrow = $mode === 'borrow';

    $t = $isBorrow
        ? [
            'heading' => 'Borrow Books',
            'intro' => 'Place one book at a time on the reader, or type its barcode. Check your list, then confirm.',
            'list' => 'Books to borrow',
            'empty' => 'No books added yet. Place a book on the reader to add it.',
            'confirm' => 'Confirm Borrowing',
            'processing' => 'Processing your borrowing...',
        ]
        : [
            'heading' => 'Return Books',
            'intro' => 'Place one book at a time on the reader, or choose it from your borrowed books below. Check your list, then confirm.',
            'list' => 'Books to return',
            'empty' => 'No books added yet. Place a book on the reader, or choose one from your borrowed books below.',
            'confirm' => 'Confirm Return',
            'processing' => 'Processing your return...',
        ];

    $inCart = collect($items)->pluck('barcode')->map(fn ($b) => strtolower($b))->all();
@endphp

<x-kiosk-layout :scan-line="false" max-width="max-w-2xl">
    <div class="rounded-2xl bg-paper/8 p-8">
        <x-kiosk-page-header :title="$t['heading']" :subtitle="$t['intro']" :back="route('kiosk.dashboard')" />

        @error('barcode')
            <p class="mb-4 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ $message }}</p>
        @enderror

        @if (session('status'))
            <p class="mb-4 rounded-lg bg-catalog-teal/20 px-4 py-2.5 text-sm font-medium text-paper">{{ session('status') }}</p>
        @endif

                @if (! $isBorrow && empty($patron['borrowed_books']) && count($items) === 0)
            <p style="margin:40px 0 24px; text-align:center; font-size:22px; font-weight:600; color:#ffffff;">
                You have no borrowed books.
            </p>
        @else
        @if ($isBorrow)
            @php $spaceLeft = max(0, $limit - count($items)); @endphp
            <div style="border-radius:12px; background:rgba(228,167,46,0.12); border:1px solid rgba(228,167,46,0.35); padding:14px 18px;">
                <p style="margin:0; font-size:17px; font-weight:700; color:#ffffff;">
                    You can borrow up to {{ $category['max_books'] }} {{ \Illuminate\Support\Str::plural('book', $category['max_books']) }} at once
                </p>
                <p style="margin:6px 0 0; font-size:14px; color:rgba(246,247,245,0.9);">
                    {{ $category['label'] }} &middot; {{ $onLoan }} on loan &middot; {{ count($items) }} in your list &middot; {{ $spaceLeft }} more can be added
                </p>
                @if ($spaceLeft === 0)
                    <p style="margin:8px 0 0; font-size:14px; font-weight:600; color:#fbbf24;">
                        @if ($onLoan >= $category['max_books'])
                            You have reached your limit. Please return a book before borrowing more.
                        @else
                            Your list is full. Remove a book to add another.
                        @endif
                    </p>
                @endif
            </div>
        @endif

                       @php
            $showField = count($items) === 0;
            $canAdd = $isBorrow
                ? true
                : collect($patron['borrowed_books'])->contains(
                    fn ($b) => ! empty($b['barcode']) && $b['barcode'] !== 'N/A' && ! in_array(strtolower($b['barcode']), $inCart, true)
                );
        @endphp
        @if ($canAdd)
        <form method="POST" action="{{ route("kiosk.$mode.lookup") }}" style="margin-top:20px;">
            @csrf
                        <div id="scan-field">
                <x-kiosk-input name="barcode" id="barcode" placeholder="Scan the book tag or type its barcode" autofocus required />
            </div>
            
        </form>
        @endif
        <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-paper/50">
            {{ $t['list'] }} ({{ count($items) }})
        </p>
        <div class="mt-3 space-y-2">
            @forelse ($items as $item)
                @php
                    $due = ! empty($item['due']) ? rescue(fn () => \Illuminate\Support\Carbon::parse($item['due']), null, false) : null;
                    $isOverdue = $due && $due->copy()->endOfDay()->isPast();
                @endphp
                <div class="kiosk-row">
                    <div class="kiosk-row-main">
                        <div class="kiosk-row-title">{{ $item['title'] }}</div>
                        <div class="kiosk-row-meta">
                            @if (!empty($item['author']))
                                by {{ $item['author'] }} &middot;
                            @endif
                            Barcode: {{ $item['barcode'] }}
                        </div>
                        @if ($due)
                            <div class="kiosk-row-meta" style="{{ $isOverdue ? 'color:#fecaca;' : '' }}">
                                {{ $isOverdue ? 'Overdue since' : 'Due' }} {{ $due->format('d M Y') }}
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route("kiosk.$mode.remove") }}">
                        @csrf
                        <input type="hidden" name="barcode" value="{{ $item['barcode'] }}">
                        <button type="submit" class="kiosk-btn bg-red-500/15 text-red-300 hover:bg-red-500/25 transition">Remove</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-paper/50">{{ $t['empty'] }}</p>
            @endforelse
        </div>

                @if (count($items) > 0)
                        @if (! $isBorrow)
                {{-- No identity photo is needed for returns --}}
            @elseif (! $verified)
                @include('kiosk.partials.verification-capture', ['action' => $mode, 'confirmFormId' => 'confirmForm'])
            @else                <div class="rounded-xl bg-catalog-teal/15 border border-catalog-teal/30 px-4 py-3 text-sm text-catalog-teal font-medium mt-4">
                    ✓ Identity verification photo captured for this visit.
                </div>
            @endif

            <div style="margin-top:24px; display:flex; gap:12px;">
                <form method="POST" action="{{ route("kiosk.$mode.confirm.store") }}" id="confirmForm" style="flex:1;"
                      onsubmit="this.querySelector('button').disabled = true; document.getElementById('processing').style.display = 'flex';">
                    @csrf
                    <input type="hidden" name="cart_token" value="{{ $token }}">
                                       <button type="button" id="confirmSubmitBtn" class="kiosk-btn" onclick="document.getElementById('confirmModal').style.display = 'flex';" @unless($verified || ! $isBorrow) disabled @endunless
                            style="width:100%; background:#E4A72E; color:#16233E; padding:14px 24px; font-size:16px; opacity:{{ ($verified || ! $isBorrow) ? '1' : '0.5' }};">
                        {{ $t['confirm'] }} ({{ count($items) }})
                    </button>
                </form>
                <form method="POST" action="{{ route("kiosk.$mode.clear") }}">
                    @csrf
                    <button type="submit" class="kiosk-btn" style="border:1px solid rgba(246,247,245,0.35); color:#F6F7F5; background:transparent; padding:14px 22px; font-size:16px;">
                        Clear list
                    </button>
                </form>
            </div>
        @endif
        
        @if (! $isBorrow)
                        <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-paper/50">Remaining Borrowed Books</p>
            <div class="mt-3 space-y-2">
                                @php
                    $returnable = collect($patron['borrowed_books'])->reject(
                        fn ($b) => empty($b['barcode']) || $b['barcode'] === 'N/A' || in_array(strtolower($b['barcode']), $inCart, true)
                    );
                @endphp
                @forelse ($returnable as $book)
                    <div class="kiosk-row">
                        <div class="kiosk-row-main">
                            <div class="kiosk-row-title">{{ $book['title'] }}</div>
                            <div class="kiosk-row-meta">Barcode: {{ $book['barcode'] }}</div>
                        </div>
                        <form method="POST" action="{{ route('kiosk.return.lookup') }}">
                            @csrf
                            <input type="hidden" name="barcode" value="{{ $book['barcode'] }}">
                            <button type="submit" class="kiosk-btn bg-signal-amber/20 text-signal-amber hover:bg-signal-amber/30 transition">Add to return list</button>
                        </form>
                    </div>
                                @empty
                    <p class="text-sm text-paper/50">No books to show.</p>
                @endforelse
            </div>
        @endif
        @endif
    </div>

        <div id="confirmModal" style="display:none; position:fixed; inset:0; z-index:99998; background:rgba(10,18,36,0.85); align-items:center; justify-content:center; padding:24px;">
        <div style="position:relative; width:100%; max-width:440px; background:#16233E; border:1px solid rgba(246,247,245,0.25); border-radius:20px; padding:40px 28px 28px; text-align:center; color:#F6F7F5;">
            <button type="button" aria-label="Close" onclick="closeConfirmModal()"
                    style="position:absolute; top:10px; right:10px; width:48px; height:48px; border:none; border-radius:12px; background:transparent; color:#F6F7F5; font-size:32px; line-height:1; cursor:pointer;">&times;</button>
            <p style="margin:0; font-size:24px; font-weight:700; color:#ffffff;">
                {{ $isBorrow ? 'Ready to borrow' : 'Ready to return' }} {{ count($items) }} {{ \Illuminate\Support\Str::plural('book', count($items)) }}?
            </p>
            <p style="margin:12px 0 0; font-size:16px; color:rgba(246,247,245,0.85);">
                Confirm to complete the transaction, or Cancel to make changes
            </p>
            <div style="margin-top:28px; display:flex; gap:12px;">
                <button type="button" onclick="closeConfirmModal()" class="kiosk-btn"
                        style="flex:1; border:1px solid rgba(246,247,245,0.35); color:#F6F7F5; background:transparent; padding:14px 22px; font-size:16px;">Cancel</button>
                <button type="button" onclick="submitConfirm()" class="kiosk-btn"
                        style="flex:1; background:#E4A72E; color:#16233E; padding:14px 22px; font-size:16px; font-weight:700;">Confirm</button>
            </div>
        </div>
    </div>
    <script>
        function closeConfirmModal() { document.getElementById('confirmModal').style.display = 'none'; }
        function submitConfirm() {
            closeConfirmModal();
            document.getElementById('confirmForm').requestSubmit();
        }
    </script>

    <div id="processing" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(10,18,36,0.92); align-items:center; justify-content:center; text-align:center; color:#F6F7F5;">
        <div>
            <p style="font-size:24px; font-weight:700; margin:0;">{{ $t['processing'] }}</p>
            <p style="margin-top:8px; color:rgba(246,247,245,0.75);">Please wait, this can take a few seconds.</p>
        </div>
    </div>

    @include('kiosk.partials.book-scan')
</x-kiosk-layout>