<?php

namespace Modules\Result\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Result\Console\Commands\RegradeResults;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ResultServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Result';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'result';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        RegradeResults::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
