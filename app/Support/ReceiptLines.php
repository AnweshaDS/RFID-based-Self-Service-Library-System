<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ReceiptLines
{
    /**
     * The books to print (only the ones that went through) and how many failed.
     *
     * @return array{lines: Collection, failed: int}
     */
    public static function from(array $receipt): array
    {
        $items = $receipt['items'] ?? [];

        // Older single-book receipts have no "items" list
        if (empty($items)) {
            return [
                'lines' => collect([[
                    'title' => $receipt['title'] ?? '',
                    'author' => $receipt['author'] ?? null,
                    'barcode' => $receipt['barcode'] ?? null,
                    'due_date' => $receipt['due_date'] ?? null,
                ]]),
                'failed' => 0,
            ];
        }

        $lines = collect($items)->where('ok', true)->values();

        return ['lines' => $lines, 'failed' => count($items) - $lines->count()];
    }
}