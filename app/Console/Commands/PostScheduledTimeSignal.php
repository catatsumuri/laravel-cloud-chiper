<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Signature('chirps:post-scheduled-time-signal {--at= : Time to announce, parsable by Carbon} {--force : Post even if the once marker exists}')]
#[Description('Post a scheduled time signal chirp from the scheduler bot')]
class PostScheduledTimeSignal extends Command
{
    public function handle(): int
    {
        $cacheKey = (string) config('chirps.time_signal.cache_key');

        if ($this->shouldSkipBecauseAlreadyPosted($cacheKey)) {
            $this->components->info('Scheduled time signal chirp was already posted.');

            return self::SUCCESS;
        }

        $time = $this->option('at')
            ? Carbon::parse((string) $this->option('at'))
            : now();

        $bot = User::query()->firstOrCreate(
            ['email' => 'timekeeper@example.com'],
            [
                'name' => 'Timekeeper Bot',
                'password' => Str::password(),
            ],
        );

        if ($bot->email_verified_at === null) {
            $bot->forceFill(['email_verified_at' => now()])->save();
        }

        $chirp = $bot->chirps()->create([
            'message' => sprintf(
                'Time signal: %s. Posted by Laravel scheduler.',
                $time->timezone(config('app.timezone'))->format('H:00 T'),
            ),
        ]);

        if ((bool) config('chirps.time_signal.once')) {
            Cache::forever($cacheKey, $chirp->id);
        }

        $this->components->info("Posted time signal chirp #{$chirp->id}.");

        return self::SUCCESS;
    }

    private function shouldSkipBecauseAlreadyPosted(string $cacheKey): bool
    {
        return (bool) config('chirps.time_signal.once')
            && ! (bool) $this->option('force')
            && Cache::has($cacheKey);
    }
}
