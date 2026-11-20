<?php

namespace App\Http\Controllers;

use App\Models\PatronVerificationCapture;
use App\Models\User;
use App\Support\VerificationPhotos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatronVerificationReportController extends Controller
{
    private const TIMEZONE = 'Asia/Dhaka';

    /** Longest period one report may cover, in days. */
    private const MAX_DAYS = 31;

    private const DEFAULT_SCOPE = 'all_warnings';
        /** The choices shown first when a report is opened. The rest are under More choices. */
    private const MAIN_SCOPES = ['all', 'system_verified', 'staff_verified', 'needs_review'];

    /** The choice that is selected when the panel opens. */
    private const DEFAULT_CHOICE = 'needs_review';

    /** What a report can include: key => [label, short explanation]. */
    private const SCOPES = [
        'all' => ['All transactions', 'Every photo in the period, whatever its result.'],
        'system_verified' => ['Verified by the system', 'The face check matched the Koha photo, and no staff decision was needed.'],
        'staff_verified' => ['Verified by staff', 'A staff member reviewed the photo and marked it OK.'],
        'flagged' => ['Flagged by staff', 'A staff member flagged the photo as a problem.'],
        'needs_review' => ['Not verified: still has a warning', 'No match, or no face found, and no staff decision yet.'],
        'all_warnings' => ['All warnings', 'No match, no face found, or flagged, whatever staff decided.'],
        'not_checked' => ['Not checked by the system yet', 'The face check has not run on these photos.'],
        'no_reference' => ['No Koha photo to compare with', 'The patron has no usable registered photo, so no check was possible.'],
    ];

    private function ensureAccess(Request $request): void
    {
        abort_unless(VerificationPhotos::userCanReview($request->user()), 403);
    }

    /**
     * Start and end of the chosen period in the app time zone, then the two dates as text.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: string}
     */
    private function range(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'scope' => ['nullable', Rule::in(array_keys(self::SCOPES))],
            'only_open' => ['nullable', 'boolean'],
        ]);

        $today = Carbon::now(self::TIMEZONE)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::createFromFormat('Y-m-d', $request->input('to'), self::TIMEZONE)->startOfDay()
            : $today;

        $from = $request->filled('from')
            ? Carbon::createFromFormat('Y-m-d', $request->input('from'), self::TIMEZONE)->startOfDay()
            : $to->copy()->subDays(6);

        if ($from->gt($to)) {
            throw ValidationException::withMessages(['to' => 'The end date must not be before the start date.']);
        }

        $days = (int) round(($to->getTimestamp() - $from->getTimestamp()) / 86400) + 1;

        if ($days > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Please choose at most ' . self::MAX_DAYS . ' days at a time.']);
        }

        $start = $from->copy()->startOfDay()->timezone(config('app.timezone'));
        $end = $to->copy()->endOfDay()->timezone(config('app.timezone'));

        return [$start, $end, $from->format('Y-m-d'), $to->format('Y-m-d')];
    }

    /** The chosen option. Older links that only had the tick box still work. */
    private function scope(Request $request): string
    {
        $scope = $request->input('scope');

        if (! $scope && $request->boolean('only_open')) {
            return 'needs_review';
        }

        return $scope ?: self::DEFAULT_SCOPE;
    }

    /** Photos in the period that belong to the chosen option. */
    private function scopeQuery(Carbon $start, Carbon $end, string $scope): Builder
    {
        $query = PatronVerificationCapture::query()->whereBetween('created_at', [$start, $end]);

        return match ($scope) {
            'all' => $query,
            'system_verified' => $query->where('match_status', 'matched')->whereNull('review_status'),
            'staff_verified' => $query->where('review_status', 'ok'),
            'flagged' => $query->where('review_status', 'flagged'),
            'needs_review' => VerificationPhotos::needsReview($query),
            'not_checked' => $query->whereNull('match_status'),
            'no_reference' => $query->whereIn('match_status', ['no_reference', 'reference_unusable']),
            default => $query->where(function (Builder $q) {
                $q->whereIn('match_status', ['mismatch', 'no_face'])
                    ->orWhere(function (Builder $legacy) {
                        // Older photos that were never checked, where the kiosk itself saw no face
                        $legacy->whereNull('match_status')->where('face_detected', false);
                    })
                    ->orWhere('review_status', 'flagged');
            }),
        };
    }

    /** The system's result for one photo: matched, mismatch, no_face, no_reference or pending. */
    private function systemResult(PatronVerificationCapture $c): string
    {
        return match (true) {
            $c->match_status === 'matched' => 'matched',
            $c->match_status === 'mismatch' => 'mismatch',
            $c->match_status === 'no_face', $c->match_status === null && ! $c->face_detected => 'no_face',
            in_array($c->match_status, ['no_reference', 'reference_unusable'], true) => 'no_reference',
            default => 'pending',
        };
    }

    private function resultLabel(PatronVerificationCapture $c): string
    {
        return match (true) {
            $c->match_status === 'mismatch' => 'Does not match the Koha photo',
            $c->match_status === 'no_face' => 'No face found in the photo',
            $c->match_status === null && ! $c->face_detected => 'No face detected at the kiosk (not checked yet)',
            $c->match_status === 'matched' => 'Matches the Koha photo',
            $c->match_status === 'no_reference' => 'No Koha photo to compare with',
            $c->match_status === 'reference_unusable' => 'The Koha photo has no clear face',
            default => 'Not checked yet',
        };
    }

    private function reviewLabel(PatronVerificationCapture $c): string
    {
        return match ($c->review_status) {
            'ok' => 'Reviewed: OK',
            'flagged' => 'Flagged as a problem',
            default => 'Not reviewed yet',
        };
    }

    private function books(PatronVerificationCapture $c): string
    {
        $items = $c->transaction_items;

        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        if (! is_array($items) || ! $items) {
            return '';
        }

        return collect($items)->map(function ($item) {
            $title = $item['title'] ?? 'Title not available';
            $barcode = ! empty($item['barcode']) ? ' (' . $item['barcode'] . ')' : '';
            $failed = (array_key_exists('ok', $item) && ! $item['ok']) ? ' [not completed]' : '';

            return $title . $barcode . $failed;
        })->implode('; ');
    }

    /** @return array<int, string> */
    private function reviewerNames(Collection $rows): array
    {
        $ids = $rows->pluck('reviewed_by')->filter()->unique()->values();

        return $ids->isEmpty() ? [] : User::whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /** Stops a spreadsheet from treating a text cell as a formula. */
    private function safeCell(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    private function localTime(?Carbon $time, string $format): string
    {
        return $time ? $time->copy()->timezone(self::TIMEZONE)->format($format) : '';
    }

    /**
     * Counts for the chosen photos, in two parts that each add up to the total:
     * the system's result and the staff decision.
     *
     * @return array<string, int>
     */
    private function summary(Collection $rows): array
    {
        $system = $rows->countBy(fn ($c) => $this->systemResult($c));
        $staff = $rows->countBy(fn ($c) => $c->review_status ?? 'none');

        return [
            'total' => $rows->count(),
            'matched' => (int) $system->get('matched', 0),
            'mismatch' => (int) $system->get('mismatch', 0),
            'no_face' => (int) $system->get('no_face', 0),
            'no_reference' => (int) $system->get('no_reference', 0),
            'pending' => (int) $system->get('pending', 0),
            'ok' => (int) $staff->get('ok', 0),
            'flagged' => (int) $staff->get('flagged', 0),
            'none' => (int) $staff->get('none', 0),
            'open' => $rows->filter(fn ($c) => $c->review_status === null && in_array($this->systemResult($c), ['mismatch', 'no_face'], true))->count(),
        ];
    }

    public function index(Request $request): View
    {
        $this->ensureAccess($request);

        [$start, $end, $from, $to] = $this->range($request);

        // The numbers on the page cover every photo in the period. The choice of what to
        // include is made when the report or the CSV is opened.
        $rows = $this->scopeQuery($start, $end, 'all')
            ->get(['id', 'match_status', 'review_status', 'face_detected']);

        $counts = [];

        foreach (array_keys(self::SCOPES) as $key) {
            $counts[$key] = $this->scopeQuery($start, $end, $key)->count();
        }

        return view('patron-verification.report', [
            'from' => $from,
            'to' => $to,
            'scopes' => self::SCOPES,
            'mainScopes' => self::MAIN_SCOPES,
            'defaultChoice' => self::DEFAULT_CHOICE,
            'counts' => $counts,
            'summary' => $this->summary($rows),
            'maxDays' => self::MAX_DAYS,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $this->ensureAccess($request);

        [$start, $end, $from, $to] = $this->range($request);
        $scope = $this->scope($request);

        $rows = $this->scopeQuery($start, $end, $scope)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $reviewers = $this->reviewerNames($rows);

        Log::info(sprintf(
            'Verification report CSV (%s) exported by user %d for %s to %s (%d rows).',
            $scope,
            $request->user()->id,
            $from,
            $to,
            $rows->count()
        ));

        $filename = "verification-report-{$scope}-{$from}-to-{$to}.csv";

        return response()->streamDownload(function () use ($rows, $reviewers) {
            $out = fopen('php://output', 'w');

            // A UTF-8 marker, so Excel shows Bangla names correctly
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Date (Dhaka)', 'Time (Dhaka)', 'Patron', 'Card number', 'Action', 'Books',
                'Result', 'Match score', 'Review decision', 'Reviewed by', 'Reviewed at (Dhaka)',
                'Note', 'Photo page',
            ], ',', '"', '');

            foreach ($rows as $c) {
                fputcsv($out, [
                    $this->localTime($c->created_at, 'Y-m-d'),
                    $this->localTime($c->created_at, 'H:i'),
                    $this->safeCell($c->patron_name),
                    $this->safeCell($c->cardnumber),
                    ucfirst((string) $c->action),
                    $this->safeCell($this->books($c)),
                    $this->resultLabel($c),
                    $c->match_score !== null ? number_format((float) $c->match_score, 3, '.', '') : '',
                    $this->reviewLabel($c),
                    $this->safeCell($c->reviewed_by ? ($reviewers[$c->reviewed_by] ?? '') : ''),
                    $this->localTime($c->reviewed_at ? Carbon::parse($c->reviewed_at) : null, 'Y-m-d H:i'),
                    $this->safeCell($c->review_note),
                    route('patron-verification.history.show', $c),
                ], ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Request $request): View
    {
        $this->ensureAccess($request);

        [$start, $end, $from, $to] = $this->range($request);
        $scope = $this->scope($request);

        // Every matching photo is included. There is no limit on the number.
        $rows = $this->scopeQuery($start, $end, $scope)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $reviewers = $this->reviewerNames($rows);

        Log::info(sprintf(
            'Verification report (%s) printed by user %d for %s to %s (%d photos).',
            $scope,
            $request->user()->id,
            $from,
            $to,
            $rows->count()
        ));

        $entries = $rows->map(fn ($c) => [
            'capture' => $c,
            'when' => $this->localTime($c->created_at, 'd M Y, H:i'),
            'result' => $this->resultLabel($c),
            'kind' => $this->systemResult($c),
            'score' => $c->match_score !== null ? number_format((float) $c->match_score, 2) : null,
            'review' => $this->reviewLabel($c),
            'reviewer' => $c->reviewed_by ? ($reviewers[$c->reviewed_by] ?? null) : null,
            'reviewedAt' => $this->localTime($c->reviewed_at ? Carbon::parse($c->reviewed_at) : null, 'd M Y, H:i'),
            'books' => $this->books($c),
        ]);

        return view('patron-verification.report-print', [
            'entries' => $entries,
            'summary' => $this->summary($rows),
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'scopeLabel' => self::SCOPES[$scope][0],
            'generatedAt' => Carbon::now(self::TIMEZONE)->format('d M Y, H:i'),
            'generatedBy' => $request->user()->name,
        ]);
    }
}