<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Verification report and export</h2>
    </x-slot>

    <style>
        .rp-box { background: #fff; border: 2px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px; }
        .rp-row { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; margin-bottom: 8px; }
        .rp-field label { display: block; font-size: 13px; font-weight: 600; color: #111827; margin-bottom: 4px; }
        .rp-field input[type=date] { border: 1px solid #d1d5db; border-radius: 6px; padding: 7px 10px; font-size: 14px; }
        .rp-title { font-size: 14px; font-weight: 700; color: #111827; margin: 6px 0 10px; }
        .rp-opts { display: grid; gap: 8px; margin-bottom: 12px; }
        .rp-opt { display: flex; gap: 10px; align-items: flex-start; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 12px; cursor: pointer; }
        .rp-opt:hover { background: #f9fafb; }
        .rp-opt input { margin-top: 4px; }
        .rp-opt b { display: block; font-size: 14px; color: #111827; }
        .rp-opt small { display: block; font-size: 12px; color: #6b7280; }
        .rp-count { margin-left: 6px; font-weight: 700; color: #4f46e5; }
        .rp-more summary { cursor: pointer; font-size: 13px; font-weight: 600; color: #4f46e5; margin-bottom: 10px; }
        .rp-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .rp-btn { border: none; border-radius: 6px; padding: 9px 16px; font-size: 14px; font-weight: 600; cursor: pointer; color: #fff; }
        .rp-btn-grey { background: #6b7280; }
        .rp-btn-green { background: #16a34a; }
        .rp-btn-blue { background: #4f46e5; }
        .rp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 8px; }
        .rp-stat { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; }
        .rp-stat b { display: block; font-size: 22px; color: #111827; }
        .rp-stat span { font-size: 12px; color: #6b7280; }
        .rp-sub { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-top: 16px; }
        .rp-error { border-radius: 6px; padding: 10px 14px; font-size: 14px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .rp-warn { border-radius: 6px; padding: 10px 14px; font-size: 13px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; margin-top: 10px; }
        .rp-quick { font-size: 13px; margin-bottom: 14px; }
        .rp-quick a { color: #4f46e5; margin-right: 14px; text-decoration: none; }
    </style>

    @php
        $others = array_values(array_diff(array_keys($scopes), $mainScopes));
    @endphp

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <a href="{{ route('patron-verification.history') }}" style="font-size: 13px; color: #4f46e5; text-decoration: none;">&larr; Back to Verification History</a>

            @if ($errors->any())
                <div class="rp-error">
                    @foreach ($errors->all() as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            <form method="GET" action="{{ route('patron-verification.report') }}" id="report-form" class="space-y-6">

                <div class="rp-box">
                    <p class="text-sm text-gray-700" style="margin-bottom: 14px;">
                        Choose the period (up to {{ $maxDays }} days). Then press <b>Open printable A4 report</b> or <b>Download CSV</b> and choose what to include.
                        The CSV has the details of each photo (no pictures). The A4 report also shows the Koha photo and the kiosk photo. There is no limit on the number of photos.
                    </p>

                    <div class="rp-quick">
                        <a href="{{ route('patron-verification.report', ['from' => now('Asia/Dhaka')->subDays(6)->format('Y-m-d'), 'to' => now('Asia/Dhaka')->format('Y-m-d')]) }}">Last 7 days</a>
                        <a href="{{ route('patron-verification.report', ['from' => now('Asia/Dhaka')->subDays(29)->format('Y-m-d'), 'to' => now('Asia/Dhaka')->format('Y-m-d')]) }}">Last 30 days</a>
                        <a href="{{ route('patron-verification.report', ['from' => now('Asia/Dhaka')->format('Y-m-d'), 'to' => now('Asia/Dhaka')->format('Y-m-d')]) }}">Today</a>
                    </div>

                    <div class="rp-row">
                        <div class="rp-field">
                            <label for="from">From</label>
                            <input id="from" type="date" name="from" value="{{ $from }}">
                        </div>
                        <div class="rp-field">
                            <label for="to">To</label>
                            <input id="to" type="date" name="to" value="{{ $to }}">
                        </div>
                        <button type="submit" class="rp-btn rp-btn-grey">Update numbers</button>
                    </div>

                    <div id="dates-changed" class="rp-warn" style="display: none;">The dates changed. Press Update numbers to refresh the counts below.</div>
                </div>

                <div class="rp-box">
                    <p class="text-sm font-semibold text-gray-900">Photos from {{ $from }} to {{ $to }}</p>

                    <div class="rp-stats">
                        <div class="rp-stat"><b>{{ $summary['total'] }}</b><span>photos in total</span></div>
                        <div class="rp-stat"><b>{{ $summary['open'] }}</b><span>still need a staff decision</span></div>
                    </div>

                    <p class="rp-sub">System result</p>
                    <div class="rp-stats">
                        <div class="rp-stat"><b>{{ $summary['matched'] }}</b><span>match the Koha photo</span></div>
                        <div class="rp-stat"><b>{{ $summary['mismatch'] }}</b><span>do not match</span></div>
                        <div class="rp-stat"><b>{{ $summary['no_face'] }}</b><span>no face found</span></div>
                        <div class="rp-stat"><b>{{ $summary['no_reference'] }}</b><span>no Koha photo to compare</span></div>
                        <div class="rp-stat"><b>{{ $summary['pending'] }}</b><span>not checked yet</span></div>
                    </div>

                    <p class="rp-sub">Staff decision</p>
                    <div class="rp-stats">
                        <div class="rp-stat"><b>{{ $summary['ok'] }}</b><span>reviewed OK</span></div>
                        <div class="rp-stat"><b>{{ $summary['flagged'] }}</b><span>flagged as a problem</span></div>
                        <div class="rp-stat"><b>{{ $summary['none'] }}</b><span>no decision yet</span></div>
                    </div>
                </div>

                <div class="rp-box">
                    <p class="text-sm font-semibold text-gray-900" style="margin-bottom: 10px;">Create a report</p>

                    <div class="rp-actions">
                        <button type="button" class="rp-btn rp-btn-blue" data-mode="print">Open printable A4 report</button>
                        <button type="button" class="rp-btn rp-btn-green" data-mode="csv">Download CSV</button>
                    </div>

                    <div id="choose-panel" style="display: none; margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
                        <p id="choose-title" class="rp-title"></p>

                        <div class="rp-opts">
                            @foreach ($mainScopes as $key)
                                <label class="rp-opt">
                                    <input type="radio" name="scope" value="{{ $key }}" @checked($key === $defaultChoice)>
                                    <span>
                                        <b>{{ $scopes[$key][0] }}<span class="rp-count">{{ $counts[$key] }}</span></b>
                                        <small>{{ $scopes[$key][1] }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <details class="rp-more">
                            <summary>More choices</summary>
                            <div class="rp-opts">
                                @foreach ($others as $key)
                                    <label class="rp-opt">
                                        <input type="radio" name="scope" value="{{ $key }}">
                                        <span>
                                            <b>{{ $scopes[$key][0] }}<span class="rp-count">{{ $counts[$key] }}</span></b>
                                            <small>{{ $scopes[$key][1] }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </details>

                        <div class="rp-actions" style="margin-top: 14px;">
                            <button type="submit" id="choose-go" class="rp-btn rp-btn-blue">Open</button>
                            <button type="button" id="choose-cancel" class="rp-btn rp-btn-grey">Cancel</button>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500" style="margin-top: 14px;">
                        These files contain names, card numbers and faces. Keep them safe and delete them when they are no longer needed.
                    </p>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var panel = document.getElementById('choose-panel');
            var title = document.getElementById('choose-title');
            var go = document.getElementById('choose-go');
            var current = null;

            var modes = {
                print: {
                    title: 'Open the printable A4 report: what should it include?',
                    label: 'Open A4 report',
                    action: "{{ route('patron-verification.report.print') }}",
                    target: '_blank'
                },
                csv: {
                    title: 'Download the CSV file: what should it include?',
                    label: 'Download CSV',
                    action: "{{ route('patron-verification.report.csv') }}",
                    target: '_self'
                }
            };

            function closePanel() {
                panel.style.display = 'none';
                current = null;
            }

            Array.prototype.forEach.call(document.querySelectorAll('[data-mode]'), function (button) {
                button.addEventListener('click', function () {
                    var mode = button.getAttribute('data-mode');

                    if (current === mode && panel.style.display !== 'none') {
                        closePanel();
                        return;
                    }

                    current = mode;
                    title.textContent = modes[mode].title;
                    go.textContent = modes[mode].label;
                    go.setAttribute('formaction', modes[mode].action);
                    go.setAttribute('formtarget', modes[mode].target);
                    panel.style.display = 'block';
                    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });

            document.getElementById('choose-cancel').addEventListener('click', closePanel);

            // Remind the user to refresh the counts after changing a date
            ['from', 'to'].forEach(function (id) {
                document.getElementById(id).addEventListener('change', function () {
                    document.getElementById('dates-changed').style.display = 'block';
                });
            });
        })();
    </script>
</x-app-layout>