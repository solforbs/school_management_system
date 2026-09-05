<?php

namespace App\Providers;

use App\Listeners\SendAttendanceSmsListener;
use App\Listeners\SyncStudentsToDeviceListener;
use App\Observers\ZktecoAttendanceLogObserver;
use Athwari\LaravelZktecoAdms\Events\AttendanceReceived;
use Athwari\LaravelZktecoAdms\Events\DeviceConnected;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        Event::listen(DeviceConnected::class, SyncStudentsToDeviceListener::class);
        Event::listen(AttendanceReceived::class, SendAttendanceSmsListener::class);

        // Reclassifies each punch into the school's actual check-in/
        // check-out/overtime schedule before it's saved - see the observer's
        // own docblock for why this lives here rather than on the event.
        ZktecoAttendanceLog::observe(ZktecoAttendanceLogObserver::class);
    }

    /**
     * Offline clients sync in bursts when they regain connectivity - a whole
     * day's queued work in one go - so the limit is per authenticated device
     * rather than per IP, where a whole school behind one NAT would throttle
     * each other.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Fee reminders and report cards are both queued jobs, one per
        // parent/report card, sharing this single platform-wide bucket -
        // they hit the same mail server and the same SMS gateway account,
        // so a school with a thousand students can't blow through either
        // in one go, whichever feature happens to be running.
        RateLimiter::for('notifications', fn () => Limit::perMinute(
            (int) config('services.notifications.rate_limit', 100)
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
