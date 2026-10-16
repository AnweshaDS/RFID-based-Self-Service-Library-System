<?php

namespace App\Support;

class CheckoutFormatter
{
    public static function format(array $checkouts): array
    {
        return array_map(function ($checkout) {
            return [
                'title' => $checkout['item']['biblio']['title']
                    ?? $checkout['item']['title']
                    ?? $checkout['title']
                    ?? $checkout['item']['biblio_title']
                    ?? 'Unknown title',
                'author' => $checkout['item']['biblio']['author']
                    ?? $checkout['item']['author']
                    ?? $checkout['author']
                    ?? $checkout['item']['biblio_author']
                    ?? 'Unknown author',
                'due' => $checkout['due_date'] ?? $checkout['due'] ?? null,
                'barcode' => $checkout['item']['barcode'] ?? $checkout['barcode'] ?? 'N/A',
                'checkout_date' => $checkout['issuedate'] ?? $checkout['checkout_date'] ?? null,
                'raw' => $checkout,
            ];
        }, $checkouts);
    }
}