<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\KohaService;
use App\Support\CheckoutFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class KioskRenewalController extends Controller
{
    public function __construct(
        private KohaService $koha,
        private ActivityLogService $activity,
    ) {}

    public function renew(Request $request)
    {
        if (! $patron = Session::get('kiosk_patron')) {
            return redirect()->route('kiosk.welcome')
                ->withErrors(['uid' => 'Please scan your RFID card first.']);
        }

        $request->validate(['barcode' => ['required', 'string', 'max:64']]);

        $barcode = $request->input('barcode');
        $patronId = (string) ($patron['patron_id'] ?? '');

        // The checkout ID comes from our own session, never from the browser
        $loan = collect($patron['borrowed_books'] ?? [])->first(
            fn ($b) => strtolower((string) ($b['barcode'] ?? '')) === strtolower($barcode)
        );
        $checkoutId = (int) data_get($loan, 'raw.checkout_id', 0);

        if (! $loan || $checkoutId === 0) {
            return $this->refuse($patronId, $barcode, 'not_on_account');
        }

        try {
            $check = $this->koha->getRenewability($checkoutId);

            if (! $check['allowed']) {
                return $this->refuse($patronId, $barcode, $check['reason']);
            }

            $result = $this->koha->renewCheckout($checkoutId);
        } catch (\Throwable $e) {
            Log::warning("Kiosk renewal failed for {$barcode}: " . $e->getMessage());

            return $this->refuse($patronId, $barcode, $e->getMessage());
        }

        $this->activity->log(
            action: 'renew',
            status: 'success',
            patronId: $patronId,
            message: 'Renewed: ' . ($loan['title'] ?? 'Book'),
            barcode: $barcode,
            itemId: (int) data_get($loan, 'raw.item_id', 0)
        );

        try {
            $patron['borrowed_books'] = CheckoutFormatter::format(
                $this->koha->getPatronCheckouts((int) ($patron['patron_id'] ?? 0))
            );
            Session::put('kiosk_patron', $patron);
        } catch (\Throwable $e) {
            // keep the old list; it refreshes at the next login
        }

        $due = $result['due_date'] ?? null;

        return redirect()->route('kiosk.dashboard')->with(
            'renewed',
            'Renewed' . ($due ? ' until ' . Str::limit($due, 10, '') : '') . '.'
        );
    }

    private function refuse(string $patronId, string $barcode, ?string $reason)
    {
        $message = $this->friendly($reason);

        $this->activity->log(
            action: 'renew',
            status: 'failed',
            patronId: $patronId,
            message: $message,
            barcode: $barcode
        );

        return redirect()->route('kiosk.dashboard')->withErrors(['renew' => $message]);
    }

    /** Turns Koha's reason code into a patron-friendly sentence. */
    private function friendly(?string $reason): string
    {
        $code = '';

        if ($reason !== null) {
            if (preg_match('/\(([a-z_]+)\)/', $reason, $m)) {
                $code = $m[1];
            } elseif (preg_match('/^[a-z_]+$/', $reason)) {
                $code = $reason;
            }
        }

        return match ($code) {
            'not_on_account' => 'This book is not on your account.',
            'too_many' => 'You have reached the maximum number of renewals for this book.',
            'on_reserve' => 'Another patron has requested this book, so it cannot be renewed.',
            'too_soon', 'auto_too_soon' => 'It is too early to renew this book. Please try closer to the due date.',
            'overdue' => 'Overdue books cannot be renewed. Please return it.',
            'restriction' => 'Your account is restricted, so renewals are not available.',
            'too_unseen' => 'This book must be brought to the library desk before it can be renewed again.',
            default => 'This book cannot be renewed right now. Please ask a librarian.',
        };
    }
}