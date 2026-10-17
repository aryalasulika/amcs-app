<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| AMCS scheduled maintenance
|--------------------------------------------------------------------------
*/

// Nightly MySQL backup with 30-day retention (NFR-07).
Schedule::command('amcs:backup-database --keep=30')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Monthly archive of activity logs older than 3 years (FR-15).
Schedule::command('amcs:archive-activity --days=1095')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping();
