<?php

namespace App\Console\Commands;

use App\Models\PatronVerificationCapture;
use App\Services\FaceMatchService;
use Illuminate\Console\Command;

class MatchVerificationPhotos extends Command
{
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

                break;
            }

            if ($outcome === 'failed') {
                $this->error('The Python matching tool failed. See storage/logs/laravel.log for the reason.');

                return self::FAILURE;
            }

            $checked++;
            $score = $capture->match_score !== null ? ' (' . number_format((float) $capture->match_score, 3) . ')' : '';
            $this->line("#{$capture->id} patron {$capture->patron_id} {$capture->cardnumber}: {$outcome}{$score}");
        }

        $this->info("Checked {$checked} photo(s).");

        if ($skipped > 0) {
            $this->warn("{$skipped} photo(s) skipped because their file is not on this computer.");
        }

        return self::SUCCESS;
    }
}