<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Environment file path
    |--------------------------------------------------------------------------
    |
    | Where EnvManager reads and writes. Overridden in the test suite so a
    | test run can never rewrite the environment the app runs on - doing so
    | would silently wipe live credentials.
    |
    */

    'path' => base_path('.env'),

];
