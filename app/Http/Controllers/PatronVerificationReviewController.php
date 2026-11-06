<?php

namespace App\Http\Controllers;

use App\Models\PatronVerificationCapture;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PatronVerificationReviewController extends Controller
{
    /** How many patron cards appear on one page. */
    protected const CARDS_PER_PAGE = 8;

    /** Kiosk photos each patron card shows before "Show all" (set in config/kiosk.php). */
    protected function photosPerCard(): int
    {
        return max(1, (int) config('kiosk.photos_per_card', 10));
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

    public function index(Request $request): View
    {
        $this->ensureAccess($request);

        $photosPerCard = $this->photosPerCard();
        $expandedPatron = (string) $request->input('show_all', '');

        // The action and date filters limit which photos are counted and shown.
        $applyPhotoFilters = function ($query) use ($request) {
            if ($request->filled('action')) {
                $query->where('action', $request->input('action'));
            }
            if ($request->filled('date')) {
                $query->whereDate('created_at', $request->input('date'));
            }
        };

        // One row per patron, newest activity first. The card number filter finds patrons.
        $patronQuery = PatronVerificationCapture::query()
            ->selectRaw('patron_id, MAX(created_at) as last_at, COUNT(*) as total')
            ->groupBy('patron_id')
            ->orderByDesc('last_at');

        if ($request->filled('cardnumber')) {
            $patronQuery->where('cardnumber', 'like', '%' . $request->input('cardnumber') . '%');
        }

        $applyPhotoFilters($patronQuery);

        $patrons = $patronQuery->paginate(self::CARDS_PER_PAGE)->withQueryString();

        $patrons->through(function ($row) use ($applyPhotoFilters, $expandedPatron, $photosPerCard) {
            $patronId = (string) $row->patron_id;
            $expanded = $expandedPatron !== '' && $expandedPatron === $patronId;

            $photoQuery = PatronVerificationCapture::query()
                ->where('patron_id', $patronId);

            $applyPhotoFilters($photoQuery);

            $photoQuery->latest()->orderByDesc('id');

            if (! $expanded) {
                $photoQuery->limit($photosPerCard);
            }

            $captures = $photoQuery->get();
            $newest = $captures->first();

            return [
                'patron_id' => $patronId,
                'name' => $newest ? $newest->patron_name : null,
                'cardnumber' => $newest ? $newest->cardnumber : null,
                'total' => (int) $row->total,
                'last_at' => Carbon::parse($row->last_at),
                'captures' => $captures,
                'expanded' => $expanded,
            ];
        });

        return view('patron-verification.history', [
            'patrons' => $patrons,
            'photosPerCard' => $photosPerCard,
        ]);
    }

    public function photo(Request $request, PatronVerificationCapture $capture)
    {
        $this->ensureAccess($request);

        $disk = Storage::disk(config('kiosk.photo_disk'));

        if (blank($capture->photo_path) || ! $disk->exists($capture->photo_path)) {
            abort(404);
        }

        $bytes = $disk->get($capture->photo_path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        return response($bytes)
            ->header('Content-Type', is_string($mime) && str_starts_with($mime, 'image/') ? $mime : 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=86400');
    }
}