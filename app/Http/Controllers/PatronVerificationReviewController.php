<?php

namespace App\Http\Controllers;

use App\Models\PatronVerificationCapture;
use App\Models\User;
use App\Support\VerificationPhotos;
use Illuminate\Http\RedirectResponse;
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

        // The action, date and status filters limit which photos are counted and shown.
        $applyPhotoFilters = function ($query) use ($request) {
            if ($request->filled('action')) {
                $query->where('action', $request->input('action'));
            }
            if ($request->filled('date')) {
                $query->whereDate('created_at', $request->input('date'));
            }

            switch ($request->input('status')) {
                case 'needs_review':
                    VerificationPhotos::needsReview($query);
                    break;
                case 'flagged':
                    $query->where('review_status', 'flagged');
                    break;
                case 'reviewed_ok':
                    $query->where('review_status', 'ok');
                    break;
                case 'mismatch':
                    $query->where('match_status', 'mismatch');
                    break;
                case 'no_face':
                    $query->where(function ($q) {
                        $q->where('match_status', 'no_face')
                            ->orWhere(fn ($legacy) => $legacy->whereNull('match_status')->where('face_detected', false));
                    });
                    break;
                case 'unchecked':
                    $query->whereNull('match_status');
                    break;
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

        // How many photos need review for each patron on this page, whatever the card shows.
        $pageIds = $patrons->getCollection()->pluck('patron_id')->map(fn ($id) => (string) $id)->all();

        $warningQuery = PatronVerificationCapture::query()->whereIn('patron_id', $pageIds);
        $applyPhotoFilters($warningQuery);

        $warningCounts = VerificationPhotos::needsReview($warningQuery)
            ->selectRaw('patron_id, COUNT(*) as warning_total')
            ->groupBy('patron_id')
            ->pluck('warning_total', 'patron_id');

        $patrons->through(function ($row) use ($applyPhotoFilters, $expandedPatron, $photosPerCard, $warningCounts) {
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
                'warnings' => (int) ($warningCounts[$patronId] ?? 0),
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

    /** One photo, large, next to the patron's Koha photo, with the review controls. */
    public function show(Request $request, PatronVerificationCapture $capture): View
    {
        $this->ensureAccess($request);

        $items = $capture->transaction_items;

        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        $older = PatronVerificationCapture::where('patron_id', $capture->patron_id)
            ->where('id', '<', $capture->id)
            ->orderByDesc('id')
            ->first(['id']);

        $newer = PatronVerificationCapture::where('patron_id', $capture->patron_id)
            ->where('id', '>', $capture->id)
            ->orderBy('id')
            ->first(['id']);

        $reviewer = $capture->reviewed_by ? User::find($capture->reviewed_by) : null;

        return view('patron-verification.capture', [
            'capture' => $capture,
            'items' => is_array($items) ? $items : [],
            'older' => $older,
            'newer' => $newer,
            'reviewer' => $reviewer,
        ]);
    }

    public function review(Request $request, PatronVerificationCapture $capture): RedirectResponse
    {
        $this->ensureAccess($request);

        $data = $request->validate([
            'decision' => 'required|in:ok,flagged,reopen',
            'note' => 'nullable|string|max:500',
        ]);

        if ($data['decision'] === 'reopen') {
            $capture->forceFill([
                'review_status' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ])->save();

            $message = 'Photo re-opened for review.';
        } else {
            $note = trim((string) ($data['note'] ?? ''));

            $capture->forceFill([
                'review_status' => $data['decision'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $note !== '' ? $note : null,
            ])->save();

            $message = $data['decision'] === 'ok' ? 'Marked as OK.' : 'Flagged as a problem.';
        }

        return redirect()->route('patron-verification.history.show', $capture)->with('status', $message);
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