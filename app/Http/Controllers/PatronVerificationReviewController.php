<?php

namespace App\Http\Controllers;

use App\Models\PatronVerificationCapture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PatronVerificationReviewController extends Controller
{
    protected function ensureAccess(Request $request): void
    {
        $user = $request->user()->load('roles');
        $menuConfig = config('koha_menu', []);
        $hasPatronAccess = $user->roles
            ->flatMap(fn ($role) => $menuConfig[$role->name]['menu'] ?? [])
            ->contains('label', 'Patrons');

        abort_unless($hasPatronAccess, 403);
    }

    public function index(Request $request): View
    {
        $this->ensureAccess($request);

        $query = PatronVerificationCapture::query()->latest();

        if ($request->filled('cardnumber')) {
            $query->where('cardnumber', 'like', '%' . $request->input('cardnumber') . '%');
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $captures = $query->paginate(20)->withQueryString();

        return view('patron-verification.history', ['captures' => $captures]);
    }

    public function photo(Request $request, PatronVerificationCapture $capture)
    {
        $this->ensureAccess($request);

        if (! Storage::disk('local')->exists($capture->photo_path)) {
            abort(404);
        }

        return response(Storage::disk('local')->get($capture->photo_path))
            ->header('Content-Type', 'image/jpeg');
    }
}