<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureInstitutionApproved;
use App\Http\Middleware\EnsureInstitutionHasPaid;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Public, read-only, and prefixed /api. Registered separately from
        // web so these routes get neither session nor CSRF - a front-end on
        // another origin has no cookie to send and nothing to protect.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // append(), not use(): use() *replaces* the framework's global stack
        // rather than adding to it, which silently dropped TrimStrings,
        // ConvertEmptyStringsToNull, ValidatePostSize, HandleCors and the
        // rest. An unselected <select> then reached the database as '' -
        // fatal against an integer column (e.g. classes.curriculum_id).
        $middleware->append([EnsureAccountIsActive::class, EnsureInstitutionApproved::class, EnsureInstitutionHasPaid::class]);
        $middleware->validateCsrfTokens(except: ['billing/webhook']);

        // Sanctum doesn't register this alias itself. The Tether sync
        // routes use it so a device token, if it leaks, is confined to
        // syncing and can't act as its owner elsewhere.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
