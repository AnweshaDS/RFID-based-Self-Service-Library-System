<?php

namespace App\Console\Commands;

use App\Models\PatronVerificationCapture;
use App\Support\RetentionSettings;
use App\Support\VerificationPhotos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneVerificationPhotos extends Command
{
    protected $signature = 'verification:prune
        {--scheduled : Use the retention times an admin saved on the Photo Retention page (the daily job uses this)}
        {--card= : Only photos for this card number}
        {--older-than= : Only photos older than this many days}
        {--include-missing : Also delete records whose photo file is not on this computer}
        {--include-protected : Also delete photos that still need review or were flagged}
        {--force : Really delete. Without this the command only shows what it would delete}';

    protected $description = 'Delete kiosk verification photos (the image file and its database record)';

    public function handle(): int
    {
        $card = $this->option('card');
        $days = $this->option('older-than');

        if ($this->option('scheduled')) {
            $days = (string) RetentionSettings::normalDays();
        }

        if (blank($card) and blank($days)) {
            $this->error('Give --scheduled, --card=CARDNUMBER, --older-than=DAYS, or a combination.');

            return self::FAILURE;
        }

        $query = PatronVerificationCapture::query();

        if (filled($card)) {
            $query->where('cardnumber', $card);
        }

        $kept = 0;
        $flaggedDays = null;

        if (filled($days)) {
            if (! ctype_digit((string) $days)) {
                $this->error('--older-than must be a whole number of days.');

                return self::FAILURE;
            }

            $normalDays = (int) $days;

            if ($this->option('include-protected')) {
                $query->where('created_at', '<', now()->subDays($normalDays));
            } else {
                // Photos that still need a staff decision, or were flagged, are kept longer.
                $flaggedDays = max($normalDays, RetentionSettings::flaggedDays());

                $kept = VerificationPhotos::awaitingOrFlagged(clone $query)
                    ->where('created_at', '<', now()->subDays($normalDays))
                    ->where('created_at', '>=', now()->subDays($flaggedDays))
                    ->count();

                VerificationPhotos::expired($query, $normalDays, $flaggedDays);
            }
        }

        $captures = $query->orderBy('id')->get();
        $disk = Storage::disk(config('kiosk.photo_disk'));
        $force = (bool) $this->option('force');
        $includeMissing = (bool) $this->option('include-missing');

        $handled = 0;
        $skipped = 0;

        foreach ($captures as $capture) {
            $path = $capture->photo_path;
            $fileHere = filled($path) ? $disk->exists($path) : false;

            if (! $fileHere and ! $includeMissing) {
                $skipped++;

                continue;
            }

            $this->line(
                ($force ? 'Deleting' : 'Would delete')
                . ' #' . $capture->id
                . ' card ' . $capture->cardnumber
                . ' ' . $capture->action
                . ' ' . $capture->created_at->format('Y-m-d H:i')
                . ($fileHere ? '' : ' (file not found on this computer)')
            );

            if ($force) {
                if ($fileHere) {
                    $disk->delete($path);
                }

                $capture->delete();
            }

            $handled++;
        }

        $this->newLine();
        $this->info('Matched ' . $captures->count() . ' record(s).');
        $this->info(($force ? 'Deleted ' : 'Would delete ') . $handled . ' record(s) and their photo files.');

        if ($kept > 0) {
            $this->info("Kept {$kept} photo(s) that still need review or were flagged (they are kept up to {$flaggedDays} days).");
        }

        if ($skipped > 0) {
            $this->warn($skipped . ' record(s) skipped because their photo file is not on this computer. Run the command on the computer that has the files, or add --include-missing to delete just the records.');
        }

        if (! $force and $handled > 0) {
            $this->warn('Dry run only. Nothing was deleted. Add --force to delete.');
        }

        return self::SUCCESS;
    }
}