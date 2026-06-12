<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('chirps.time_signal.enabled')) {
    Schedule::command('chirps:post-scheduled-time-signal')
        ->dailyAt((string) config('chirps.time_signal.time'))
        ->withoutOverlapping();
}
