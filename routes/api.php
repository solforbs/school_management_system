<?php

use App\Http\Controllers\Api\PlanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API
|--------------------------------------------------------------------------
|
| Read-only endpoints a front-end can call without signing in. Everything
| here is information the marketing site publishes anyway; anything that
| isn't belongs behind auth in routes/web.php instead.
|
| CORS is already open on api/* (the framework's default), so a browser app
| on another origin can call these directly.
|
*/

// Throttled by IP. These are cheap and cacheable, but they are also the
// only endpoints on this host that answer to nobody, so an upper bound
// keeps a stray render loop from becoming a database load problem.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('plans', [PlanController::class, 'index'])->name('api.plans.index');
    Route::get('plans/{plan}', [PlanController::class, 'show'])->name('api.plans.show');
});
