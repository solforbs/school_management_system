<?php

/**
 * Languages the app can be shown in. An institution picks the default for
 * its users and each person can switch for themselves (see
 * App\Http\Middleware\SetInstitutionLocale). Pages are machine-translated
 * from English by Google's page translator, so every code here must be
 * one Google supports.
 */
return [
    'default' => 'en',

    /*
     * Name of the cookie that holds a person's own language choice. It is
     * only set while that choice differs from their institution's default.
     */
    'cookie' => 'locale',

    /*
     * Google page-translator language codes where they differ from ours.
     * null = render untranslated (the app is written in English).
     */
    'machine_translation_codes' => [
        'en' => null,
    ],

    /*
     * `switcher` holds the few phrases the language switcher shows. They
     * are written by hand, never machine-translated, so someone who picked
     * a language by mistake can still read how to get back.
     */
    'available' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'switcher' => [
                'language' => 'Language',
                'question' => 'Switch the app language to :language?',
                'note' => 'This only changes the language for you, not for anyone else.',
                'confirm' => 'Yes, switch',
                'cancel' => 'Cancel',
                'changed' => 'The language is now :language.',
                'undo' => 'Go back to :language',
            ],
        ],
        'ar' => [
            'name' => 'Arabic',
            'native' => 'العربية',
            'rtl' => true,
            'switcher' => [
                'language' => 'اللغة',
                'question' => 'هل تريد تغيير لغة التطبيق إلى :language؟',
                'note' => 'هذا يغيّر اللغة لك وحدك، وليس لأي شخص آخر.',
                'confirm' => 'نعم، غيّر اللغة',
                'cancel' => 'إلغاء',
                'changed' => 'اللغة الآن :language.',
                'undo' => 'العودة إلى :language',
            ],
        ],
        'fr' => [
            'name' => 'French',
            'native' => 'Français',
            'switcher' => [
                'language' => 'Langue',
                'question' => 'Passer l’application en :language ?',
                'note' => 'Cela change la langue pour vous seulement, pas pour les autres.',
                'confirm' => 'Oui, changer',
                'cancel' => 'Annuler',
                'changed' => 'La langue est maintenant : :language.',
                'undo' => 'Revenir à :language',
            ],
        ],
        'sw' => [
            'name' => 'Swahili',
            'native' => 'Kiswahili',
            'switcher' => [
                'language' => 'Lugha',
                'question' => 'Badilisha lugha ya programu kuwa :language?',
                'note' => 'Hii inabadilisha lugha kwako tu, si kwa watu wengine.',
                'confirm' => 'Ndiyo, badilisha',
                'cancel' => 'Ghairi',
                'changed' => 'Lugha sasa ni :language.',
                'undo' => 'Rudi kwenye :language',
            ],
        ],
    ],
];
