<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Printing Receipt...</title>
    <style>
        body { margin: 0; background: #16233E; color: #F6F7F5; font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; }
        .msg { margin-bottom: 16px; font-size: 14px; }
        .actions { margin-top: 16px; display: flex; gap: 16px; }
        .actions a { color: #F6F7F5; text-decoration: underline; font-size: 13px; }
    </style>
</head>
<body>
    <p class="msg">Preparing your receipt for printing...</p>

    <iframe id="receipt-frame" src="{{ route('kiosk.receipt.pdf') }}" width="1" height="1" style="visibility:hidden; border:none;"></iframe>

    <div class="actions">
        <a href="{{ route('kiosk.receipt.pdf') }}" target="_blank">Open receipt manually</a>
        <a href="{{ route('kiosk.dashboard') }}">Back to Dashboard</a>
    </div>

    <script>
        const frame = document.getElementById('receipt-frame');
        frame.addEventListener('load', function () {
            setTimeout(function () {
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                } catch (e) {
                    window.open("{{ route('kiosk.receipt.pdf') }}", '_blank');
                }
            }, 300);
        });
    </script>
</body>
</html>