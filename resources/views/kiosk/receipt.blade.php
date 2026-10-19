<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Printing Receipt...</title>
    <style>
        body { margin: 0; background: #16233E; color: #F6F7F5; font-family: sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; }
        .msg { margin-bottom: 16px; font-size: 14px; }
        .actions { margin-top: 16px; display: flex; gap: 16px; }
                .actions a { color: #16233E; background: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; padding: 12px 22px; border-radius: 12px; white-space: nowrap; }
    </style>
</head>
<body>
    <p class="msg">Preparing your receipt for printing...</p>

    <iframe id="receipt-frame" src="{{ route('kiosk.receipt.pdf') }}" width="1" height="1" style="visibility:hidden; border:none;"></iframe>

    <div class="actions">
        <a href="{{ route('kiosk.receipt.pdf') }}" target="_blank">Open receipt manually</a>
        <a href="{{ route('kiosk.dashboard') }}">Back to Dashboard</a>
    </div>
   @if (session()->has('kiosk_patron'))
        @include('kiosk.partials.idle-timer')
    @endif
</body>
</html>