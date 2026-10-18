<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class PatronStatus
{
    /** Active, Expired or Restricted, taken from the Koha patron record. */
    public static function from(array $kohaPatron): string
    {
        if (! empty($kohaPatron['restricted'])) {
            return 'Restricted';
        }

        if (! empty($kohaPatron['expiry_date'])) {
            try {
                if (Carbon::parse($kohaPatron['expiry_date'])->endOfDay()->isPast()) {
                    return 'Expired';
                }
            } catch (\Throwable $e) {
                // unreadable date: treat as active, Koha still enforces its own rules
            }
        }

        return 'Active';
    }
}