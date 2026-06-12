<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled Time Signal Chirp
    |--------------------------------------------------------------------------
    |
    | These settings control the scheduler demo that posts a visible chirp at
    | the configured time. When "once" is enabled, the command posts only the
    | first time it runs successfully unless it is executed with --force.
    |
    */

    'time_signal' => [
        'enabled' => env('CHIRP_TIME_SIGNAL_ENABLED', true),
        'time' => env('CHIRP_TIME_SIGNAL_TIME', '09:00'),
        'once' => env('CHIRP_TIME_SIGNAL_ONCE', true),
        'cache_key' => 'chirps:scheduled-time-signal-posted',
    ],

];
