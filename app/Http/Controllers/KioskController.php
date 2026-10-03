<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Services\KohaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

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
        'name' => $name,
        'status' => 'Active',
        'borrowed_books' => $borrowedBooks,
        'recent_activity' => [],
    ];

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

        $dueSoonCount = collect($patron['borrowed_books'] ?? [])
            ->filter(function ($book) {
                if (empty($book['due'])) {
                    return false;
                }
                try {
                    return now()->diffInDays(\Illuminate\Support\Carbon::parse($book['due']), false) <= 3;
                } catch (\Throwable $e) {
                    return false;
                }
            })
            ->count();

        $patronId = (string) ($patron['patron_id'] ?? '');

        $recentActivities = ActivityLog::where('patron_id', $patronId)
            ->latest()
            ->take(10)
            ->get();

        return view('kiosk.dashboard', [
            'patron' => $patron,
            'dueSoonCount' => $dueSoonCount,
            'recentActivities' => $recentActivities,
        ]);
    }

    public function showReceipt()
{
    $receipt = Session::get('kiosk_last_receipt');

    if (! $receipt) {
        return redirect()->route('kiosk.dashboard');
    }

    return view('kiosk.receipt', ['receipt' => $receipt]);
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

        return redirect()->route('kiosk.dashboard')->with('status', 'Book borrowed successfully!');
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
            $this->kohaService->checkinItem($itemId);
        } catch (\Throwable $e) {
            $this->activityLogService->log(
                action: 'return',
                status: 'failed',
                patronId: $patronIdStr,
                message: 'Return processing is not connected to Koha yet.',
                barcode: $barcode,
                itemId: $itemId
            );

            return redirect()->route('kiosk.return')->withErrors([
                'barcode' => 'Return processing is not connected to Koha yet. The book was not marked as returned.',
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

        return redirect()->route('kiosk.dashboard')->with('status', 'Book returned successfully!');
    }

    public function cancelReturn()
    {
        Session::forget('kiosk_return_item');

        return redirect()->route('kiosk.dashboard');
    }

    public function logout()
    {
        Session::forget('kiosk_patron');
        Session::forget('kiosk_borrow_item');
        Session::forget('kiosk_return_item');
        Session::forget('kiosk_last_activity');

        return redirect()->route('kiosk.welcome');
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