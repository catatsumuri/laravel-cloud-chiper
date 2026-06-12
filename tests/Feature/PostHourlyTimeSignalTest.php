<?php

use App\Models\Chirp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('it posts a scheduled time signal chirp from the bot user', function () {
    Carbon::setTestNow('2026-06-12 15:30:00');

    $this->artisan('chirps:post-scheduled-time-signal', ['--at' => '2026-06-12 15:00:00'])
        ->expectsOutputToContain('Posted time signal chirp #')
        ->assertSuccessful();

    $bot = User::query()->where('email', 'timekeeper@example.com')->first();

    expect($bot)->not->toBeNull()
        ->and($bot->name)->toBe('Timekeeper Bot')
        ->and($bot->email_verified_at)->not->toBeNull();

    expect(Chirp::query()->latest('id')->first())
        ->user_id->toBe($bot->id)
        ->message->toBe(sprintf(
            'Time signal: %s. Posted by Laravel scheduler.',
            Carbon::parse('2026-06-12 15:00:00')->timezone(config('app.timezone'))->format('H:00 T'),
        ));
});

test('it skips after the scheduled time signal has already posted once', function () {
    config(['chirps.time_signal.once' => true]);
    Cache::forever(config('chirps.time_signal.cache_key'), 123);

    $this->artisan('chirps:post-scheduled-time-signal')
        ->expectsOutputToContain('Scheduled time signal chirp was already posted.')
        ->assertSuccessful();

    expect(Chirp::query()->count())->toBe(0);
});

test('it can be forced to post again after the once marker exists', function () {
    config(['chirps.time_signal.once' => true]);
    Cache::forever(config('chirps.time_signal.cache_key'), 123);

    $this->artisan('chirps:post-scheduled-time-signal', ['--force' => true])
        ->expectsOutputToContain('Posted time signal chirp #')
        ->assertSuccessful();

    expect(Chirp::query()->count())->toBe(1);
});

test('the scheduled time signal command is scheduled from config', function () {
    config([
        'chirps.time_signal.enabled' => true,
        'chirps.time_signal.time' => '14:30',
    ]);

    expect(config('chirps.time_signal.time'))->toBe('14:30');

    $this->artisan('schedule:list')
        ->expectsOutputToContain('chirps:post-scheduled-time-signal')
        ->assertSuccessful();
});
