<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\PatronVerificationCapture;
use App\Support\RetentionSettings;
use App\Support\VerificationPhotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RetentionSettingsController extends Controller
{
    public function edit(): View
    {
        $normal = RetentionSettings::normalDays();
        $flagged = RetentionSettings::flaggedDays();

        return view('admin.retention', [
            'normalDays' => $normal,
            'flaggedDays' => $flagged,
            'min' => RetentionSettings::MIN_DAYS,
            'max' => RetentionSettings::MAX_DAYS,
            'total' => PatronVerificationCapture::count(),
            'expiredNow' => VerificationPhotos::expired(PatronVerificationCapture::query(), $normal, $flagged)->count(),
            'waiting' => VerificationPhotos::needsReview(PatronVerificationCapture::query())->count(),
            'flaggedCount' => PatronVerificationCapture::where('review_status', 'flagged')->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $min = RetentionSettings::MIN_DAYS;
        $max = RetentionSettings::MAX_DAYS;

        $data = $request->validate(
            [
                'photo_retention_days' => ['required', 'integer', "min:$min", "max:$max"],
                'flagged_retention_days' => ['required', 'integer', "min:$min", "max:$max", 'gte:photo_retention_days'],
            ],
            [
                'photo_retention_days.min' => "Photos must be kept at least $min days.",
                'flagged_retention_days.min' => "Photos must be kept at least $min days.",
                'flagged_retention_days.gte' => 'Photos that need review or are flagged must be kept at least as long as ordinary photos.',
            ]
        );

        AppSetting::write('photo_retention_days', (string) $data['photo_retention_days']);
        AppSetting::write('flagged_retention_days', (string) $data['flagged_retention_days']);

        Log::info(sprintf(
            'Photo retention changed by user %d: ordinary %d days, review or flagged %d days.',
            $request->user()->id,
            $data['photo_retention_days'],
            $data['flagged_retention_days']
        ));

        return redirect()->route('admin.retention.edit')->with('status', 'Retention settings saved.');
    }
}