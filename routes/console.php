<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Delete kiosk verification photos that are past the retention time an admin set on the
// Photo Retention page (90 days by default, never less than 7). Photos that still need
// review, or were flagged, are kept longer. Run the scheduler on one computer only.
Schedule::command('verification:prune', ['--scheduled' => true, '--force' => true])
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Compare new kiosk photos with the patron's Koha photo. Only photos whose file can be
// reached from this computer are checked.
Schedule::command('verification:match', ['--limit' => 10])
    ->everyMinute()
    ->withoutOverlapping();