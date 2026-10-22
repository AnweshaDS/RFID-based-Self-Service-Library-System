<?php

namespace App\Http\Controllers;

use App\Models\PatronVerificationCapture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KioskVerificationController extends Controller
{
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

        $imageData = $request->input('image');
        if (! preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            return response()->json(['error' => 'Invalid image data.'], 422);
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $decoded = base64_decode(substr($imageData, strpos($imageData, ',') + 1));

        if ($decoded === false) {
            return response()->json(['error' => 'Could not decode image.'], 422);
        }

        $patron = Session::get('kiosk_patron');
        $filename = 'verification-' . ($patron['patron_id'] ?? 'unknown') . '-' . now()->format('Ymd-His') . '-' . Str::random(6) . '.' . $extension;
        $path = 'patron-verification-captures/' . $filename;
        Storage::disk('local')->put($path, $decoded);

        $capture = PatronVerificationCapture::create([
            'patron_id' => (string) ($patron['patron_id'] ?? ''),
            'cardnumber' => $patron['cardnumber'] ?? null,
            'patron_name' => $patron['name'] ?? null,
            'action' => $action,
            'barcode' => $request->input('barcode'),
            'photo_path' => $path,
            'face_detected' => $request->boolean('face_detected', true),
        ]);

        return response()->json(['ok' => true, 'capture_id' => $capture->id]);
    }
}