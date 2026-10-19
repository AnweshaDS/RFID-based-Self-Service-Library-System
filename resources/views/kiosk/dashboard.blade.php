@include('kiosk.partials.scan-notice')
<x-kiosk-layout :scan-line="false" max-width="max-w-2xl">
    @php $category = \App\Support\PatronCategory::for($patron['category_code'] ?? null); @endphp
    <div class="rounded-2xl bg-paper/8 p-8">
        @if (session('renewed'))
            <p class="mb-6 rounded-lg bg-catalog-teal/20 px-4 py-2.5 text-sm font-medium text-paper">{{ session('renewed') }}</p>
        @endif
        @error('renew')
            <p class="mb-6 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ $message }}</p>
        @enderror
        @if (($patron['status'] ?? 'Active') !== 'Active')
            <p class="mb-6 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">
                Your account is {{ strtolower($patron['status']) }}. Please contact the library desk.
            </p>
        @endif
    
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm text-paper/60">Welcome back,</p>
                <p class="font-display text-3xl">{{ $patron['name'] }}</p>
                                <p class="mt-1 text-xs text-paper/40">Patron ID {{ $patron['patron_id'] }}</p>
                <p class="mt-1 text-sm font-mono text-paper/70">Card No. {{ $patron['cardnumber'] ?? '' }}</p>
                                <p class="mt-1 text-sm text-paper/70">{{ $category['label'] }} &middot; can borrow up to {{ $category['max_books'] }} {{ \Illuminate\Support\Str::plural('book', $category['max_books']) }} at once</p>
            </div>
            <span class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium {{ ($patron['status'] ?? 'Active') === 'Active' ? 'bg-catalog-teal/25 text-paper' : 'bg-red-500/20 text-red-300' }}">
                <span class="h-1.5 w-1.5 rounded-full bg-signal-amber"></span>
                {{ $patron['status'] }}
            </span>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center gap-3 rounded-xl bg-paper/10 px-4 py-3">
                <svg class="h-5 w-5 flex-shrink-0 text-signal-amber" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                <div>
                    <p class="font-display text-xl leading-none">{{ count($patron['borrowed_books']) }}<span class="text-sm text-paper/50"> / {{ $category['max_books'] }}</span></p>
                    <p class="mt-1 text-xs text-paper/50">Borrowed</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-paper/10 px-4 py-3">
                <svg class="h-5 w-5 flex-shrink-0 {{ $dueSoonCount > 0 ? 'text-signal-amber' : 'text-paper/40' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <div>
                    <p class="font-display text-xl leading-none {{ $dueSoonCount > 0 ? 'text-signal-amber' : '' }}">{{ $dueSoonCount }}</p>
                    <p class="mt-1 text-xs text-paper/50">Due soon</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-paper/10 px-4 py-3">
                <svg class="h-5 w-5 flex-shrink-0 text-catalog-teal" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div>
                    <p class="font-display text-xl leading-none">{{ $patron['status'] }}</p>
                            <p class="mt-1 text-xs text-paper/50">Account @if (! is_null($patron['balance'] ?? null))&middot; Fines {{ number_format($patron['balance'], 2) }} {{ config('kiosk.currency') }}@endif</p>
                </div>
            </div>
        </div>

        <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-paper/50">Current loans</p>
        @if (($overdueCount ?? 0) > 0)
            <p class="mt-3 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">
                You have {{ $overdueCount }} overdue {{ \Illuminate\Support\Str::plural('book', $overdueCount) }}. Please return {{ $overdueCount === 1 ? 'it' : 'them' }} as soon as possible.
            </p>
        @endif
        <div class="mt-3 space-y-2">
            @forelse ($patron['borrowed_books'] as $book)
                                @php
                    $due = !empty($book['due']) ? \Illuminate\Support\Carbon::parse($book['due']) : null;
                   $isOverdue = $due && $due->copy()->endOfDay()->isPast();
                    $isDueSoon = $due && ! $isOverdue && $due->lte(now()->addDays(3));
                @endphp
                <div class="flex items-center gap-3 rounded-xl bg-paper/10 px-4 py-3 text-sm">
                    <svg class="h-4 w-4 flex-shrink-0 text-paper/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <div class="flex-1">
                        <span class="block font-medium">{{ $book['title'] }}</span>
                        @if (!empty($book['author']))
                            <span class="block text-xs text-paper/50">by {{ $book['author'] }}</span>
                        @endif
                        @if (!empty($book['barcode']))
                            <span class="block text-xs font-mono text-paper/40">Barcode: {{ $book['barcode'] }}</span>
                        @endif
                    </div>
                    @if ($due)
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $isOverdue ? 'bg-red-500/20 text-red-300' : ($isDueSoon ? 'bg-signal-amber/15 text-signal-amber' : 'text-paper/50') }}">
                            {{ $isOverdue ? 'Overdue since' : 'Due' }} {{ $due->format('d M Y') }}
                        </span>
                    @endif
                    @if (!empty($book['barcode']) && $book['barcode'] !== 'N/A')
                        <form method="POST" action="{{ route('kiosk.renew') }}" onsubmit="this.querySelector('button').disabled = true;">
                            @csrf
                            <input type="hidden" name="barcode" value="{{ $book['barcode'] }}">
                            <button type="submit" class="rounded-lg bg-catalog-teal/20 px-3 py-1.5 text-xs font-semibold text-catalog-teal hover:bg-catalog-teal/30 transition">
                                Renew
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm text-paper/50">No books currently borrowed.</p>
            @endforelse
        </div>

        <div class="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <a href="{{ route('kiosk.borrow') }}" class="flex items-center justify-center gap-2 rounded-lg bg-signal-amber px-4 py-3 text-sm font-semibold text-ink-navy transition hover:bg-signal-amber/90">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Borrow a book
            </a>
            <a href="{{ route('kiosk.return') }}" class="flex items-center justify-center gap-2 rounded-lg border border-paper/20 px-4 py-3 text-sm font-semibold text-paper transition hover:border-catalog-teal hover:text-catalog-teal">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
                Return a book
            </a>
        </div>

                @if (isset($recentActivities) && count($recentActivities) > 0)
            <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-paper/70">Recent activity</p>
            <div class="mt-3 space-y-2">
                @foreach ($recentActivities as $activity)
                    @php
                        $label = match ($activity->action) {
                            'borrow' => 'Borrowed',
                            'return' => 'Returned',
                            'renew' => 'Renewed',
                            default => ucfirst($activity->action),
                        };
                        $chip = match ($activity->action) {
                            'borrow' => 'bg-signal-amber/20 text-signal-amber',
                            'return' => 'bg-catalog-teal/25 text-catalog-teal',
                            default => 'bg-paper/15 text-paper',
                        };
                        $hasTitle = str_contains((string) $activity->message, ': ');
                        $title = \Illuminate\Support\Str::after((string) $activity->message, ': ');
                        $loanTitle = collect($patron['borrowed_books'])->first(fn ($b) => strtolower((string) ($b['barcode'] ?? '')) === strtolower((string) $activity->barcode))['title'] ?? null;
                    @endphp
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-paper/10 px-4 py-3 text-sm">
                        <div class="min-w-0 flex-1">
                                                        <span class="block truncate font-medium">{{ $hasTitle ? $title : ($loanTitle ?? 'Book ' . strtolower($label)) }}</span>
                            @if ($activity->barcode)
                                <span class="block text-xs font-mono text-paper/50">Barcode: {{ $activity->barcode }}</span>
                            @endif
                        </div>
                        <div class="flex flex-shrink-0 flex-col items-end gap-1">
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider {{ $chip }}">{{ $label }}</span>
                            <span class="text-xs text-paper/50">{{ $activity->created_at ? $activity->created_at->format('d M, h:i A') : '' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('kiosk.logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="w-full text-center text-sm text-paper/50 underline hover:text-paper">
                Log out
            </button>
        </form>
    </div>
</x-kiosk-layout>