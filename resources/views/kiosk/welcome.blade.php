<x-kiosk-layout>
    <div class="rounded-2xl bg-paper/5 p-10 text-center backdrop-blur">
        <p class="font-display text-4xl">Welcome</p>
        <p class="mt-3 text-paper/70">Tap your card or enter your student card number to begin.</p>

        <div class="mx-auto mt-8 flex h-28 w-28 items-center justify-center rounded-full border-2 border-signal-amber/60">
            <svg class="h-12 w-12 text-signal-amber" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="7" y="9" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                <path d="M2 12C2 6.48 6.48 2 12 2s10 4.48 10 10-4.48 10-10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="2 4"/>
            </svg>
        </div>

        @error('cardnumber')
            <p class="mt-6 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">{{ $message }}</p>
        @enderror

        <p id="scan-error" class="mt-6 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300" style="display:none;"></p>

        @if (session('status'))
            <p class="mt-6 rounded-lg bg-catalog-teal/10 px-4 py-2.5 text-sm text-catalog-teal">{{ session('status') }}</p>
        @endif
    </div>

    <div style="margin-top:24px; border-radius:16px; border:1px solid rgba(246,247,245,0.2); background:rgba(246,247,245,0.05); padding:24px;">
        <p style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:rgba(246,247,245,0.5); margin:0 0 12px 0;">Enter Student Card Number</p>
        <form id="enter-form" method="POST" action="{{ route('kiosk.enter') }}" style="display:flex; gap:12px;">
            @csrf
                                    <x-kiosk-input name="cardnumber" id="cardnumber-input" placeholder="e.g. STU001" />
            <button
                type="submit"
                style="flex-shrink:0; background:#E4A72E; color:#16233E; border:none; border-radius:12px; padding:12px 24px; font-size:14px; font-weight:700; font-family:inherit; cursor:pointer; position:relative; z-index:999;"
            >
                Enter
            </button>
        </form>
    </div>
<script>
    let submitting = false;

    setInterval(async () => {
        if (submitting) return;

        try {
            const res = await fetch('/api/kiosk/poll?type=patron', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;

            const data = await res.json();
            if (!data) return;

            if (data.error) {
                const el = document.getElementById('scan-error');
                el.textContent = data.error;
                el.style.display = 'block';
                setTimeout(() => { el.style.display = 'none'; }, 4000);
                return;
            }

            // Only log in when a real card number arrived
            if (typeof data.cardnumber !== 'string' || data.cardnumber.trim() === '') return;

            submitting = true;
            document.getElementById('cardnumber-input').value = data.cardnumber;
            document.getElementById('enter-form').submit();
        } catch (e) {
            // ignore network blips and try again on the next tick
        }
    }, 700);
</script>

    </x-kiosk-layout>