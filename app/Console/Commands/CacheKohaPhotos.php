<?php

namespace App\Console\Commands;

use App\Models\PatronVerificationCapture;
use App\Services\KohaPhotoService;
use Illuminate\Console\Command;

class CacheKohaPhotos extends Command
{
    protected $signature = 'verification:cache-koha-photos
        {--patron= : Only this Koha patron id}
        {--refresh : Download again even when a recent copy exists}';

    protected $description = 'Download each patron\'s Koha photo once, shrink it, and keep a copy for the history page';

    public function handle(KohaPhotoService $photos): int
    {
        $only = $this->option('patron');

        if (filled($only)) {
            if (! ctype_digit((string) $only)) {
                $this->error('--patron must be a Koha patron id (digits only).');

                return self::FAILURE;
            }

            $ids = [(int) $only];
        } else {
            $ids = PatronVerificationCapture::query()
                ->distinct()
                ->pluck('patron_id')
                ->filter(fn ($id) => ctype_digit((string) $id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if (! $ids) {
            $this->warn('No patrons to process.');

            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            $start = microtime(true);
            $bytes = $photos->get($id, (bool) $this->option('refresh'));
            $seconds = round(microtime(true) - $start, 1);

            $this->line($bytes === null
                ? "patron {$id}: no photo in Koha, or Koha could not be reached [{$seconds}s]"
                : "patron {$id}: ready, " . number_format(strlen($bytes) / 1024, 0) . " KB [{$seconds}s]");
        }

        return self::SUCCESS;
    }
}