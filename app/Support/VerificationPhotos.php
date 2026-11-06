<?php

namespace App\Support;

use App\Models\PatronVerificationCapture;
use App\Models\User;
use App\Services\FaceMatchService;
use Illuminate\Database\Eloquent\Builder;

class VerificationPhotos
{
    /**
     * SQL that is true for photos staff still have to look at, or have flagged.
     * It can be NULL for ordinary photos, so it is always wrapped in COALESCE(..., 0).
     */
    public const PROTECTED_SQL = "((review_status IS NULL AND (match_status IN ('mismatch', 'no_face') OR (match_status IS NULL AND face_detected = 0))) OR review_status = 'flagged')";

    /** Photos with a warning that no staff member has decided on yet. */
    public static function needsReview(Builder $query): Builder
    {
        return $query->whereNull('review_status')->where(function (Builder $q) {
            $q->whereIn('match_status', FaceMatchService::WARNING_STATUSES)
                ->orWhere(function (Builder $legacy) {
                    // Older photos that were never checked, where the kiosk itself saw no face
                    $legacy->whereNull('match_status')->where('face_detected', false);
                });
        });
    }

    /** Photos waiting for a decision, or flagged by staff. */
    public static function awaitingOrFlagged(Builder $query): Builder
    {
        return $query->whereRaw('COALESCE(' . self::PROTECTED_SQL . ', 0) = 1');
    }

    /** Photos the clean-up may delete: nothing waiting for a decision and nothing flagged. */
    public static function deletable(Builder $query): Builder
    {
        return $query->whereRaw('COALESCE(' . self::PROTECTED_SQL . ', 0) = 0');
    }

    /** Whether this user may use the verification pages (same rule as the Patrons menu entry). */
    public static function userCanReview(User $user): bool
    {
        $menuConfig = config('koha_menu', []);

        return $user->loadMissing('roles')->roles
            ->flatMap(fn ($role) => $menuConfig[$role->name]['menu'] ?? [])
            ->contains('label', 'Patrons');
    }

    public static function pendingWarningCount(): int
    {
        return self::needsReview(PatronVerificationCapture::query())->count();
    }
}