<x-kiosk-layout :scan-line="false" max-width="max-w-2xl">
    <div class="rounded-2xl bg-paper/8 p-8">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm text-paper/60">Welcome back,</p>
                <p class="font-display text-3xl">{{ $patron['name'] }}</p>
                <p class="mt-1 text-xs text-paper/40">Patron ID {{ $patron['patron_id'] }}</p>
            </div>
            <span class="flex items-center gap-1.5 rounded-full bg-catalog-teal/25 px-3 py-1 text-xs font-medium text-paper">
                <span class="h-1.5 w-1.5 rounded-full bg-signal-amber"></span>
                {{ $patron['status'] }}
            </span>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="flex items-center gap-3 rounded-xl bg-paper/10 px-4 py-3">
                <svg class="h-5 w-5 flex-shrink-0 text-signal-amber" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                <div>
                    <p class="font-display text-xl leading-none">{{ count($patron['borrowed_books']) }}</p>
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
                    <p class="mt-1 text-xs text-paper/50">Account</p>
                </div>
            </div>
        </div>

        <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-paper/50">Current loans</p>
        <div class="mt-3 space-y-2">
            @forelse ($patron['borrowed_books'] as $book)
                @php
                    $due = \Illuminate\Support\Carbon::parse($book['due']);
                    $isDueSoon = now()->diffInDays($due, false) <= 3;
                @endphp
                <div class="flex items-center gap-3 rounded-xl bg-paper/10 px-4 py-3 text-sm">
                    <svg class="h-4 w-4 flex-shrink-0 text-paper/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <span class="flex-1">{{ $book['title'] }}</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $isDueSoon ? 'bg-signal-amber/15 text-signal-amber' : 'text-paper/50' }}">
                        Due {{ $due->format('d M Y') }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-paper/50">No books currently borrowed.</p>
            @endforelse
        </div>

        <div class="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <button class="flex items-center justify-center gap-2 rounded-lg bg-signal-amber px-4 py-3 text-sm font-semibold text-ink-navy transition hover:bg-signal-amber/90">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Borrow a book
            </button>
            <button class="flex items-center justify-center gap-2 rounded-lg border border-paper/20 px-4 py-3 text-sm font-semibold text-paper transition hover:border-catalog-teal hover:text-catalog-teal">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
                Return a book
            </button>
        </div>

        @if (! empty($patron['recent_activity']))
            <p class="mt-8 text-xs font-semibold uppercase tracking-wide text-paper/70">Recent activity</p>
            <div class="mt-3 space-y-1.5">
                @foreach ($patron['recent_activity'] as $entry)
                    <div class="flex items-center gap-2 text-xs text-paper/90">
                        <svg class="h-3.5 w-3.5 flex-shrink-0 text-paper/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span class="flex-1">{{ $entry['action'] }} - {{ $entry['title'] }}</span>
                        <span class="text-paper/70">{{ \Illuminate\Support\Carbon::parse($entry['date'])->format('d M Y') }}</span>
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