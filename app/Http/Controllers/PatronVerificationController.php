<?php

namespace App\Http\Controllers;

use App\Services\KohaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatronVerificationController extends Controller
{
    public function __construct(protected KohaService $kohaService)
    {
    }

    public function show(): View
    {
        return view('patron-verification.show', ['patron' => null, 'photoAvailable' => false]);
    }

    public function search(Request $request): View
    {
        $request->validate(['cardnumber' => 'required|string']);

        $patron = $this->kohaService->getPatronByCardnumber(trim($request->input('cardnumber')));

        $photoAvailable = false;
        if ($patron) {
            try {
                $photoAvailable = $this->kohaService->checkPatronImageExists((int) $patron['patron_id']);
            } catch (\Throwable $e) {
                $photoAvailable = false;
            }
        }

        return view('patron-verification.show', [
            'patron' => $patron,
            'photoAvailable' => $photoAvailable,
        ]);
    }

    public function photo(int $patronId)
    {
        $image = $this->kohaService->getPatronImage($patronId);

        if (! $image) {
            abort(404);
        }

        return response($image)->header('Content-Type', 'image/jpeg');
    }
}