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
];
