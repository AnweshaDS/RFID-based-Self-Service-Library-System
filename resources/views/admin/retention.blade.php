<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Photo retention</h2>
    </x-slot>

    <style>
        .rt-box { background: #fff; border: 2px solid #e5e7eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px; }
        .rt-row { margin-bottom: 18px; }
        .rt-label { display: block; font-size: 14px; font-weight: 600; color: #111827; }
        .rt-help { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .rt-input { width: 140px; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 10px; font-size: 14px; margin-top: 6px; }
        .rt-btn { border: none; border-radius: 6px; padding: 9px 18px; font-size: 14px; font-weight: 600; cursor: pointer; color: #fff; background: #4f46e5; }
        .rt-notice { border-radius: 6px; padding: 10px 14px; font-size: 14px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .rt-error { border-radius: 6px; padding: 10px 14px; font-size: 14px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .rt-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-top: 12px; }
        .rt-stat { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; }
        .rt-stat b { display: block; font-size: 22px; color: #111827; }
        .rt-stat span { font-size: 12px; color: #6b7280; }
    </style>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <a href="{{ route('admin.settings') }}" style="font-size: 13px; color: #4f46e5; text-decoration: none;">&larr; Back to Settings</a>

            @if (session('status'))
                <div class="rt-notice">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rt-error">
                    @foreach ($errors->all() as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            <div class="rt-box">
                <p class="text-sm text-gray-700" style="margin-bottom: 16px;">
                    Kiosk identity photos are deleted automatically once they are older than the number of days below.
                    Photos that still need review, and photos flagged by staff, are kept for the longer period.
                    Saving here does not delete anything. The cleanup runs once a day at 02:00 on the computer that runs the scheduler.
                </p>

                <form method="POST" action="{{ route('admin.retention.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="rt-row">
                        <label for="photo_retention_days" class="rt-label">Keep ordinary photos for (days)</label>
                        <input id="photo_retention_days" type="number" name="photo_retention_days" class="rt-input"
                               min="{{ $min }}" max="{{ $max }}" value="{{ old('photo_retention_days', $normalDays) }}" required>
                        <p class="rt-help">At least {{ $min }} days. Photos that matched the Koha photo, or were reviewed as OK, are deleted after this time.</p>
                    </div>

                    <div class="rt-row">
                        <label for="flagged_retention_days" class="rt-label">Keep photos that need review or are flagged for (days)</label>
                        <input id="flagged_retention_days" type="number" name="flagged_retention_days" class="rt-input"
                               min="{{ $min }}" max="{{ $max }}" value="{{ old('flagged_retention_days', $flaggedDays) }}" required>
                        <p class="rt-help">Must be at least as long as the number above.</p>
                    </div>

                    <button type="submit" class="rt-btn">Save</button>
                </form>
            </div>

            <div class="rt-box">
                <p class="text-sm font-semibold text-gray-900">Right now</p>

                <div class="rt-stats">
                    <div class="rt-stat"><b>{{ $total }}</b><span>photos stored</span></div>
                    <div class="rt-stat"><b>{{ $expiredNow }}</b><span>past their limit, deleted at the next 02:00 run</span></div>
                    <div class="rt-stat"><b>{{ $waiting }}</b><span>waiting for a staff decision</span></div>
                    <div class="rt-stat"><b>{{ $flaggedCount }}</b><span>flagged as a problem</span></div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>