<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Verification warnings: report and export</h2>
    </x-slot>

    <style>
        .rp-box { background: #fff; border: 2px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px; }
        .rp-row { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; margin-bottom: 14px; }
        .rp-field label { display: block; font-size: 13px; font-weight: 600; color: #111827; margin-bottom: 4px; }
        .rp-field input[type=date] { border: 1px solid #d1d5db; border-radius: 6px; padding: 7px 10px; font-size: 14px; }
        .rp-check { font-size: 14px; color: #374151; display: flex; gap: 8px; align-items: center; margin-bottom: 14px; }
        .rp-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .rp-btn { border: none; border-radius: 6px; padding: 9px 16px; font-size: 14px; font-weight: 600; cursor: pointer; color: #fff; }
        .rp-btn-grey { background: #6b7280; }
        .rp-btn-green { background: #16a34a; }
        .rp-btn-blue { background: #4f46e5; }
        .rp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 12px; }
        .rp-stat { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; }
        .rp-stat b { display: block; font-size: 22px; color: #111827; }
        .rp-stat span { font-size: 12px; color: #6b7280; }
        .rp-error { border-radius: 6px; padding: 10px 14px; font-size: 14px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .rp-quick { font-size: 13px; margin-bottom: 14px; }
        .rp-quick a { color: #4f46e5; margin-right: 14px; text-decoration: none; }
    </style>

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

            <div class="rp-box">
                <p class="text-sm text-gray-700" style="margin-bottom: 14px;">
                    Choose the period. A report covers up to {{ $maxDays }} days. The CSV file has the details of each warning (no pictures).
                    The printable A4 report also shows the Koha photo and the kiosk photo of each warning, up to {{ $maxPhotos }} photos.
                </p>

                <div class="rp-quick">
                    <a href="{{ route('patron-verification.report', ['from' => now('Asia/Dhaka')->subDays(6)->format('Y-m-d'), 'to' => now('Asia/Dhaka')->format('Y-m-d')]) }}">Last 7 days</a>
                    <a href="{{ route('patron-verification.report', ['from' => now('Asia/Dhaka')->subDays(29)->format('Y-m-d'), 'to' => now('Asia/Dhaka')->format('Y-m-d')]) }}">Last 30 days</a>
                    <a href="{{ route('patron-verification.report', ['from' => now('Asia/Dhaka')->format('Y-m-d'), 'to' => now('Asia/Dhaka')->format('Y-m-d')]) }}">Today</a>
                </div>

                <form method="GET" action="{{ route('patron-verification.report') }}">
                    <div class="rp-row">
                        <div class="rp-field">
                            <label for="from">From</label>
                            <input id="from" type="date" name="from" value="{{ $from }}">
                        </div>
                        <div class="rp-field">
                            <label for="to">To</label>
                            <input id="to" type="date" name="to" value="{{ $to }}">
                        </div>
                    </div>

                    <label class="rp-check">
                        <input type="checkbox" name="only_open" value="1" @checked($onlyOpen)>
                        Only photos that still need a staff decision
                    </label>

                    <div class="rp-actions">
                        <button type="submit" class="rp-btn rp-btn-grey">Update preview</button>
                        <button type="submit" formaction="{{ route('patron-verification.report.csv') }}" class="rp-btn rp-btn-green">Download CSV</button>
                        <button type="submit" formaction="{{ route('patron-verification.report.print') }}" formtarget="_blank" class="rp-btn rp-btn-blue">Open printable A4 report</button>
                    </div>
                </form>
            </div>

            <div class="rp-box">
                <p class="text-sm font-semibold text-gray-900">Preview for {{ $from }} to {{ $to }}</p>

                <div class="rp-stats">
                    <div class="rp-stat"><b>{{ $summary['total'] }}</b><span>warnings in total</span></div>
                    <div class="rp-stat"><b>{{ $summary['mismatch'] }}</b><span>do not match the Koha photo</span></div>
                    <div class="rp-stat"><b>{{ $summary['no_face'] }}</b><span>no face found</span></div>
                    <div class="rp-stat"><b>{{ $summary['open'] }}</b><span>still need a decision</span></div>
                    <div class="rp-stat"><b>{{ $summary['reviewed_ok'] }}</b><span>reviewed OK</span></div>
                    <div class="rp-stat"><b>{{ $summary['flagged'] }}</b><span>flagged as a problem</span></div>
                </div>

                <p class="text-xs text-gray-500" style="margin-top: 14px;">
                    These files contain names, card numbers and faces. Keep them safe and delete them when they are no longer needed.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>