<?php

use Spatie\Permission\Middleware\RoleMiddleware;

return [

    /*
    |--------------------------------------------------------------------------
    | Routes group config
    |--------------------------------------------------------------------------
    |
    | The default group settings for the elFinder routes.
    |
    */
    'route' => [
        'prefix' => 'translations',
        // Platform admins only: edits here rewrite the lang/ files.
        'middleware' => ['web', 'auth', RoleMiddleware::class.':Admin'],
    ],

    /**
     * Enable deletion of translations
     *
     * @type bool
     */
    'delete_enabled' => true,

    /**
     * Exclude specific groups from Laravel Translation Manager.
     * This is useful if, for example, you want to avoid editing the official Laravel language files.
     *
     * @type array
     *
     *    array(
     *        'pagination',
     *        'reminders',
     *        'validation',
     *    )
     */
    'exclude_groups' => [],

    /**
     * Exclude specific languages from Laravel Translation Manager.
     *
     * @type array
     *
     *    array(
     *        'fr',
     *        'de',
     *    )
     */
    'exclude_langs' => [],

    /**
     * Export translations with keys output alphabetically.
     */
    'sort_keys' => false,

    'trans_functions' => [
        'trans',
        'trans_choice',
        'Lang::get',
        'Lang::choice',
        'Lang::trans',
        'Lang::transChoice',
        '@lang',
        '@choice',
        '__',
        '$trans.get',
    ],

    /**
     * Database connection name to allow for different db connection for the translations table.
     */
    'db_connection' => env('TRANSLATION_MANAGER_DB_CONNECTION', null),

];
