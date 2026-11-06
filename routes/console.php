<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Delete kiosk verification photos older than the retention period (90 days by default,
// never less than 7). Only photos stored on this computer are removed, so run the
// scheduler on one computer only.
Schedule::command('verification:prune', [
    '--older-than' => max(7, (int) config('kiosk.photo_retention_days', 90)),
    '--force' => true,
])->dailyAt('02:00')->withoutOverlapping();