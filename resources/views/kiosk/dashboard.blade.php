<x-kiosk-layout>
    <div class="rounded-2xl bg-paper/5 p-8 backdrop-blur">
        <p class="text-sm text-paper/60">Welcome back,</p>
        <p class="font-display text-3xl">{{ $patron['name'] }}</p>

        <div class="mt-6 space-y-3">
            @forelse ($patron['borrowed_books'] as $book)
                <div class="flex items-center justify-between rounded-lg border border-paper/10 px-4 py-3 text-sm">
                    <span>{{ $book['title'] }}</span>
                    <span class="text-paper/50">Due {{ \Illuminate\Support\Carbon::parse($book['due'])->format('d M Y') }}</span>
                </div>
            @empty
                <p class="text-sm text-paper/50">No books currently borrowed.</p>
            @endforelse
        </div>

        <div class="mt-8 grid grid-cols-2 gap-3">
            <button class="rounded-lg bg-signal-amber px-4 py-3 text-sm font-semibold text-ink-navy transition hover:bg-signal-amber/90">
                Borrow a book
            </button>
            <button class="rounded-lg border border-paper/20 px-4 py-3 text-sm font-semibold text-paper transition hover:border-catalog-teal hover:text-catalog-teal">
                Return a book
            </button>
        </div>

        <form method="POST" action="{{ route('kiosk.logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="w-full text-center text-sm text-paper/50 underline hover:text-paper">
                Log out
            </button>
        </form>
    </div>

    @push('scripts')
        <script>
            (function () {
                const timeoutMs = 45_000; // idle timeout on the kiosk screen
                let timer;

                function resetTimer() {
                    clearTimeout(timer);
                    timer = setTimeout(logout, timeoutMs);
                }

                function logout() {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = "{{ route('kiosk.logout') }}";
                    form.innerHTML = '@csrf';
                    document.body.appendChild(form);
                    form.submit();
                }

                ['click', 'touchstart', 'keydown'].forEach((evt) =>
                    document.addEventListener(evt, resetTimer)
                );

                resetTimer();
            })();
        </script>
    @endpush
</x-kiosk-layout>