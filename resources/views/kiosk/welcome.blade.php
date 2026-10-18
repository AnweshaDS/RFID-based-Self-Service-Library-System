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
        <p id="reader-offline" style="display:none; margin-top:24px; font-size:13px; color:#fbbf24;">
            Card reader is offline. You can still enter your card number below.
        </p>

        @if (session('status'))
            <p class="mt-6 rounded-lg bg-catalog-teal/10 px-4 py-2.5 text-sm text-catalog-teal">{{ session('status') }}</p>
        @endif
    </div>

    <div style="margin-top:24px; border-radius:16px; border:1px solid rgba(246,247,245,0.2); background:rgba(246,247,245,0.05); padding:24px;">
        <p style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:rgba(246,247,245,0.5); margin:0 0 12px 0;">Enter Student Card Number</p>
        <form id="enter-form" method="POST" action="{{ route('kiosk.enter') }}" style="display:flex; gap:12px;" onsubmit="document.getElementById('loading').style.display = 'flex';">
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

    function beep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            osc.frequency.value = 880;
            osc.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.12);
        } catch (e) {}
    }

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

            if (typeof data.cardnumber !== 'string' || data.cardnumber.trim() === '') return;

            submitting = true;
            beep();
            document.getElementById('cardnumber-input').value = data.cardnumber;
            document.getElementById('loading').style.display = 'flex';
            setTimeout(() => document.getElementById('enter-form').submit(), 150);
        } catch (e) {
            // ignore network blips and try again on the next tick
        }
    }, 700);

    async function checkReader() {
        try {
            const res = await fetch('/api/kiosk/reader-status', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            document.getElementById('reader-offline').style.display = data.online ? 'none' : 'block';
        } catch (e) {}
    }
    checkReader();
    setInterval(checkReader, 5000);
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            document.getElementById('loading').style.display = 'none';
            submitting = false;
        }
    });
</script>
    <div id="loading" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(10,18,36,0.94); align-items:center; justify-content:center; text-align:center; color:#F6F7F5;">
        <div>
            <div style="width:56px; height:56px; margin:0 auto 20px; border:5px solid rgba(246,247,245,0.2); border-top-color:#E4A72E; border-radius:50%; animation:kiosk-spin 0.9s linear infinite;"></div>
            <p style="font-size:26px; font-weight:700; margin:0;">Loading your account...</p>
            <p style="margin-top:8px; color:rgba(246,247,245,0.75); font-size:16px;">This can take a few seconds.</p>
        </div>
    </div>
    <style>@keyframes kiosk-spin { to { transform: rotate(360deg); } }</style>
    </x-kiosk-layout>