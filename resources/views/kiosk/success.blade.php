<x-kiosk-layout max-width="max-w-xl">
    @php $isBorrow = ($receipt['type'] ?? '') === 'borrow'; @endphp

    <div class="rounded-2xl bg-paper/8 p-10 text-center">
        <div style="margin:0 auto; width:80px; height:80px; border-radius:50%; background:rgba(74,222,128,0.15); display:flex; align-items:center; justify-content:center;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>

        <p class="font-display text-3xl" style="margin-top:24px;">
            {{ $isBorrow ? 'Successfully Borrowed' : 'Successfully Returned' }}
        </p>
        <p style="margin-top:8px; color:rgba(246,247,245,0.7);">{{ $receipt['title'] ?? '' }}</p>

        @if ($isBorrow && ! empty($receipt['due_date']))
            <p style="margin-top:4px; font-size:13px; color:rgba(246,247,245,0.5);">
                Due: {{ \Illuminate\Support\Str::limit($receipt['due_date'], 10, '') }}
            </p>
        @endif

        <div style="margin-top:32px; display:flex; flex-direction:column; gap:12px;">
            <a href="{{ route('kiosk.receipt') }}"
               style="display:block; background:#E4A72E; color:#16233E; border-radius:12px; padding:14px 24px; font-size:15px; font-weight:700; text-decoration:none;">
                Print Receipt
            </a>
            <a href="{{ route('kiosk.dashboard') }}"
               style="display:block; border:1px solid rgba(246,247,245,0.3); color:#F6F7F5; border-radius:12px; padding:14px 24px; font-size:15px; font-weight:600; text-decoration:none;">
                Exit Without Receipt
            </a>
        </div>
    </div>
</x-kiosk-layout>