<?php

namespace App\Http\Controllers;

use App\Models\PatronVerificationCapture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KioskVerificationController extends Controller
{
    /** A second photo for the same action within this many seconds reuses the first one. */
    protected const DUPLICATE_WINDOW_SECONDS = 10;

    /** Largest photo the kiosk may upload, in bytes. */
    protected const MAX_IMAGE_BYTES = 3145728;

    public function store(Request $request, string $action)
    {
        abort_unless(in_array($action, ['borrow', 'return'], true), 404);

        if (! Session::has('kiosk_patron')) {
            return response()->json(['error' => 'No active patron session.'], 403);
        }

        $request->validate([
            'image' => 'required|string',
            'face_detected' => 'nullable|boolean',
        ]);

        $patron = Session::get('kiosk_patron');
        $patronId = (string) ($patron['patron_id'] ?? '');

        // The kiosk sometimes sends the same photo twice at once; keep only the first.
        $existingId = Session::get("kiosk_verification_capture_{$action}");

        if ($existingId) {
            $existing = PatronVerificationCapture::find($existingId);

            if ($existing
                && $existing->patron_id === $patronId
                && $existing->created_at->gt(now()->subSeconds(self::DUPLICATE_WINDOW_SECONDS))) {
                return response()->json(['ok' => true, 'capture_id' => $existing->id]);
            }
        }

        $imageData = (string) $request->input('image');

        if (! preg_match('/^data:image\/(?:jpeg|jpg|png);base64,/', $imageData)) {
            return response()->json(['error' => 'Invalid image data.'], 422);
        }

        $decoded = base64_decode(substr($imageData, strpos($imageData, ',') + 1), true);

        if ($decoded === false || $decoded === '' || strlen($decoded) > self::MAX_IMAGE_BYTES) {
            return response()->json(['error' => 'Could not decode image.'], 422);
        }

        // The bytes must really be a JPEG or PNG, whatever the data URL claims.
        $info = @getimagesizefromstring($decoded);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return response()->json(['error' => 'Invalid image data.'], 422);
        }

        $extension = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        $filename = 'verification-' . ($patronId !== '' ? $patronId : 'unknown') . '-' . now()->format('Ymd-His') . '-' . Str::random(6) . '.' . $extension;
        $path = 'patron-verification-captures/' . $filename;

        if (! Storage::disk(config('kiosk.photo_disk'))->put($path, $decoded)) {
            return response()->json(['error' => 'Could not save the photo.'], 500);
        }

        $capture = PatronVerificationCapture::create([
            'patron_id' => $patronId,
            'cardnumber' => $patron['cardnumber'] ?? null,
            'patron_name' => $patron['name'] ?? null,
            'action' => $action,
            'barcode' => $request->input('barcode'),
            'photo_path' => $path,
            'face_detected' => $request->boolean('face_detected', true),
        ]);

        Session::put("kiosk_verification_capture_{$action}", $capture->id);

        return response()->json(['ok' => true, 'capture_id' => $capture->id]);
    }
}