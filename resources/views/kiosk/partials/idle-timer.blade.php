@php $idleTimeout = (int) config('kiosk.idle_timeout', 180); @endphp

<form id="idle-logout-form" method="POST" action="{{ route('kiosk.logout') }}" style="display:none;">@csrf</form>
<form method="POST" action="{{ route('kiosk.logout') }}" style="position:fixed; top:16px; right:16px; z-index:9999;">
    @csrf
    <button type="submit" style="background:rgba(10,18,36,0.85); color:#F6F7F5; border:1px solid rgba(246,247,245,0.3); border-radius:10px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer;">
        Log out
    </button>
</form>
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