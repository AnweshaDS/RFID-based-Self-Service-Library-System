<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Services\KohaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\RfidCard;
use Illuminate\Support\Facades\Cache;
use App\Models\BookTag;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class KioskController extends Controller
{
    protected KohaService $kohaService;
    protected ActivityLogService $activityLogService;

    public function __construct(KohaService $kohaService, ActivityLogService $activityLogService)
    {
        $this->kohaService = $kohaService;
        $this->activityLogService = $activityLogService;
    }

    public function welcome()
    {
        return view('kiosk.welcome');
    }
public function scan(Request $request)
{
    $expected = (string) config('services.kiosk.token');
    abort_unless(
        $expected !== '' && hash_equals($expected, (string) $request->header('X-Kiosk-Token')),
        401
    );

    $uid = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $request->input('uid')));

    if ($uid === '') {
        return response()->json(['ok' => false, 'message' => 'Invalid UID'], 422);
    }

    $card = RfidCard::where('uid', $uid)->where('active', true)->first();
    if ($card) {
        Cache::put('kiosk.pending_scan', [
            'id' => uniqid(),
            'type' => 'patron',
            'cardnumber' => $card->cardnumber,
        ], 5);

        return response()->json(['ok' => true, 'type' => 'patron']);
    }

    $book = BookTag::where('uid', $uid)->where('active', true)->first();
    if ($book) {
        Cache::put('kiosk.pending_scan', [
            'id' => uniqid(),
            'type' => 'book',
            'barcode' => $book->barcode,
        ], 15);

        return response()->json(['ok' => true, 'type' => 'book']);
    }

    Cache::put('kiosk.pending_scan', [
        'id' => uniqid(),
        'type' => 'error',
        'error' => 'Tag not registered or inactive.',
    ], 15);

    return response()->json(['ok' => false, 'message' => 'Tag not registered'], 404);
}

public function pollScan(Request $request)
{
    $wanted = $request->query('type');   // 'patron', 'book', or 'none' (already logged in)

    $scan = Cache::get('kiosk.pending_scan');
    if (! $scan) {
        return response()->json(['idle' => true]);
    }

    Cache::forget('kiosk.pending_scan');   // each scan is used once

    if (($scan['type'] ?? '') === 'error') {
        return response()->json($scan);
    }

    if ($wanted === 'none') {
        return response()->json([
            'id' => $scan['id'],
            'error' => ($scan['type'] ?? '') === 'patron'
                ? 'You are already logged in. Please log out first to use a different card.'
                : 'Open Borrow or Return first, then tap the book.',
        ]);
    }

    if (($scan['type'] ?? '') !== $wanted) {
        return response()->json([
            'id' => $scan['id'],
            'error' => $wanted === 'book'
                ? 'Please scan a book, not a card.'
                : 'Please scan your card, not a book.',
        ]);
    }

    return response()->json($scan);
}
public function showReceiptView()
{
    $receipt = Session::get('kiosk_last_receipt');

    if (! $receipt) {
        return redirect()->route('kiosk.dashboard');
    }

    return view('kiosk.receipt-view', ['receipt' => $receipt]);
}
    public function enterByCardnumber(Request $request)
{
    $request->validate(['cardnumber' => 'required|string']);

    $cardnumber = trim($request->input('cardnumber'));

    try {
        $kohaPatron = $this->kohaService->getPatronByCardnumber($cardnumber);

        if (! $kohaPatron) {
            $this->activityLogService->log(
                action: 'manual_entry',
                status: 'failed',
                message: 'Patron not found.'
            );

            return back()->withErrors([
                'cardnumber' => 'Patron not found.',
            ]);
        }

        $patronId = (int) ($kohaPatron['patron_id'] ?? 0);
        $checkouts = $this->kohaService->getPatronCheckouts($patronId);
    } catch (\Throwable $e) {
\Illuminate\Support\Facades\Log::error('Koha checkout failed: ' . $e->getMessage());
        $this->activityLogService->log(
            action: 'manual_entry',
            status: 'failed',
            message: 'Koha service unavailable. Please try again later.'
        );

        return back()->withErrors([
            'cardnumber' => 'Koha service unavailable. Please try again later.',
        ]);
    }

    $firstname = $kohaPatron['firstname'] ?? '';
    $surname = $kohaPatron['surname'] ?? '';
    $name = trim("{$firstname} {$surname}");
    if (empty($name)) {
        $name = $kohaPatron['cardnumber'] ?? 'Patron';
    }

    $borrowedBooks = $this->formatCheckouts($checkouts);

    $patronIdStr = (string) ($kohaPatron['patron_id'] ?? $kohaPatron['cardnumber'] ?? '');

    $patron = [
        'patron_id' => $patronIdStr,
        'cardnumber' => $kohaPatron['cardnumber'] ?? '',
	'category_code' => $kohaPatron['category_id'] ?? $kohaPatron['categorycode'] ?? null,
        'name' => $name,
        'status' => 'Active',
        'borrowed_books' => $borrowedBooks,
        'recent_activity' => [],
    ];

    Session::regenerate();
    Cache::forget('kiosk.pending_scan');
    Session::put('kiosk_patron', $patron);
    Session::put('kiosk_last_activity', now());

    $this->activityLogService->log(
        action: 'manual_entry',
        status: 'success',
        patronId: $patronIdStr,
        message: "Patron {$name} entered successfully."
    );

    return redirect()->route('kiosk.dashboard');
}




    public function dashboard()
    {
        $patron = Session::get('kiosk_patron');

        if (! $patron) {
            return redirect()->route('kiosk.welcome');
        }

                $dueSoonCount = 0;
        $overdueCount = 0;

        foreach ($patron['borrowed_books'] ?? [] as $book) {
            if (empty($book['due'])) {
                continue;
            }

            try {
                $due = \Illuminate\Support\Carbon::parse($book['due']);
            } catch (\Throwable $e) {
                continue;
            }

            if ($due->copy()->endOfDay()->isPast()) {
                $overdueCount++;
            } elseif ($due->lte(now()->addDays(3))) {
                $dueSoonCount++;
            }
        }

        $patronId = (string) ($patron['patron_id'] ?? '');

        $recentActivities = ActivityLog::where('patron_id', $patronId)
    ->where('status', 'success')
    ->whereIn('action', ['borrow', 'return'])
    ->latest()
    ->take(10)
    ->get();

        return view('kiosk.dashboard', [
            'patron' => $patron,
            'dueSoonCount' => $dueSoonCount,
'overdueCount' => $overdueCount,
            'recentActivities' => $recentActivities,
        ]);
    }

    public function printReceipt()
{
    $receipt = Session::get('kiosk_last_receipt');

    if (! $receipt) {
        return redirect()->route('kiosk.dashboard');
    }

    return view('kiosk.receipt', ['receipt' => $receipt]);
}

public function receiptPdf()
{
    $receipt = Session::get('kiosk_last_receipt');

    if (! $receipt) {
        abort(404);
    }

    // 58mm width, matching the Xprinter XP-58IIL. Height is a fixed, generous
    // allowance for a prototype - DomPDF needs a fixed page size, so this isn't
    // true continuous-roll printing. That would need raw ESC/POS output instead
    // of a PDF, which is a bigger change for later if needed.
    $widthMm = 58;
    $heightMm = 120;
    $widthPt = $widthMm * 2.8346;
    $heightPt = $heightMm * 2.8346;

    $pdf = Pdf::loadView('kiosk.receipt-pdf', ['receipt' => $receipt])
        ->setPaper([0, 0, $widthPt, $heightPt]);

    $pdf->render();
    $output = $pdf->output();

    $filename = 'receipt-' . ($receipt['patron_id'] ?? 'unknown') . '-' . now()->format('Ymd-His') . '.pdf';

    Storage::disk('local')->put('receipts/' . $filename, $output);

    return response($output, 200)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
}

    public function showBorrow()
    {
        if (! Session::has('kiosk_patron')) {
            return redirect()->route('kiosk.welcome')->withErrors([
                'uid' => 'Please scan your RFID card first.',
            ]);
        }

        return view('kiosk.borrow', [
            'patron' => Session::get('kiosk_patron'),
        ]);
    }

    public function lookupBorrowItem(Request $request)
    {
        if (! Session::has('kiosk_patron')) {
            return redirect()->route('kiosk.welcome')->withErrors([
                'uid' => 'Please scan your RFID card first.',
            ]);
        }

        $request->validate(['barcode' => 'required|string']);

        $barcode = trim($request->input('barcode'));
        $patron = Session::get('kiosk_patron');
        $patronId = $patron['patron_id'] ?? null;

        try {
            $item = $this->kohaService->getItemByBarcode($barcode);
        } catch (\Throwable $e) {
            $this->activityLogService->log(
                action: 'borrow',
                status: 'failed',
                patronId: $patronId,
                message: 'Unable to retrieve book information. Please try again.',
                barcode: $barcode
            );

            return back()->withErrors([
                'barcode' => 'Unable to retrieve book information. Please try again.',
            ]);
        }

        if (! $item) {
            $this->activityLogService->log(
                action: 'borrow',
                status: 'failed',
                patronId: $patronId,
                message: 'Book not found.',
                barcode: $barcode
            );

            return back()->withErrors([
                'barcode' => 'Book not found.',
            ]);
        }

        if ($this->isItemUnavailable($item)) {
            $itemId = (int) ($item['item_id'] ?? 0);

            $this->activityLogService->log(
                action: 'borrow',
                status: 'failed',
                patronId: $patronId,
                message: 'This book is currently unavailable.',
                barcode: $barcode,
                itemId: $itemId
            );

            return back()->withErrors([
                'barcode' => 'This book is currently unavailable.',
            ]);
        }

        Session::put('kiosk_borrow_item', $item);

        return redirect()->route('kiosk.borrow.confirm');
    }
public function showSuccess()
{
    $receipt = Session::get('kiosk_last_receipt');

    if (! $receipt || ! Session::has('kiosk_patron')) {
        return redirect()->route('kiosk.dashboard');
    }

    return view('kiosk.success', ['receipt' => $receipt]);
}

    public function showConfirmBorrow()
    {
        if (! Session::has('kiosk_patron')) {
            return redirect()->route('kiosk.welcome')->withErrors([
                'uid' => 'Please scan your RFID card first.',
            ]);
        }

        if (! Session::has('kiosk_borrow_item')) {
            return redirect()->route('kiosk.borrow')->withErrors([
                'barcode' => 'Book not found.',
            ]);
        }

        return view('kiosk.borrow-confirm', [
            'patron' => Session::get('kiosk_patron'),
            'item' => Session::get('kiosk_borrow_item'),
        ]);
    }

    public function confirmBorrow()
    {
        if (! Session::has('kiosk_patron')) {
            return redirect()->route('kiosk.welcome')->withErrors([
                'uid' => 'Please scan your RFID card first.',
            ]);
        }

        if (! Session::has('kiosk_borrow_item')) {
            return redirect()->route('kiosk.borrow')->withErrors([
                'barcode' => 'Book not found.',
            ]);
        }

        $patron = Session::get('kiosk_patron');
        $item = Session::get('kiosk_borrow_item');

        $patronId = (int) ($patron['patron_id'] ?? 0);
        $patronIdStr = (string) $patronId;
        $itemId = (int) ($item['item_id'] ?? 0);
        $barcode = $item['barcode'] ?? null;

        try {
            $checkoutResult = $this->kohaService->checkoutItem($patronId, $itemId);
        } catch (\Throwable $e) {
            $this->activityLogService->log(
                action: 'borrow',
                status: 'failed',
                patronId: $patronIdStr,
                message: 'Unable to borrow this book. Please try again.',
                barcode: $barcode,
                itemId: $itemId
            );

            return redirect()->route('kiosk.borrow')->withErrors([
                'barcode' => 'Unable to borrow this book. Please try again.',
            ]);
        }

        Session::forget('kiosk_borrow_item');

        $this->activityLogService->log(
            action: 'borrow',
            status: 'success',
            patronId: $patronIdStr,
            message: 'Book borrowed successfully.',
            barcode: $barcode,
            itemId: $itemId
        );

        Session::put('kiosk_last_receipt', [
            'type' => 'borrow',
            'patron_name' => $patron['name'] ?? '',
            'patron_id' => $patronIdStr,
            'title' => $item['biblio']['title'] ?? $item['title'] ?? 'Unknown title',
            'author' => $item['biblio']['author'] ?? $item['author'] ?? null,
            'barcode' => $barcode,
            'due_date' => $checkoutResult['due_date'] ?? null,
            'timestamp' => now(),
        ]);

        try {
            $checkouts = $this->kohaService->getPatronCheckouts($patronId);
            $patron['borrowed_books'] = $this->formatCheckouts($checkouts);
            Session::put('kiosk_patron', $patron);
        } catch (\Throwable $e) {
            // Keep existing patron data if re-fetching checkouts fails
        }

        return redirect()->route('kiosk.success');
    }

    public function cancelBorrow()
    {
        Session::forget('kiosk_borrow_item');

        return redirect()->route('kiosk.borrow');
    }

    public function showReturn()
    {
        if (! Session::has('kiosk_patron')) {
            return redirect()->route('kiosk.welcome')->withErrors([
                'uid' => 'Please scan your RFID card first.',
            ]);
        }

        return view('kiosk.return', [
            'patron' => Session::get('kiosk_patron'),
        ]);
    }

    public function lookupReturnItem(Request $request)
    {
        if (! Session::has('kiosk_patron')) {
            return redirect()->route('kiosk.welcome')->withErrors([
                'uid' => 'Please scan your RFID card first.',
            ]);
        }

        $request->validate(['barcode' => 'required|string']);

        $barcode = trim($request->input('barcode'));
        $patron = Session::get('kiosk_patron');
        $patronId = $patron['patron_id'] ?? null;

        try {
            $item = $this->kohaService->getItemByBarcode($barcode);
        } catch (\Throwable $e) {
            $this->activityLogService->log(
                action: 'return',
                status: 'failed',
                patronId: $patronId,
                message: 'Unable to retrieve book information. Please try again.',
                barcode: $barcode
            );

            return back()->withErrors([
                'barcode' => 'Unable to retrieve book information. Please try again.',
            ]);
        }

        if (! $item) {
            $this->activityLogService->log(
                action: 'return',
                status: 'failed',
                patronId: $patronId,
                message: 'Book not found.',
                barcode: $barcode
            );

            return back()->withErrors([
                'barcode' => 'Book not found.',
            ]);
        }

        $borrowedBooks = $patron['borrowed_books'] ?? [];

        $isBorrowedByPatron = collect($borrowedBooks)->contains(function ($book) use ($barcode, $item) {
            $bookBarcode = $book['barcode'] ?? null;
            $bookItemId = $book['raw']['item_id'] ?? $book['item_id'] ?? null;

            return ($bookBarcode && strtolower($bookBarcode) === strtolower($barcode))
                || ($bookItemId && (int) $bookItemId === (int) ($item['item_id'] ?? 0));
        });

        if (! $isBorrowedByPatron) {
            $itemId = (int) ($item['item_id'] ?? 0);

            $this->activityLogService->log(
                action: 'return',
                status: 'failed',
                patronId: $patronId,
                message: 'This book is not currently borrowed on your account.',
                barcode: $barcode,
                itemId: $itemId
            );

            return back()->withErrors([
                'barcode' => 'This book is not currently borrowed on your account.',
            ]);
        }

        Session::put('kiosk_return_item', $item);

        return redirect()->route('kiosk.return.confirm');
    }

    public function showConfirmReturn()
    {
        if (! Session::has('kiosk_patron')) {
            return redirect()->route('kiosk.welcome')->withErrors([
                'uid' => 'Please scan your RFID card first.',
            ]);
        }

        if (! Session::has('kiosk_return_item')) {
            return redirect()->route('kiosk.return')->withErrors([
                'barcode' => 'Book not found.',
            ]);
        }

        return view('kiosk.return-confirm', [
            'patron' => Session::get('kiosk_patron'),
            'item' => Session::get('kiosk_return_item'),
        ]);
    }

    public function confirmReturn()
    {
        if (! Session::has('kiosk_patron')) {
            return redirect()->route('kiosk.welcome')->withErrors([
                'uid' => 'Please scan your RFID card first.',
            ]);
        }

        if (! Session::has('kiosk_return_item')) {
            return redirect()->route('kiosk.return')->withErrors([
                'barcode' => 'Book not found.',
            ]);
        }

        $patron = Session::get('kiosk_patron');
        $item = Session::get('kiosk_return_item');

        $patronIdStr = (string) ($patron['patron_id'] ?? '');
        $itemId = (int) ($item['item_id'] ?? 0);
        $barcode = $item['barcode'] ?? null;

                try {
            $this->kohaService->checkinItem($itemId, $barcode);
        } catch (\Throwable $e) {
            $this->activityLogService->log(
                action: 'return',
                status: 'failed',
                patronId: $patronIdStr,
                message: 'Return failed: ' . $e->getMessage(),
                barcode: $barcode,
                itemId: $itemId
            );

            return redirect()->route('kiosk.return')->withErrors([
                'barcode' => 'The book could not be returned: ' . $e->getMessage(),
            ]);
        }

        Session::forget('kiosk_return_item');

        $this->activityLogService->log(
            action: 'return',
            status: 'success',
            patronId: $patronIdStr,
            message: 'Book returned successfully.',
            barcode: $barcode,
            itemId: $itemId
        );

        Session::put('kiosk_last_receipt', [
            'type' => 'return',
            'patron_name' => $patron['name'] ?? '',
            'patron_id' => $patronIdStr,
            'title' => $item['biblio']['title'] ?? $item['title'] ?? 'Unknown title',
            'author' => $item['biblio']['author'] ?? $item['author'] ?? null,
            'barcode' => $barcode,
            'due_date' => null,
            'timestamp' => now(),
        ]);
// Refresh the patron's borrowed list so the dashboard updates right away
$patronId = (int) ($patron['patron_id'] ?? 0);

try {
    $checkouts = $this->kohaService->getPatronCheckouts($patronId);
    $patron['borrowed_books'] = $this->formatCheckouts($checkouts);
} catch (\Throwable $e) {
    // Koha unreachable: keep the existing list and fall through to the local removal below
}

// Safety net: make sure the returned book is gone even if Koha's list lags behind
if (! empty($barcode)) {
    $patron['borrowed_books'] = array_values(array_filter(
        $patron['borrowed_books'] ?? [],
        fn ($b) => strtolower((string) ($b['barcode'] ?? '')) !== strtolower((string) $barcode)
    ));
}

Session::put('kiosk_patron', $patron);

        return redirect()->route('kiosk.success');    }

    public function cancelReturn()
    {
        Session::forget('kiosk_return_item');

        return redirect()->route('kiosk.dashboard');
    }

    public function logout()
{
    Session::invalidate();
    Session::regenerateToken();
    Cache::forget('kiosk.pending_scan');

    return redirect()->route('kiosk.welcome');
}
public function readerStatus(Request $request)
{
    $expected = (string) config('services.kiosk.token');
    abort_unless(
        $expected !== '' && hash_equals($expected, (string) $request->header('X-Kiosk-Token')),
        401
    );

    Cache::put('kiosk.reader', ['connected' => (bool) $request->input('connected')], 30);

    return response()->noContent();
}

public function readerOnline()
{
    $reader = Cache::get('kiosk.reader');

    return response()->json(['online' => (bool) ($reader['connected'] ?? false)]);
}

    protected function isItemUnavailable(array $item): bool
    {
        if (! empty($item['checked_out']) || ! empty($item['onloan'])) {
            return true;
        }

        if (! empty($item['notforloan']) && (int) $item['notforloan'] > 0) {
            return true;
        }

        if (! empty($item['withdrawn']) && (int) $item['withdrawn'] > 0) {
            return true;
        }

        if (! empty($item['itemlost']) && (int) $item['itemlost'] > 0) {
            return true;
        }

        $status = strtolower($item['status'] ?? '');
        if ($status === 'checked out' || $status === 'unavailable' || $status === 'on loan') {
            return true;
        }

        return false;
    }

    protected function formatCheckouts(array $checkouts): array
    {
        return array_map(function ($checkout) {
            $title = $checkout['item']['biblio']['title']
                ?? $checkout['item']['title']
                ?? $checkout['title']
                ?? $checkout['item']['biblio_title']
                ?? 'Unknown title';

            $author = $checkout['item']['biblio']['author']
                ?? $checkout['item']['author']
                ?? $checkout['author']
                ?? $checkout['item']['biblio_author']
                ?? 'Unknown author';

            $due = $checkout['due_date']
                ?? $checkout['due']
                ?? null;

            $barcode = $checkout['item']['barcode']
                ?? $checkout['barcode']
                ?? 'N/A';

            $checkoutDate = $checkout['issuedate']
                ?? $checkout['checkout_date']
                ?? null;

            return [
                'title' => $title,
                'author' => $author,
                'due' => $due,
                'barcode' => $barcode,
                'checkout_date' => $checkoutDate,
                'raw' => $checkout,
            ];
        }, $checkouts);
    }
}