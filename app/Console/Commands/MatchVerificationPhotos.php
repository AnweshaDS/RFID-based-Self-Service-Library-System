<?php

namespace App\Console\Commands;

use App\Models\PatronVerificationCapture;
use App\Services\FaceMatchService;
use Illuminate\Console\Command;

class MatchVerificationPhotos extends Command
{
    /**
     * If this many photos in a row cannot be checked, the problem is probably the matching
     * tool itself (not one damaged photo), so the run stops instead of trying them all.
     */
    private const MAX_FAILURES_IN_A_ROW = 5;

    protected $signature = 'verification:match
        {--limit=20 : Check at most this many photos in one run}
        {--all : Check photos again, even ones already checked}
        {--id= : Only this capture id}';

    protected $description = 'Compare kiosk verification photos with each patron\'s Koha photo and save the result';

    public function handle(FaceMatchService $matcher): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $query = PatronVerificationCapture::query()->orderByDesc('id');

        if (filled($this->option('id'))) {
            $query->where('id', (int) $this->option('id'));
        } elseif (! $this->option('all')) {
            $query->whereNull('match_status');
        }

        $checked = 0;
        $skipped = 0;
        $kohaDown = false;
        $failedIds = [];
        $failuresInARow = 0;

        foreach ($query->pluck('id') as $id) {
            if ($checked >= $limit) {
                break;
            }

            $capture = PatronVerificationCapture::find($id);

            if (! $capture) {
                continue;
            }

            $outcome = $matcher->check($capture);

            if ($outcome === 'skipped_no_file') {
                $skipped++;

                continue;
            }

            if ($outcome === 'skipped_koha') {
                $this->warn('Koha could not be reached for a reference photo. Stopping; try again later.');
                $kohaDown = true;

                break;
            }

            if ($outcome === 'failed') {
                // One damaged photo must not block every photo behind it. It keeps its
                // empty result (shown as "not checked yet") and is tried again next run.
                $failedIds[] = $capture->id;
                $failuresInARow++;
                $this->warn("#{$capture->id}: the matching tool could not check this photo. Moving on to the next one.");

                if ($failuresInARow >= self::MAX_FAILURES_IN_A_ROW) {
                    break;
                }

                continue;
            }

            $failuresInARow = 0;
            $checked++;
            $score = $capture->match_score !== null ? ' (' . number_format((float) $capture->match_score, 3) . ')' : '';
            $this->line("#{$capture->id} patron {$capture->patron_id} {$capture->cardnumber}: {$outcome}{$score}");
        }

        if ($checked > 0 || $failedIds === []) {
            $this->info("Checked {$checked} photo(s).");
        }

        if ($skipped > 0) {
            $this->warn("{$skipped} photo(s) skipped because their file is not on this computer.");
        }

        if ($failedIds !== [] && ! $kohaDown && ($checked === 0 || $failuresInARow >= self::MAX_FAILURES_IN_A_ROW)) {
            $this->error('The Python matching tool failed. See storage/logs/laravel.log for the reason.');

            return self::FAILURE;
        }

        if ($failedIds !== []) {
            $this->warn(count($failedIds) . ' photo(s) could not be checked and will be tried again (ids: ' . implode(', ', $failedIds) . ').');
        }

        return self::SUCCESS;
    }
}