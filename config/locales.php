<?php

/**
 * Languages an institution can run the app in. Sourced from the
 * shammaa/laravel-multilingual locale list (that package does not support
 * Laravel 13 and only does URL-prefix routing, so the locale is resolved
 * per institution by App\Http\Middleware\SetInstitutionLocale instead).
 */
return [
    'default' => 'en',

    /*
     * Google page-translator language codes where they differ from ours.
     * null = render untranslated (English, or not supported by Google).
     */
    'machine_translation_codes' => [
        'en' => null,
        'en-US' => null,
        'en-GB' => null,
        'es-MX' => 'es',
        'pt-BR' => 'pt',
        'zh' => 'zh-CN',
        'fil' => 'tl',
        'br' => null,
    ],

    'available' => [
        // African
        'en' => ['name' => 'English', 'native' => 'English'],
        'sw' => ['name' => 'Swahili', 'native' => 'Kiswahili'],
        'af' => ['name' => 'Afrikaans', 'native' => 'Afrikaans'],
        'am' => ['name' => 'Amharic', 'native' => 'አማርኛ'],
        'zu' => ['name' => 'Zulu', 'native' => 'isiZulu'],
        'xh' => ['name' => 'Xhosa', 'native' => 'isiXhosa'],
        'yo' => ['name' => 'Yoruba', 'native' => 'Yorùbá'],
        'ig' => ['name' => 'Igbo', 'native' => 'Igbo'],
        'ha' => ['name' => 'Hausa', 'native' => 'Hausa'],

        // European
        'en-US' => ['name' => 'English (US)', 'native' => 'English (US)'],
        'en-GB' => ['name' => 'English (UK)', 'native' => 'English (UK)'],
        'fr' => ['name' => 'French', 'native' => 'Français'],
        'es' => ['name' => 'Spanish', 'native' => 'Español'],
        'es-MX' => ['name' => 'Spanish (Mexico)', 'native' => 'Español (México)'],
        'de' => ['name' => 'German', 'native' => 'Deutsch'],
        'it' => ['name' => 'Italian', 'native' => 'Italiano'],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português'],
        'pt-BR' => ['name' => 'Portuguese (Brazil)', 'native' => 'Português (Brasil)'],
        'ru' => ['name' => 'Russian', 'native' => 'Русский'],
        'pl' => ['name' => 'Polish', 'native' => 'Polski'],
        'nl' => ['name' => 'Dutch', 'native' => 'Nederlands'],
        'sv' => ['name' => 'Swedish', 'native' => 'Svenska'],
        'no' => ['name' => 'Norwegian', 'native' => 'Norsk'],
        'da' => ['name' => 'Danish', 'native' => 'Dansk'],
        'fi' => ['name' => 'Finnish', 'native' => 'Suomi'],
        'el' => ['name' => 'Greek', 'native' => 'Ελληνικά'],
        'cs' => ['name' => 'Czech', 'native' => 'Čeština'],
        'sk' => ['name' => 'Slovak', 'native' => 'Slovenčina'],
        'hu' => ['name' => 'Hungarian', 'native' => 'Magyar'],
        'ro' => ['name' => 'Romanian', 'native' => 'Română'],
        'bg' => ['name' => 'Bulgarian', 'native' => 'Български'],
        'hr' => ['name' => 'Croatian', 'native' => 'Hrvatski'],
        'sr' => ['name' => 'Serbian', 'native' => 'Српски'],
        'sl' => ['name' => 'Slovenian', 'native' => 'Slovenščina'],
        'uk' => ['name' => 'Ukrainian', 'native' => 'Українська'],
        'be' => ['name' => 'Belarusian', 'native' => 'Беларуская'],
        'lt' => ['name' => 'Lithuanian', 'native' => 'Lietuvių'],
        'lv' => ['name' => 'Latvian', 'native' => 'Latviešu'],
        'et' => ['name' => 'Estonian', 'native' => 'Eesti'],
        'ga' => ['name' => 'Irish', 'native' => 'Gaeilge'],
        'cy' => ['name' => 'Welsh', 'native' => 'Cymraeg'],
        'mt' => ['name' => 'Maltese', 'native' => 'Malti'],
        'is' => ['name' => 'Icelandic', 'native' => 'Íslenska'],
        'ca' => ['name' => 'Catalan', 'native' => 'Català'],
        'eu' => ['name' => 'Basque', 'native' => 'Euskara'],
        'gl' => ['name' => 'Galician', 'native' => 'Galego'],
        'br' => ['name' => 'Breton', 'native' => 'Brezhoneg'],
        'lb' => ['name' => 'Luxembourgish', 'native' => 'Lëtzebuergesch'],

        // Middle East & Asia
        'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'rtl' => true],
        'fa' => ['name' => 'Persian', 'native' => 'فارسی', 'rtl' => true],
        'ur' => ['name' => 'Urdu', 'native' => 'اردو', 'rtl' => true],
        'ps' => ['name' => 'Pashto', 'native' => 'پښتو', 'rtl' => true],
        'ku' => ['name' => 'Kurdish', 'native' => 'Kurdî'],
        'tr' => ['name' => 'Turkish', 'native' => 'Türkçe'],
        'hi' => ['name' => 'Hindi', 'native' => 'हिन्दी'],
        'bn' => ['name' => 'Bengali', 'native' => 'বাংলা'],
        'ta' => ['name' => 'Tamil', 'native' => 'தமிழ்'],
        'te' => ['name' => 'Telugu', 'native' => 'తెలుగు'],
        'mr' => ['name' => 'Marathi', 'native' => 'मराठी'],
        'th' => ['name' => 'Thai', 'native' => 'ไทย'],
        'vi' => ['name' => 'Vietnamese', 'native' => 'Tiếng Việt'],
        'id' => ['name' => 'Indonesian', 'native' => 'Bahasa Indonesia'],
        'ms' => ['name' => 'Malay', 'native' => 'Bahasa Melayu'],
        'fil' => ['name' => 'Filipino', 'native' => 'Filipino'],
        'zh' => ['name' => 'Chinese (Simplified)', 'native' => '中文'],
        'zh-TW' => ['name' => 'Chinese (Traditional)', 'native' => '繁體中文'],
        'ja' => ['name' => 'Japanese', 'native' => '日本語'],
        'ko' => ['name' => 'Korean', 'native' => '한국어'],
    ],
];
