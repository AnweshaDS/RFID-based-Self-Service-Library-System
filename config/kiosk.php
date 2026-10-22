<?php

return [
    // Seconds of inactivity before the patron is logged out (360 = 6 minutes)
    'idle_timeout' => (int) env('KIOSK_IDLE_TIMEOUT', 360),

    // Koha patron category code => label shown on screen, and max books on loan at once.
    // Replace UG / PG / T with the real codes from Koha.
        'categories' => [
        'ST' => ['label' => 'Postgraduate',  'max_books' => 5],    // confirmed: STU003 (Sadia)
        'UG' => ['label' => 'Undergraduate', 'max_books' => 3],    // code still to confirm (step 2)
        'T'  => ['label' => 'Teacher',       'max_books' => 15],   // code still to confirm (step 2)
    
            // Shown next to fine amounts
    'currency' => env('KIOSK_CURRENCY', 'BDT'),
    ],

    // Used when a category is missing or not listed above (strict on purpose)
    'default_category' => ['label' => 'Patron', 'max_books' => 3],
];