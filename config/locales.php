<?php

/**
 * Languages an institution can run the app in, keyed by their folder /
 * JSON file name under lang/. The locale is applied per institution by
 * App\Http\Middleware\SetInstitutionLocale.
 *
 * To add one: php artisan lang:add <code>, php artisan translatable:export
 * <code>, php artisan lang:translate-missing <code>, then list it here.
 */
return [
    'default' => 'en',

    /*
     * Stop-gap while views are being moved to __() + lang/*.json: Google's
     * in-browser translator covers text that isn't wrapped yet. Turn off
     * (MACHINE_TRANSLATE_PAGES=false) once every module uses lang files.
     */
    'machine_translate_pages' => (bool) env('MACHINE_TRANSLATE_PAGES', true),

    /*
     * Google page-translator language codes where they differ from ours.
     * null = render untranslated (English, or not supported by Google).
     */
    'machine_translation_codes' => [
        'en' => null,
    ],

    'available' => [
        'en' => ['name' => 'English', 'native' => 'English'],
        'sw' => ['name' => 'Swahili', 'native' => 'Kiswahili'],
        'fr' => ['name' => 'French', 'native' => 'Français'],
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'rtl' => true],
    ],
];
