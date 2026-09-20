<?php

return [
    // Turn off once the physical reader feeds UIDs into the scan endpoint directly.
    'simulation_mode' => env('KIOSK_SIMULATION_MODE', true),
];