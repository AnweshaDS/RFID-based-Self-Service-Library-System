@include('kiosk.partials.scan-notice')
<x-kiosk-layout max-width="max-w-xl">
    @php
        $isBorrow = ($receipt['type'] ?? '') === 'borrow';
        $items = $receipt['items'] ?? [];
        $reason = $items[0]['message'] ?? null;
    @endphp

    <div class="rounded-2xl bg-paper/8 p-10 text-center">
        <div style="margin:0 auto; width:80px; height:80px; border-radius:50%; background:rgba(248,113,113,0.15); display:flex; align-items:center; justify-content:center;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="6" x2="12" y2="14"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
        </div>

        <p class="font-display text-3xl" style="margin-top:24px;">
            {{ $isBorrow ? 'We could not borrow your books' : 'We could not return your books' }}
        </p>
        <p style="margin-top:12px; font-size:16px; color:rgba(246,247,245,0.9);">
            {{ $reason ?: 'Something went wrong. Please try again, or ask a librarian at the desk.' }}
        </p>

        <div style="margin-top:20px; text-align:left;" class="space-y-2">
            @foreach ($items as $item)
                <div class="rounded-xl bg-paper/10 px-4 py-3 text-sm">
                    <span class="block font-medium">{{ $item['title'] }}</span>
                    @if (! empty($item['message']))
                        <span class="block text-xs text-red-300">{{ $item['message'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div style="margin-top:32px;">
            <a href="{{ route('kiosk.dashboard') }}"
               style="display:block; background:#E4A72E; color:#16233E; border-radius:12px; padding:14px 24px; font-size:16px; font-weight:700; text-decoration:none;">
                Back to Dashboard
            </a>
        </div>
    </div>
</x-kiosk-layout>