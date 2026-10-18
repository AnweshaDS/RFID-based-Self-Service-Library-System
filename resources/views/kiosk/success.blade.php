@include('kiosk.partials.scan-notice')
<x-kiosk-layout max-width="max-w-xl">
    @php
        $isBorrow = ($receipt['type'] ?? '') === 'borrow';
        $items = $receipt['items'] ?? [];
        $total = count($items);
        $okCount = collect($items)->where('ok', true)->count();
        $allOk = $total > 0 && $okCount === $total;
        $done = $isBorrow ? 'borrowed' : 'returned';
    @endphp

    <div class="rounded-2xl bg-paper/8 p-10 text-center">
        <div style="margin:0 auto; width:80px; height:80px; border-radius:50%; background:{{ $allOk ? 'rgba(74,222,128,0.15)' : 'rgba(251,191,36,0.15)' }}; display:flex; align-items:center; justify-content:center;">
            @if ($allOk)
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            @else
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="6" x2="12" y2="14"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
            @endif
        </div>

        <p class="font-display text-3xl" style="margin-top:24px;">
            @if ($allOk)
                Successfully {{ ucfirst($done) }}
            @elseif ($okCount > 0)
                {{ $okCount }} of {{ $total }} books {{ $done }}
            @else
                Could not complete
            @endif
        </p>

        <div style="margin-top:20px; text-align:left;" class="space-y-2">
            @foreach ($items as $item)
                <div class="rounded-xl bg-paper/10 px-4 py-3 text-sm">
                    <span class="block font-medium">{{ $item['title'] }}</span>
                    @if ($item['ok'])
                        @if ($isBorrow && ! empty($item['due_date']))
                            <span class="block text-xs text-paper/50">Due {{ \Illuminate\Support\Str::limit($item['due_date'], 10, '') }}</span>
                        @endif
                    @else
                        <span class="block text-xs text-red-300">{{ $item['message'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div style="margin-top:32px; display:flex; flex-direction:column; gap:12px;">
                        <a href="{{ route('kiosk.receipt') }}"
               style="display:block; background:#E4A72E; color:#16233E; border-radius:12px; padding:14px 24px; font-size:15px; font-weight:700; text-decoration:none;">
                Print Receipt
            </a>
            <a href="{{ route('kiosk.receipt.view') }}"
               style="display:block; border:1px solid rgba(246,247,245,0.3); color:#F6F7F5; border-radius:12px; padding:14px 24px; font-size:15px; font-weight:600; text-decoration:none;">
                View Receipt
            </a>
            <a href="{{ route('kiosk.dashboard') }}"
               style="display:block; border:1px solid rgba(246,247,245,0.3); color:#F6F7F5; border-radius:12px; padding:14px 24px; font-size:15px; font-weight:600; text-decoration:none;">
                Exit Without Receipt
            </a>
        </div>
    </div>
    <p style="margin-top:16px; text-align:center; font-size:12px; color:rgba(246,247,245,0.5);">
        Logging out automatically in <span id="auto-logout-count">30</span> s
    </p>
    <script>
        (function () {
            let s = 30;
            const el = document.getElementById('auto-logout-count');
            const t = setInterval(function () {
                s--;
                el.textContent = s;
                if (s <= 0) {
                    clearInterval(t);
                    document.getElementById('idle-logout-form').submit();
                }
            }, 1000);
        })();
    </script>
    @include('kiosk.partials.scan-notice')
</x-kiosk-layout>