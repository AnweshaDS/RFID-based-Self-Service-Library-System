<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Verification photo</h2>
    </x-slot>

    <style>
        .cp-box { background: #fff; border: 2px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px; }
        .cp-top { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .cp-nav { display: flex; flex-wrap: wrap; gap: 14px; font-size: 13px; }
        .cp-nav a { color: #4f46e5; text-decoration: none; }
        .cp-nav a:hover { text-decoration: underline; }
        .cp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 700px) { .cp-grid { grid-template-columns: 1fr; } }
        .cp-frame { position: relative; width: 100%; aspect-ratio: 1 / 1; border-radius: 6px; overflow: hidden; background: #f3f4f6; border: 1px solid #e5e7eb; }
        .cp-frame img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .cp-missing { display: none; position: absolute; inset: 0; align-items: center; justify-content: center; text-align: center; padding: 12px; font-size: 13px; color: #6b7280; }
        .cp-missing-on { display: flex; }
        .cp-label { font-size: 12px; color: #6b7280; margin-top: 6px; text-align: center; }
        .cp-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .cp-danger { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .cp-warn { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .cp-info { background: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb; }
        .cp-ok { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .cp-facts { display: grid; grid-template-columns: 140px 1fr; gap: 6px 14px; font-size: 14px; margin-top: 18px; }
        .cp-facts dt { color: #6b7280; }
        .cp-facts dd { margin: 0; color: #111827; }
        .cp-textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; font-size: 14px; }
        .cp-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
        .cp-btn { border: none; border-radius: 6px; padding: 9px 16px; font-size: 14px; font-weight: 600; cursor: pointer; color: #fff; }
        .cp-btn-ok { background: #16a34a; }
        .cp-btn-flag { background: #dc2626; }
        .cp-btn-grey { background: #6b7280; }
        .cp-notice { border-radius: 6px; padding: 10px 14px; font-size: 14px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .cp-error { border-radius: 6px; padding: 10px 14px; font-size: 14px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    </style>

    @php
        $score = $capture->match_score !== null ? number_format((float) $capture->match_score, 3) : null;

        // [badge style, text]
        [$resultKind, $resultText] = match (true) {
            $capture->match_status === 'mismatch' => ['danger', 'Does not match the Koha photo'],
            $capture->match_status === 'no_face' => ['warn', 'No face found in the kiosk photo'],
            $capture->match_status === 'no_reference' => ['info', 'No Koha photo to compare with'],
            $capture->match_status === 'reference_unusable' => ['info', 'The Koha photo has no clear face to compare with'],
            $capture->match_status === 'matched' => ['ok', 'Matches the Koha photo'],
            ! $capture->face_detected => ['warn', 'No face detected at the kiosk (not checked yet)'],
            default => ['info', 'Not checked yet'],
        };

        $hasKohaId = ctype_digit((string) $capture->patron_id);
    @endphp

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="cp-notice">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="cp-error">{{ $errors->first() }}</div>
            @endif

            <div class="cp-box">
                <div class="cp-top">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $capture->patron_name ?? $capture->cardnumber ?? 'Unknown patron' }}</p>
                        <p class="text-xs text-gray-500">
                            Card {{ $capture->cardnumber ?? 'unknown' }}
                            - {{ ucfirst($capture->action) }}
                            - {{ $capture->created_at->format('d M Y, H:i') }}
                        </p>
                    </div>
                    <div class="cp-nav">
                        @if ($older)
                            <a href="{{ route('patron-verification.history.show', $older->id) }}">&larr; Older photo</a>
                        @endif
                        @if ($newer)
                            <a href="{{ route('patron-verification.history.show', $newer->id) }}">Newer photo &rarr;</a>
                        @endif
                        <a href="{{ route('patron-verification.history', ['cardnumber' => $capture->cardnumber]) }}">Back to history</a>
                    </div>
                </div>

                <div class="cp-grid">
                    <div>
                        <div class="cp-frame">
                            @if ($hasKohaId)
                                <img src="{{ route('patron-verification.photo', (int) $capture->patron_id) }}" alt="Registered photo in Koha"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="cp-missing">No photo on file in Koha</div>
                            @else
                                <div class="cp-missing cp-missing-on">No Koha patron linked</div>
                            @endif
                        </div>
                        <p class="cp-label">Registered photo (Koha)</p>
                    </div>

                    <div>
                        <div class="cp-frame">
                            <img src="{{ route('patron-verification.history.photo', $capture) }}" alt="Kiosk photo"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="cp-missing">Photo file is not on this computer</div>
                        </div>
                        <p class="cp-label">Photo taken at the kiosk</p>
                    </div>
                </div>

                <dl class="cp-facts">
                    <dt>Result</dt>
                    <dd><span class="cp-badge cp-{{ $resultKind }}">{{ $resultText }}</span>@if ($score) <span class="text-xs text-gray-500">score {{ $score }}</span>@endif</dd>

                    <dt>Action</dt>
                    <dd>{{ ucfirst($capture->action) }}</dd>

                    <dt>Books</dt>
                    <dd>
                        @forelse ($items as $item)
                            <div>{{ $item['title'] ?? 'Title not available' }}@if (! empty($item['barcode'])) <span class="text-xs text-gray-500">({{ $item['barcode'] }})</span>@endif</div>
                        @empty
                            <span class="text-gray-500">Not recorded for this photo.</span>
                        @endforelse
                    </dd>

                    <dt>Review</dt>
                    <dd>
                        @if ($capture->review_status === 'ok')
                            <span class="cp-badge cp-ok">Reviewed: OK</span>
                        @elseif ($capture->review_status === 'flagged')
                            <span class="cp-badge cp-danger">Flagged as a problem</span>
                        @else
                            <span class="text-gray-500">Not reviewed yet.</span>
                        @endif

                        @if ($capture->review_status)
                            <div class="text-xs text-gray-500" style="margin-top:4px;">
                                By {{ $reviewer?->name ?? 'a staff member' }}@if ($capture->reviewed_at) on {{ \Illuminate\Support\Carbon::parse($capture->reviewed_at)->format('d M Y, H:i') }}@endif
                            </div>
                        @endif
                    </dd>

                    @if ($capture->review_note)
                        <dt>Note</dt>
                        <dd>{{ $capture->review_note }}</dd>
                    @endif
                </dl>
            </div>

            <div class="cp-box">
                <p class="text-sm font-semibold text-gray-900" style="margin-bottom:10px;">Your decision</p>

                <form method="POST" action="{{ route('patron-verification.history.review', $capture) }}">
                    @csrf

                    <label for="note" class="text-sm text-gray-700">Note (optional)</label>
                    <textarea id="note" name="note" rows="3" maxlength="500" class="cp-textarea">{{ old('note', $capture->review_note) }}</textarea>

                    <div class="cp-actions">
                        <button type="submit" name="decision" value="ok" class="cp-btn cp-btn-ok">Mark as OK (false alarm)</button>
                        <button type="submit" name="decision" value="flagged" class="cp-btn cp-btn-flag">Flag as a problem</button>
                        @if ($capture->review_status)
                            <button type="submit" name="decision" value="reopen" class="cp-btn cp-btn-grey">Re-open</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>