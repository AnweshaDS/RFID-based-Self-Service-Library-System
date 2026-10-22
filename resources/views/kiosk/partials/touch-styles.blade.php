<style>
    /* ---------- Touch behaviour ---------- */
    button, input[type="submit"] { min-height: 48px; touch-action: manipulation; }
    a[class*="rounded"] { min-height: 48px; display: inline-flex; align-items: center; touch-action: manipulation; }
    a, button { -webkit-tap-highlight-color: transparent; }
    button:active, .kiosk-btn:active, .kiosk-back-btn:active { transform: scale(0.97); }

    /* ---------- Text size and contrast (the kiosk is read standing up) ---------- */
    .text-xs { font-size: 14px !important; line-height: 1.45 !important; }
    .text-sm { font-size: 16px !important; line-height: 1.5 !important; }
    [class*="text-[10px]"], [class*="text-[11px]"] { font-size: 13px !important; }
    .text-paper\/40, .text-paper\/50, .text-paper\/60 { color: rgba(246, 247, 245, 0.85) !important; }
    .text-paper\/70, .text-paper\/90 { color: rgba(246, 247, 245, 0.95) !important; }
    .text-red-300 { color: #fecaca !important; }

    /* ---------- Text that stands out ---------- */
    .font-display { font-weight: 700; }
    .font-medium { font-weight: 600 !important; }
    span.block.font-medium { color: #ffffff; }
    .uppercase.tracking-wide { color: #E4A72E !important; letter-spacing: 0.08em; }

    /* ---------- Kiosk behaviour ---------- */
    body { -webkit-user-select: none; user-select: none; }
    input { -webkit-user-select: text; user-select: text; }

    /* ---------- Page header: Back button on its own row, title below ---------- */
    .kiosk-back-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        flex-shrink: 0; white-space: nowrap;
        min-height: 48px; padding: 10px 22px;
        background: #ffffff; color: #16233E !important;
        border-radius: 12px; font-size: 15px; font-weight: 700;
        text-decoration: none !important; touch-action: manipulation;
    }
        .kiosk-page-header { margin-bottom: 22px; padding-bottom: 20px; border-bottom: 1px solid rgba(246, 247, 245, 0.18); text-align: center; }
    .kiosk-page-header .kiosk-back-btn { display: flex; width: fit-content; margin: 0 auto 20px 0; }
    .kiosk-page-title { margin: 0; font-size: 32px; line-height: 1.2; font-weight: 700; color: #ffffff; text-align: center; }
    .kiosk-page-subtitle { margin: 10px auto 0; max-width: 560px; font-size: 16px; line-height: 1.5; color: rgba(246, 247, 245, 0.85); text-align: center; }

    /* ---------- List rows: the title shrinks and wraps, the buttons never break ---------- */
    .kiosk-row {
        display: flex; align-items: center; justify-content: space-between; gap: 16px;
        padding: 14px 18px; border-radius: 14px; background: rgba(246, 247, 245, 0.10);
    }
    .kiosk-row-main { flex: 1 1 auto; min-width: 0; }
    .kiosk-row-title {
        display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden;
        overflow-wrap: anywhere; font-size: 17px; font-weight: 600; line-height: 1.35; color: #ffffff;
    }
    .kiosk-row-meta { margin-top: 4px; font-size: 14px; line-height: 1.4; color: rgba(246, 247, 245, 0.8); overflow-wrap: anywhere; }
    .kiosk-row-side { flex: 0 0 auto; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
    .kiosk-btn {
        display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; white-space: nowrap;
        min-height: 46px; padding: 10px 18px; border: none; border-radius: 12px;
        font-size: 15px; font-weight: 700; cursor: pointer; touch-action: manipulation;
    }
    .kiosk-chip {
        display: inline-flex; align-items: center; white-space: nowrap;
        padding: 4px 12px; border-radius: 999px;
        font-size: 13px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
    }
</style>