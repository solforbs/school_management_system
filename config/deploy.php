<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pull From Dashboard
    |--------------------------------------------------------------------------
    |
    | The dashboard Pull button deploys code over HTTP, so it is off unless
    | switched on deliberately, and only ever an Admin can reach it.
    |
    */
    'enabled' => env('DEPLOY_PULL_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Remote and Branch
    |--------------------------------------------------------------------------
    |
    | Fixed here rather than taken from the request. Nothing a browser sends
    | picks the remote, the branch, or any part of a git command - otherwise
    | the button would be a way to run arbitrary code on the server.
    |
    */
    'remote' => env('DEPLOY_REMOTE', 'origin'),
    'branch' => env('DEPLOY_BRANCH', 'main'),

    /*
    |--------------------------------------------------------------------------
    | Repository Path
    |--------------------------------------------------------------------------
    |
    | Where the working copy lives. Defaults to the application root.
    |
    */
    'path' => env('DEPLOY_PATH', base_path()),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds any single git or artisan step may take before it is abandoned
    | and the deploy rolled back.
    |
    */
    'timeout' => env('DEPLOY_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Migrations After A Pull
    |--------------------------------------------------------------------------
    |
    | When a release adds or changes migration files, run "migrate --force"
    | as the last step of the deploy. Migrations are forward-only and the
    | migrations table makes them idempotent, so this is safe to automate.
    | A migration that fails leaves the code updated and is reported for
    | someone to look at, rather than rolled back over a half-applied schema.
    |
    */
    'run_migrations' => env('DEPLOY_RUN_MIGRATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Seeders After A Pull
    |--------------------------------------------------------------------------
    |
    | Seeders are never run automatically - many of them truncate and
    | re-insert. A release that changes seeder files has the changed classes
    | listed on the deploy result, and an Admin runs them explicitly from
    | the Deployment card when they know it is safe.
    |
    | Directories scanned for seeder classes, relative to the repo root.
    |
    */
    'seeder_paths' => [
        'database/seeders',
        'Modules/*/database/seeders',
    ],
];
