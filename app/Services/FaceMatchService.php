<?php

namespace App\Services;

use App\Models\PatronVerificationCapture;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class FaceMatchService
{
    /** Results that count as a warning for staff. */
    public const WARNING_STATUSES = ['mismatch', 'no_face'];

    public function __construct(private KohaPhotoService $kohaPhotos)
    {
    }

    /**
     * Compare one kiosk photo with the patron's Koha photo and save the result.
     *
     * Returns the saved status (matched, mismatch, no_face, no_reference,
     * reference_unusable), or one of: skipped_no_file (the photo file is not on this
     * computer), skipped_koha (Koha could not answer, try again later), failed.
     */
    public function check(PatronVerificationCapture $capture): string
    {
        $disk = Storage::disk(config('kiosk.photo_disk'));

        if (blank($capture->photo_path) || ! $disk->exists($capture->photo_path)) {
            return 'skipped_no_file';
        }

        $patronId = (int) $capture->patron_id;
        $reference = $this->kohaPhotos->lookup($patronId);

        if ($reference['state'] === 'unavailable') {
            return 'skipped_koha';
        }

        if ($reference['state'] === 'none') {
            return $this->save($capture, 'no_reference', null);
        }

        $referencePath = $this->kohaPhotos->localPath($patronId);

        if ($referencePath === null) {
            return 'skipped_koha';
        }

        $result = $this->run($referencePath, $disk->path($capture->photo_path));

        if ($result === null) {
            return 'failed';
        }

        return $this->save($capture, $result['status'], $result['score'] ?? null);
    }

    private function save(PatronVerificationCapture $capture, string $status, ?float $score): string
    {
        $capture->forceFill([
            'match_status' => $status,
            'match_score' => $score,
            'matched_at' => now(),
        ])->save();

        return $status;
    }

    /** Runs the Python tool and returns its answer, or null when it did not work. */
    private function run(string $referencePath, string $photoPath): ?array
    {
        try {
            $process = Process::timeout(90)->run([
                (string) config('kiosk.face_python'),
                (string) config('kiosk.face_script'),
                $referencePath,
                $photoPath,
                '--threshold',
                (string) config('kiosk.face_match_threshold'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Face match could not start: ' . $e->getMessage());

            return null;
        }

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $process->output())),
            fn ($line) => $line !== ''
        ));
        $json = $lines ? json_decode(end($lines), true) : null;
        $allowed = ['matched', 'mismatch', 'no_face', 'reference_unusable'];

        if (! $process->successful() || ! is_array($json) || ! in_array($json['status'] ?? null, $allowed, true)) {
            Log::warning('Face match failed: ' . trim($process->errorOutput() . ' ' . $process->output()));

            return null;
        }

        return $json;
    }
}