<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KohaPhotoService
{
    /** Longest side of the saved copy in pixels: plenty for staff and for face matching. */
    private const MAX_SIDE = 800;

    private const JPEG_QUALITY = 85;

    /** Download a stored photo again after this many days, in case it changed in Koha. */
    private const REFRESH_DAYS = 7;

    /** Ask Koha again after this many hours when it had no photo for a patron. */
    private const RETRY_MISSING_HOURS = 1;

    public function __construct(private KohaService $koha)
    {
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('kiosk.photo_disk'));
    }

    private function photoPath(int $patronId): string
    {
        return "koha-photos/{$patronId}.jpg";
    }

    private function missingPath(int $patronId): string
    {
        return "koha-photos/{$patronId}.none";
    }

    /**
     * The patron's Koha photo as JPEG bytes, from the saved copy when it is recent.
     * Returns null when Koha has no photo for the patron, or cannot be reached.
     */
    public function get(int $patronId, bool $forceRefresh = false): ?string
    {
        if ($patronId <= 0) {
            return null;
        }

        $disk = $this->disk();
        $path = $this->photoPath($patronId);
        $missing = $this->missingPath($patronId);

        if (! $forceRefresh) {
            if ($disk->exists($path)
                && $disk->lastModified($path) > now()->subDays(self::REFRESH_DAYS)->getTimestamp()) {
                return $disk->get($path);
            }

            if ($disk->exists($missing)
                && $disk->lastModified($missing) > now()->subHours(self::RETRY_MISSING_HOURS)->getTimestamp()) {
                return null;
            }
        }

        // The first download of a large photo over a slow link can take a while.
        @set_time_limit(300);

        try {
            $original = $this->koha->getPatronImage($patronId);
        } catch (\Throwable $e) {
            Log::warning("Koha photo download failed for patron {$patronId}: " . $e->getMessage());

            // Koha could not be reached: use the older copy if there is one,
            // and do not mark the patron as having no photo.
            return $disk->exists($path) ? $disk->get($path) : null;
        }

        if ($original === null || $original === '') {
            $disk->delete($path);
            $disk->put($missing, (string) time());

            return null;
        }

        $jpeg = $this->shrink($original);

        if ($jpeg === null) {
            Log::warning("Koha photo for patron {$patronId} could not be read as an image.");

            return $disk->exists($path) ? $disk->get($path) : null;
        }

        $disk->put($path, $jpeg);
        $disk->delete($missing);

        return $jpeg;
    }

    private function shrink(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        @ini_set('memory_limit', '768M');

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        $image = $this->applyExifOrientation($image, $bytes);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // White background, so a transparent PNG does not turn black as a JPEG.
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }

    /** Phone photos are often stored sideways with a rotation flag; turn them upright. */
    private function applyExifOrientation(\GdImage $image, string $bytes): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'kph');

        if ($tmp === false) {
            return $image;
        }

        $exif = false;

        try {
            file_put_contents($tmp, $bytes);
            $exif = @exif_read_data($tmp);
        } catch (\Throwable $e) {
            $exif = false;
        } finally {
            @unlink($tmp);
        }

        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        return $rotated instanceof \GdImage ? $rotated : $image;
    }
}