<?php

return [
    // Seconds of inactivity before the patron is logged out (360 = 6 minutes)
    'idle_timeout' => (int) env('KIOSK_IDLE_TIMEOUT', 360),

    // Koha patron category code => label shown on screen, and max books on loan at once.
    // Replace T with the real code from Koha.
    'categories' => [
        'ST' => ['label' => 'Postgraduate',  'max_books' => 5],    // confirmed: STU003
        'UG' => ['label' => 'Undergraduate', 'max_books' => 3],    // confirmed: STU002
        'T'  => ['label' => 'Teacher',       'max_books' => 15],   // code still to confirm (step 2)
    ],

    // Shown next to fine amounts
    'currency' => env('KIOSK_CURRENCY', 'BDT'),

    // Used when a category is missing or not listed above (strict on purpose)
    'default_category' => ['label' => 'Patron', 'max_books' => 3],

    // Disk that holds kiosk verification photos and cached Koha photos
    'photo_disk' => env('KIOSK_PHOTO_DISK', 'verification'),

    // Kiosk photos older than this many days are deleted by the daily prune job
    'photo_retention_days' => (int) env('KIOSK_PHOTO_RETENTION_DAYS', 90),
    // Photos that still need review, or were flagged, are kept this many days instead
    'flagged_retention_days' => (int) env('KIOSK_FLAGGED_RETENTION_DAYS', 365),

        // Kiosk photos shown on a patron card before "Show all"
    'photos_per_card' => 10,


    // Face matching: the Python tool in the face-service folder
    'face_python' => env('FACE_PYTHON', base_path('face-service/.venv/Scripts/python.exe')),
    'face_script' => env('FACE_SCRIPT', base_path('face-service/match.py')),

    // Cosine similarity at or above this counts as the same person. 0.363 is OpenCV's
    // own starting value for this model; calibrate it on real photos from this library.
    'face_match_threshold' => (float) env('FACE_MATCH_THRESHOLD', 0.363),

    // Which laptop/kiosk this is. Set per machine in .env, never committed.
    'id' => env('KIOSK_ID', 'unknown'),
    'name' => env('KIOSK_NAME', env('KIOSK_ID', 'Unknown kiosk')),

];