<?php

namespace App\Kiosk\Circulation;

interface CirculationAction
{
    /** Returns an error message, or null when the book may go in the cart. */
    public function validate(array $item, array $patron): ?string;

    /** Performs the real operation in Koha. Throws on failure. */
    public function commit(array $patron, array $item): array;

    public function successMessage(): string;

    public function failureMessage(): string;
}