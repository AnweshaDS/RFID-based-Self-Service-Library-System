<script>
(function () {
    let toast;

    function show(msg) {
        if (!toast) {
            toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;background:rgba(239,68,68,0.95);color:#fff;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:600;';
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.style.display = 'block';
        clearTimeout(toast._t);
        toast._t = setTimeout(function () { toast.style.display = 'none'; }, 4000);
    }

    setInterval(async function () {
        try {
            const res = await fetch('/api/kiosk/poll?type=none', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (data && data.error) show(data.error);
        } catch (e) {}
    }, 1000);
})();
</script>