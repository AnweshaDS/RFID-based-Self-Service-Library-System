<?php

namespace App\Kiosk\Circulation;

use App\Services\KohaService;

class ReturnAction implements CirculationAction
{
    public function __construct(private KohaService $koha) {}

    public function validate(array $item, array $patron): ?string
    {
        $barcode = strtolower((string) ($item['barcode'] ?? ''));
        $itemId = (int) ($item['item_id'] ?? 0);

        $isMine = collect($patron['borrowed_books'] ?? [])->contains(function ($book) use ($barcode, $itemId) {
            $bookItemId = $book['raw']['item_id'] ?? $book['item_id'] ?? null;

            return ($barcode !== '' && strtolower((string) ($book['barcode'] ?? '')) === $barcode)
                || ($bookItemId && (int) $bookItemId === $itemId);
        });

        return $isMine ? null : 'This book is not currently borrowed on your account.';
    }

    public function commit(array $patron, array $item): array
    {
        return $this->koha->checkinItem((int) $item['item_id'], $item['barcode']);
    }

    public function successMessage(): string
    {
        return 'Book returned successfully.';
    }

    public function failureMessage(): string
    {
        return 'This book could not be returned. Please ask a librarian.';
    }
}