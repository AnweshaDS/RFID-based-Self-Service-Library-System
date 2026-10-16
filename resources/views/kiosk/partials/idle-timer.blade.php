@php $idleTimeout = (int) config('kiosk.idle_timeout', 180); @endphp

<form id="idle-logout-form" method="POST" action="{{ route('kiosk.logout') }}" style="display:none;">@csrf</form>

<script>
(function () {
    const IDLE_MS = {{ $idleTimeout }} * 1000;
    let lastActivity = Date.now();
    let lastPing = Date.now();
    let loggingOut = false;

    function onActivity() {
        lastActivity = Date.now();

        // Keep the server's timer in step, at most once every 10 seconds
        if (Date.now() - lastPing < 10000) return;
        lastPing = Date.now();
        fetch("{{ route('kiosk.keepalive') }}", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' }
        }).catch(function () {});
    }

    ['click', 'touchstart', 'keydown', 'mousemove', 'scroll'].forEach(function (evt) {
        document.addEventListener(evt, onActivity, { passive: true });
    });

    setInterval(function () {
        if (loggingOut) return;
        if (Date.now() - lastActivity >= IDLE_MS) {
            loggingOut = true;
            document.getElementById('idle-logout-form').submit();
            setTimeout(function () { window.location.href = "{{ route('kiosk.welcome') }}"; }, 3000);
        }
    }, 1000);
})();
</script>