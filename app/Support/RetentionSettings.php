<?php

namespace App\Support;

use App\Models\AppSetting;

class RetentionSettings
{
    public const MIN_DAYS = 7;

    public const MAX_DAYS = 3650;

    /** Days after which ordinary kiosk photos are deleted. */
    public static function normalDays(): int
    {
        return self::clamp(
            AppSetting::read('photo_retention_days'),
            (int) config('kiosk.photo_retention_days', 90)
        );
    }

    /** Days after which photos that still need review, or were flagged, are deleted. Never shorter than normalDays(). */
    public static function flaggedDays(): int
    {
        $days = self::clamp(
            AppSetting::read('flagged_retention_days'),
            (int) config('kiosk.flagged_retention_days', 365)
        );

        return max($days, self::normalDays());
    }

    private static function clamp(?string $saved, int $fallback): int
    {
        $days = is_numeric($saved) ? (int) $saved : $fallback;

        return min(self::MAX_DAYS, max(self::MIN_DAYS, $days));
    }
}