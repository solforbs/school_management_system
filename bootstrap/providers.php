<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TetherServiceProvider;
use Mhmiton\LaravelModulesLivewire\LaravelModulesLivewireServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    TetherServiceProvider::class,
    LaravelModulesLivewireServiceProvider::class,
];
