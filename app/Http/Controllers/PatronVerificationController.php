<?php

namespace App\Http\Controllers;

use App\Models\PatronVerificationCapture;
use App\Services\KohaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatronVerificationController extends Controller
{
    public function __construct(protected KohaService $kohaService)
    {
    }

    protected function ensureAccess(Request $request): void
    {
        $user = $request->user()->load('roles');
        $menuConfig = config('koha_menu', []);
        $hasPatronAccess = $user->roles
            ->flatMap(fn ($role) => $menuConfig[$role->name]['menu'] ?? [])
            ->contains('label', 'Patrons');

        abort_unless($hasPatronAccess, 403);
    }

    public function show(Request $request): View
    {
        $this->ensureAccess($request);

        return view('patron-verification.show', ['patron' => null, 'photoAvailable' => false, 'latestCapture' => null]);
    }

    public function search(Request $request): View
    {
        $this->ensureAccess($request);

        $request->validate(['cardnumber' => 'required|string']);

        $patron = $this->kohaService->getPatronByCardnumber(trim($request->input('cardnumber')));

        $photoAvailable = false;
        $latestCapture = null;

        if ($patron) {
            try {
                $photoAvailable = $this->kohaService->checkPatronImageExists((int) $patron['patron_id']);
            } catch (\Throwable $e) {
                $photoAvailable = false;
            }

            $latestCapture = PatronVerificationCapture::where('patron_id', (string) $patron['patron_id'])
                ->latest()
                ->first();
        }

        return view('patron-verification.show', [
            'patron' => $patron,
            'photoAvailable' => $photoAvailable,
            'latestCapture' => $latestCapture,
        ]);
    }

    public function photo(Request $request, int $patronId)
    {
        $this->ensureAccess($request);

        $image = $this->kohaService->getPatronImage($patronId);

        if (! $image) {
            abort(404);
        }

        return response($image)->header('Content-Type', 'image/jpeg');
    }
}