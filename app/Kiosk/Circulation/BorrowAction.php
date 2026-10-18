<?php

namespace App\Kiosk\Circulation;

use App\Services\KohaService;

class BorrowAction implements CirculationAction
{
    public function __construct(private KohaService $koha) {}

    public function validate(array $item, array $patron): ?string
    {
        if (! empty($patron['restricted'])) {
            return 'Your account is restricted. Please contact the library desk.';
        }

        return $this->isUnavailable($item) ? 'This book is currently unavailable.' : null;
    }

    public function commit(array $patron, array $item): array
    {
        return $this->koha->checkoutItem((int) $patron['patron_id'], (int) $item['item_id']);
    }

    public function successMessage(): string
    {
        return 'Book borrowed successfully.';
    }

    public function failureMessage(): string
    {
        return 'Unable to borrow this book. Please try again.';
    }

    private function isUnavailable(array $item): bool
    {
        // Koha REST field names first, older names kept as a fallback
        if (! empty($item['checked_out_date']) || ! empty($item['checked_out']) || ! empty($item['onloan'])) {
            return true;
        }

        foreach ([
            'not_for_loan_status', 'effective_not_for_loan_status', 'notforloan',
            'lost_status', 'itemlost', 'withdrawn',
        ] as $flag) {
            if (! empty($item[$flag])) {
                return true;
            }
        }

        return in_array(strtolower($item['status'] ?? ''), ['checked out', 'unavailable', 'on loan'], true);
    }
}
