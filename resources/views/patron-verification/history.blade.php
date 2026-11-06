<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Verification History</h2>
    </x-slot>

    <style>
        .vh-wrap { position: relative; }
        .vh-card { display: flex; flex-wrap: wrap; gap: 20px; }
        .vh-koha { flex: 0 0 170px; max-width: 100%; }
        .vh-main { flex: 1 1 320px; min-width: 0; }
        .vh-frame { position: relative; width: 100%; aspect-ratio: 1 / 1; border-radius: 6px; overflow: hidden; background: #f3f4f6; border: 1px solid #e5e7eb; }
        .vh-frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .vh-frame-link { display: block; }
        .vh-missing { display: none; position: absolute; top: 0; right: 0; bottom: 0; left: 0; align-items: center; justify-content: center; text-align: center; padding: 8px; font-size: 11px; color: #6b7280; }
        .vh-missing-on { display: flex; }
        .vh-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; }
        .vh-cap { font-size: 11px; line-height: 1.3; margin-top: 4px; }
        .vh-label { font-size: 11px; color: #6b7280; margin-top: 6px; text-align: center; }

        .vh-warn-badge { position: absolute; top: 10px; right: 12px; display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-size: 12px; font-weight: 600; }
        .vh-flag { position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15); }
        .vh-danger { color: #dc2626; }
        .vh-flagged { color: #b91c1c; }
        .vh-warn { color: #d97706; }
        .vh-info { color: #6b7280; }
        .vh-ok { color: #16a34a; }
        .vh-legend { display: flex; flex-wrap: wrap; gap: 16px; font-size: 12px; color: #4b5563; }
        .vh-legend > span { display: inline-flex; align-items: center; gap: 6px; }
    </style>

    @php
        $icons = [
            'triangle' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            'info' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
            'check' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'flag' => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
        ];
        $iconFor = ['danger' => 'triangle', 'warn' => 'triangle', 'info' => 'info', 'ok' => 'check', 'flagged' => 'flag'];

        // For one kiosk photo: [kind, tooltip text], or null when it has not been checked yet.
        $flagFor = function ($capture) {
            if ($capture->review_status === 'ok') {
                return ['ok', 'Reviewed by staff: OK'];
            }

            if ($capture->review_status === 'flagged') {
                return ['flagged', 'Flagged by staff as a problem'];
            }

            $score = $capture->match_score !== null ? number_format((float) $capture->match_score, 2) : null;

            return match (true) {
                $capture->match_status === 'mismatch' => ['danger', 'Does not match the Koha photo' . ($score ? " (score $score)" : '')],
                $capture->match_status === 'no_face' => ['warn', 'No face found in this photo'],
                $capture->match_status === null && ! $capture->face_detected => ['warn', 'No face detected at the kiosk'],
                $capture->match_status === 'no_reference' => ['info', 'No Koha photo to compare with'],
                $capture->match_status === 'reference_unusable' => ['info', 'The Koha photo has no clear face to compare with'],
                $capture->match_status === 'matched' => ['ok', 'Matches the Koha photo' . ($score ? " (score $score)" : '')],
                default => null,
            };
        };
    @endphp

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white border-2 border-gray-200 rounded-lg shadow p-5 space-y-4">
                <form method="GET" class="flex flex-wrap gap-3">
                    <input type="text" name="cardnumber" value="{{ request('cardnumber') }}" placeholder="Card number"
                           class="rounded-md border-gray-300 text-sm">
                    <select name="action" class="rounded-md border-gray-300 text-sm">
                        <option value="">Any action</option>
                        <option value="borrow" @selected(request('action') === 'borrow')>Borrow</option>
                        <option value="return" @selected(request('action') === 'return')>Return</option>
                    </select>
                    <select name="status" class="rounded-md border-gray-300 text-sm">
                        <option value="">Any status</option>
                        <option value="needs_review" @selected(request('status') === 'needs_review')>Needs review</option>
                        <option value="flagged" @selected(request('status') === 'flagged')>Flagged</option>
                        <option value="mismatch" @selected(request('status') === 'mismatch')>Not matching</option>
                        <option value="no_face" @selected(request('status') === 'no_face')>No face found</option>
                        <option value="reviewed_ok" @selected(request('status') === 'reviewed_ok')>Reviewed OK</option>
                        <option value="unchecked" @selected(request('status') === 'unchecked')>Not checked yet</option>
                    </select>
                    <input type="date" name="date" value="{{ request('date') }}" class="rounded-md border-gray-300 text-sm">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Filter
                    </button>
                </form>

                <div class="vh-legend">
                    <span><span class="vh-danger" style="display:inline-flex;">{!! $icons['triangle'] !!}</span> Does not match the Koha photo</span>
                    <span><span class="vh-warn" style="display:inline-flex;">{!! $icons['triangle'] !!}</span> No face found</span>
                    <span><span class="vh-info" style="display:inline-flex;">{!! $icons['info'] !!}</span> No Koha photo to compare with</span>
                    <span><span class="vh-ok" style="display:inline-flex;">{!! $icons['check'] !!}</span> Matches, or reviewed OK</span>
                    <span><span class="vh-flagged" style="display:inline-flex;">{!! $icons['flag'] !!}</span> Flagged by staff</span>
                </div>
            </div>

            @forelse ($patrons as $p)
                @php($hasKohaId = ctype_digit($p['patron_id']))

                <div class="vh-wrap bg-white border-2 border-gray-200 rounded-lg shadow p-4">

                    @if ($p['warnings'] > 0)
                        <span class="vh-warn-badge" title="Photos on this card that need review">
                            {!! $icons['triangle'] !!} {{ $p['warnings'] }} to review
                        </span>
                    @endif

                    <div class="vh-card">

                        <div class="vh-koha">
                            <div class="vh-frame">
                                @if ($hasKohaId)
                                    <img src="{{ route('patron-verification.photo', $p['patron_id']) }}" alt="Registered photo in Koha" loading="lazy"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="vh-missing">No photo on file in Koha</div>
                                @else
                                    <div class="vh-missing vh-missing-on">No Koha patron linked</div>
                                @endif
                            </div>
                            <p class="vh-label">Registered photo (Koha)</p>
                        </div>

                        <div class="vh-main">
                            <div style="margin-bottom: 12px; padding-right: {{ $p['warnings'] > 0 ? '130px' : '0' }};">
                                <p class="text-sm font-semibold text-gray-900">{{ $p['name'] ?? $p['cardnumber'] ?? 'Unknown patron' }}</p>
                                <p class="text-xs text-gray-500">
                                    Card {{ $p['cardnumber'] ?? 'unknown' }}
                                    - {{ $p['total'] }} {{ \Illuminate\Support\Str::plural('photo', $p['total']) }}
                                    - last on {{ $p['last_at']->format('d M Y, H:i') }}
                                </p>
                            </div>

                            <div class="vh-grid">
                                @foreach ($p['captures'] as $capture)
                                    @php($flag = $flagFor($capture))
                                    <div>
                                        <a href="{{ route('patron-verification.history.show', $capture) }}" class="vh-frame-link" title="Open this photo">
                                            <div class="vh-frame">
                                                <img src="{{ route('patron-verification.history.photo', $capture) }}" alt="Verification photo" loading="lazy"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="vh-missing">Photo file is not on this computer</div>
                                                @if ($flag)
                                                    <span class="vh-flag vh-{{ $flag[0] }}" title="{{ $flag[1] }}">{!! $icons[$iconFor[$flag[0]]] !!}</span>
                                                @endif
                                            </div>
                                        </a>
                                        <p class="vh-cap text-gray-500 capitalize">{{ $capture->action }}@if ($capture->barcode) - {{ $capture->barcode }}@endif</p>
                                        <p class="vh-cap text-gray-400">{{ $capture->created_at->format('d M Y, H:i') }}</p>
                                        @unless ($capture->face_detected)
                                            <p class="vh-cap text-red-600 font-medium">No face detected</p>
                                        @endunless
                                    </div>
                                @endforeach
                            </div>

                            @if ($p['expanded'])
                                <p class="mt-3 text-xs">
                                    <a href="{{ request()->fullUrlWithQuery(['show_all' => null]) }}" class="text-indigo-600 hover:underline">Show only the latest {{ $photosPerCard }}</a>
                                </p>
                            @elseif ($p['total'] > $photosPerCard)
                                <p class="mt-3 text-xs">
                                    <a href="{{ request()->fullUrlWithQuery(['show_all' => $p['patron_id']]) }}" class="text-indigo-600 hover:underline">Show all {{ $p['total'] }} photos</a>
                                </p>
                            @endif
                        </div>

                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No verification photos match these filters.</p>
            @endforelse

            <div>{{ $patrons->links() }}</div>
        </div>
    </div>
</x-app-layout>