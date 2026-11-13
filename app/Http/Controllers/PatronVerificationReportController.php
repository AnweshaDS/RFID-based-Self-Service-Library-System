<?php

namespace App\Http\Controllers;

use App\Models\PatronVerificationCapture;
use App\Models\User;
use App\Services\FaceMatchService;
use App\Support\VerificationPhotos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatronVerificationReportController extends Controller
{
    private const TIMEZONE = 'Asia/Dhaka';

    /** Longest period one report may cover, in days. */
    private const MAX_DAYS = 31;

    /** Most photos one printable report shows. */
    private const MAX_PRINT_PHOTOS = 150;

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

    /** Photos with a warning in the period: no match, no face, or flagged by staff. */
    private function warningQuery(Carbon $start, Carbon $end, bool $onlyOpen): Builder
    {
        $query = PatronVerificationCapture::query()->whereBetween('created_at', [$start, $end]);

        if ($onlyOpen) {
            return VerificationPhotos::needsReview($query);
        }

        return $query->where(function (Builder $q) {
            $q->whereIn('match_status', FaceMatchService::WARNING_STATUSES)
                ->orWhere(function (Builder $legacy) {
                    // Older photos that were never checked, where the kiosk itself saw no face
                    $legacy->whereNull('match_status')->where('face_detected', false);
                })
                ->orWhere('review_status', 'flagged');
        });
    }

    private function kind(PatronVerificationCapture $c): string
    {
        if ($c->match_status === 'mismatch') {
            return 'mismatch';
        }

        if ($c->match_status === 'no_face' || ($c->match_status === null && ! $c->face_detected)) {
            return 'no_face';
        }

        return 'other';
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

    /** @return array<string, int> */
    private function summary(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'mismatch' => $rows->filter(fn ($c) => $this->kind($c) === 'mismatch')->count(),
            'no_face' => $rows->filter(fn ($c) => $this->kind($c) === 'no_face')->count(),
            'flagged' => $rows->where('review_status', 'flagged')->count(),
            'reviewed_ok' => $rows->where('review_status', 'ok')->count(),
            'open' => $rows->filter(fn ($c) => $c->review_status === null && in_array($this->kind($c), ['mismatch', 'no_face'], true))->count(),
        ];
    }

    public function index(Request $request): View
    {
        $this->ensureAccess($request);

        [$start, $end, $from, $to] = $this->range($request);
        $onlyOpen = $request->boolean('only_open');

        $rows = $this->warningQuery($start, $end, $onlyOpen)
            ->get(['id', 'match_status', 'review_status', 'face_detected']);

        return view('patron-verification.report', [
            'from' => $from,
            'to' => $to,
            'onlyOpen' => $onlyOpen,
            'summary' => $this->summary($rows),
            'maxDays' => self::MAX_DAYS,
            'maxPhotos' => self::MAX_PRINT_PHOTOS,
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $this->ensureAccess($request);

        [$start, $end, $from, $to] = $this->range($request);
        $onlyOpen = $request->boolean('only_open');

        $rows = $this->warningQuery($start, $end, $onlyOpen)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $reviewers = $this->reviewerNames($rows);

        Log::info(sprintf(
            'Verification warnings CSV exported by user %d for %s to %s (%d rows).',
            $request->user()->id,
            $from,
            $to,
            $rows->count()
        ));

        $filename = "verification-warnings-{$from}-to-{$to}.csv";

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
        $onlyOpen = $request->boolean('only_open');

        $query = $this->warningQuery($start, $end, $onlyOpen);

        $summary = $this->summary((clone $query)->get(['id', 'match_status', 'review_status', 'face_detected']));

        $rows = $query->orderBy('created_at')->orderBy('id')->limit(self::MAX_PRINT_PHOTOS)->get();
        $reviewers = $this->reviewerNames($rows);

        Log::info(sprintf(
            'Verification warnings report printed by user %d for %s to %s (%d of %d shown).',
            $request->user()->id,
            $from,
            $to,
            $rows->count(),
            $summary['total']
        ));

        $entries = $rows->map(fn ($c) => [
            'capture' => $c,
            'when' => $this->localTime($c->created_at, 'd M Y, H:i'),
            'result' => $this->resultLabel($c),
            'kind' => $this->kind($c),
            'score' => $c->match_score !== null ? number_format((float) $c->match_score, 2) : null,
            'review' => $this->reviewLabel($c),
            'reviewer' => $c->reviewed_by ? ($reviewers[$c->reviewed_by] ?? null) : null,
            'reviewedAt' => $this->localTime($c->reviewed_at ? Carbon::parse($c->reviewed_at) : null, 'd M Y, H:i'),
            'books' => $this->books($c),
        ]);

        return view('patron-verification.report-print', [
            'entries' => $entries,
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
            'onlyOpen' => $onlyOpen,
            'shown' => $rows->count(),
            'generatedAt' => Carbon::now(self::TIMEZONE)->format('d M Y, H:i'),
            'generatedBy' => $request->user()->name,
        ]);
    }
}