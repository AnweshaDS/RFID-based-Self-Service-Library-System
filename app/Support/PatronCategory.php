<?php

namespace App\Support;

class PatronCategory
{
    /** Label and borrowing limit for a Koha patron category code. */
    public static function for(?string $code): array
    {
        $key = strtoupper(trim((string) $code));
        $categories = array_change_key_case(config('kiosk.categories', []), CASE_UPPER);
        $category = $categories[$key] ?? config('kiosk.default_category', ['label' => 'Patron', 'max_books' => 3]);

        return [
            'code' => $key,
            'label' => $category['label'],
            'max_books' => (int) $category['max_books'],
        ];
    }
}