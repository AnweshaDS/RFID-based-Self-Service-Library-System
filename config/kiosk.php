<?php

return [
    // Seconds of inactivity before the patron is logged out (180 = 3 minutes)
    'idle_timeout' => (int) env('KIOSK_IDLE_TIMEOUT', 180),
    'max_cart_items' => (int) env('KIOSK_MAX_CART', 5),
];