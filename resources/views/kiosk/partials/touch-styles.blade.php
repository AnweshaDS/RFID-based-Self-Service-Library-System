<style>
    /* Touch targets: at least 48 px tall, no double-tap zoom delay */
    button, input[type="submit"], a[class*="rounded"] {
        min-height: 48px;
        touch-action: manipulation;
    }
    a[class*="rounded"] { display: inline-flex; align-items: center; }
    a, button { -webkit-tap-highlight-color: transparent; }
    button:active, a[class*="rounded"]:active { transform: scale(0.97); }

    /* Text size: the kiosk is read standing up */
    .text-xs { font-size: 14px !important; line-height: 1.45 !important; }
    .text-sm { font-size: 16px !important; line-height: 1.5 !important; }
    [class*="text-[10px]"], [class*="text-[11px]"] { font-size: 13px !important; }

    /* Contrast: faint text becomes clearly readable on the dark background */
    .text-paper\/40, .text-paper\/50, .text-paper\/60 { color: rgba(246, 247, 245, 0.85) !important; }
    .text-paper\/70, .text-paper\/90 { color: rgba(246, 247, 245, 0.95) !important; }
    .text-red-300 { color: #fecaca !important; }

    /* Kiosk behaviour: no accidental text selection, but inputs still work */
    body { -webkit-user-select: none; user-select: none; }
    input { -webkit-user-select: text; user-select: text; }
</style>