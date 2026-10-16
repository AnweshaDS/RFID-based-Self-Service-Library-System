<?php

namespace App\Kiosk\Circulation;

use App\Services\KohaService;

class BorrowAction implements CirculationAction
{
    public function __construct(private KohaService $koha) {}

    public function validate(array $item, array $patron): ?string
    {
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
        if (! empty($item['checked_out']) || ! empty($item['onloan'])) {
            return true;
        }

        foreach (['notforloan', 'withdrawn', 'itemlost'] as $flag) {
            if (! empty($item[$flag]) && (int) $item[$flag] > 0) {
                return true;
            }
        }

        return in_array(strtolower($item['status'] ?? ''), ['checked out', 'unavailable', 'on loan'], true);
    }
}
