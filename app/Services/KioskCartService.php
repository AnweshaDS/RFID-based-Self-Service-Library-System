<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;
use App\Support\PatronCategory;
use Illuminate\Support\Str;

class KioskCartService
{
    private const KEY = 'kiosk_cart';

    

    public function items(string $mode): array
    {
        return Session::get(self::KEY . ".$mode.items", []);
    }

    public function token(string $mode): string
    {
        $token = Session::get(self::KEY . ".$mode.token");

        if (! $token) {
            $token = Str::random(40);
            Session::put(self::KEY . ".$mode.token", $token);
        }

        return $token;
    }

    public function has(string $mode, string $barcode): bool
    {
        return collect($this->items($mode))->contains(
            fn ($i) => strtolower($i['barcode']) === strtolower($barcode)
        );
    }

        /** Free borrowing slots, or null when there is no limit (returns). */
    public function limit(string $mode, array $patron): ?int
    {
        if ($mode !== 'borrow') {
            return null;   // a return cart is naturally capped by what the patron has on loan
        }

        $max = PatronCategory::for($patron['category_code'] ?? null)['max_books'];

        return max(0, $max - count($patron['borrowed_books'] ?? []));
    }

    public function isFull(string $mode, array $patron): bool
    {
        $limit = $this->limit($mode, $patron);

        return $limit !== null && count($this->items($mode)) >= $limit;
    }
    public function add(string $mode, array $item): void
    {
        $items = $this->items($mode);
        $items[] = $item;
        Session::put(self::KEY . ".$mode.items", $items);
    }

    public function remove(string $mode, string $barcode): void
    {
        $items = array_values(array_filter(
            $this->items($mode),
            fn ($i) => strtolower($i['barcode']) !== strtolower($barcode)
        ));

        Session::put(self::KEY . ".$mode.items", $items);
    }

    public function clear(string $mode): void
    {
        Session::forget(self::KEY . ".$mode");
    }

    public function clearAll(): void
    {
        Session::forget(self::KEY);
    }

    /** Hands over the cart exactly once. A wrong or reused token returns null. */
    public function consume(string $mode, string $token): ?array
    {
        $expected = Session::get(self::KEY . ".$mode.token");
        $items = $this->items($mode);

        if (! $expected || ! hash_equals($expected, $token) || $items === []) {
            return null;
        }

        $this->clear($mode);

        return $items;
    }
}