<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verification warnings {{ $from }} to {{ $to }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 12px; margin: 0; background: #f3f4f6; }
        .toolbar { position: sticky; top: 0; z-index: 5; background: #1f2937; color: #fff; padding: 10px 16px; display: flex; flex-wrap: wrap; gap: 14px; align-items: center; font-size: 13px; }
        .toolbar button { border: none; border-radius: 6px; padding: 8px 16px; font-size: 14px; font-weight: 700; cursor: pointer; background: #4f46e5; color: #fff; }
        .toolbar a { color: #c7d2fe; text-decoration: none; }
        .page { max-width: 210mm; margin: 12px auto; background: #fff; padding: 12mm; }
        .project { font-size: 10px; color: #6b7280; }
        h1 { font-size: 20px; margin: 4px 0 2px; }
        .meta { font-size: 12px; color: #374151; margin-bottom: 10px; }
        .summary { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 14px; }
        .summary div { border: 1px solid #d1d5db; border-radius: 4px; padding: 6px 10px; min-width: 100px; }
        .summary b { display: block; font-size: 16px; }
        .summary span { font-size: 10px; color: #6b7280; }
        .notice { border: 1px solid #fcd34d; background: #fffbeb; padding: 6px 10px; border-radius: 4px; margin-bottom: 12px; font-size: 11px; }
        .entry { display: flex; gap: 10px; border: 1px solid #d1d5db; border-radius: 4px; padding: 8px; margin-bottom: 8px; break-inside: avoid; page-break-inside: avoid; }
        .photo { width: 36mm; flex: 0 0 36mm; }
        .frame { position: relative; width: 36mm; height: 36mm; border: 1px solid #9ca3af; background: #f3f4f6; overflow: hidden; }
        .frame img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .missing { display: none; position: absolute; inset: 0; align-items: center; justify-content: center; text-align: center; padding: 4px; font-size: 10px; color: #6b7280; }
        .cap { font-size: 10px; color: #6b7280; text-align: center; margin-top: 3px; }
        .details { flex: 1; min-width: 0; }
        .name { font-size: 14px; font-weight: 700; }
        .muted { color: #6b7280; margin-bottom: 6px; }
        .pill { display: inline-block; border: 1.5px solid #6b7280; border-radius: 999px; padding: 2px 10px; font-weight: 700; margin-bottom: 6px; }
        .pill-mismatch { border-color: #b91c1c; color: #b91c1c; }
        .pill-no_face { border-color: #b45309; color: #b45309; }
        .line { margin-top: 3px; }
        .foot { margin-top: 14px; font-size: 10px; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 8px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; padding: 0; max-width: none; }
            .pill, .summary div { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print (A4)</button>
        <span>In the print window choose A4. Choose 'Save as PDF' as the printer to make a file.</span>
        <a href="{{ route('patron-verification.report', ['from' => $from, 'to' => $to, 'only_open' => $onlyOpen ? 1 : null]) }}">Back to the report page</a>
    </div>

    <div class="page">
        <div class="project">{{ config('smartlib.full_title') }}</div>
        <h1>Verification warnings report</h1>
        <div class="meta">
            Period: {{ $from }} to {{ $to }} (Dhaka time)
            @if ($onlyOpen)
                - only photos that still need a staff decision
            @endif
            <br>
            Created {{ $generatedAt }} by {{ $generatedBy }}
        </div>

        <div class="summary">
            <div><b>{{ $summary['total'] }}</b><span>warnings in total</span></div>
            <div><b>{{ $summary['mismatch'] }}</b><span>do not match</span></div>
            <div><b>{{ $summary['no_face'] }}</b><span>no face found</span></div>
            <div><b>{{ $summary['open'] }}</b><span>still need a decision</span></div>
            <div><b>{{ $summary['reviewed_ok'] }}</b><span>reviewed OK</span></div>
            <div><b>{{ $summary['flagged'] }}</b><span>flagged</span></div>
        </div>

        @if ($summary['total'] > $shown)
            <div class="notice">Showing the first {{ $shown }} of {{ $summary['total'] }} warnings. Choose a shorter period to see the rest.</div>
        @endif

        @forelse ($entries as $e)
            @php($c = $e['capture'])
            <div class="entry">
                <div class="photo">
                    <div class="frame">
                        @if (ctype_digit((string) $c->patron_id))
                            <img src="{{ route('patron-verification.photo', (int) $c->patron_id) }}" alt="Koha photo"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="missing">No photo in Koha</div>
                        @else
                            <div class="missing" style="display: flex;">No Koha patron</div>
                        @endif
                    </div>
                    <div class="cap">Koha photo</div>
                </div>

                <div class="photo">
                    <div class="frame">
                        <img src="{{ route('patron-verification.history.photo', $c) }}" alt="Kiosk photo"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="missing">Photo file not available</div>
                    </div>
                    <div class="cap">Photo taken at the kiosk</div>
                </div>

                <div class="details">
                    <div class="name">{{ $c->patron_name ?? $c->cardnumber ?? 'Unknown patron' }}</div>
                    <div class="muted">Card {{ $c->cardnumber ?? 'unknown' }} - {{ ucfirst($c->action) }} - {{ $e['when'] }}</div>

                    <div class="pill pill-{{ $e['kind'] }}">
                        {{ $e['result'] }}
                        @if ($e['score'])
                            (score {{ $e['score'] }})
                        @endif
                    </div>

                    @if ($e['books'] !== '')
                        <div class="line"><b>Books:</b> {{ $e['books'] }}</div>
                    @endif

                    <div class="line">
                        <b>Review:</b> {{ $e['review'] }}
                        @if ($e['reviewer'])
                            by {{ $e['reviewer'] }}
                        @endif
                        @if ($e['reviewedAt'] !== '')
                            on {{ $e['reviewedAt'] }}
                        @endif
                    </div>

                    @if ($c->review_note)
                        <div class="line"><b>Note:</b> {{ $c->review_note }}</div>
                    @endif
                </div>
            </div>
        @empty
            <p>No warnings in this period.</p>
        @endforelse

        <div class="foot">
            This report contains personal data (names, card numbers and photos). Keep it safe and destroy it when it is no longer needed.
            A 'does not match' result means a person should look at the photos. It is not proof of anything.
        </div>
    </div>
</body>
</html>