<script>
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
(function () {
    const input = document.getElementById('barcode');
    if (!input) return;
    const form = input.closest('form');
    let submitting = false;

    function showError(msg) {
        let el = document.getElementById('scan-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'scan-toast';
            el.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;background:rgba(239,68,68,0.95);color:#fff;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:600;';
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.style.display = 'none'; }, 4000);
    }

    setInterval(async function () {
        if (submitting) return;
        // Only accept scans while the scan field is showing (after "Borrow/Return another book")
        const field = document.getElementById('scan-field');
        if (field && field.style.display === 'none') return;
        try {
            const res = await fetch('/api/kiosk/poll?type=book', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;

            const data = await res.json();
            if (!data) return;

            if (data.error) { showError(data.error); return; }

            // Only act on a real barcode
            if (typeof data.barcode !== 'string' || data.barcode.trim() === '') return;

                        submitting = true;
            beep();
            input.value = data.barcode;
            setTimeout(function () { form.submit(); }, 150);
        } catch (e) {
            // ignore network blips, try again next tick
        }
    }, 700);
})();
</script>
