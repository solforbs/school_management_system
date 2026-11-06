{{--
    Machine-translates every page into the institution's language using
    Google's page translator. The "googtrans" cookie tells it which
    language to switch to on load; English clears it so pages render as-is.
--}}
@php
    $locale = app()->getLocale();
    $overrides = config('locales.machine_translation_codes', []);
    $translateTo = array_key_exists($locale, $overrides) ? $overrides[$locale] : $locale;
@endphp

<script>
    (function () {
        var target = @js($translateTo);
        var host = window.location.hostname;
        var domains = ['', '; domain=' + host, '; domain=.' + host.split('.').slice(-2).join('.')];
        var value = target ? '/en/' + target : '';
        var expiry = target ? '' : '; expires=Thu, 01 Jan 1970 00:00:00 GMT';

        domains.forEach(function (domain) {
            document.cookie = 'googtrans=' + value + '; path=/' + domain + expiry;
        });

        if (!target) {
            return;
        }

        window.googleTranslateElementInit = function () {
            new google.translate.TranslateElement({ pageLanguage: 'en', autoDisplay: false }, 'google_translate_element');
        };

        document.addEventListener('DOMContentLoaded', function () {
            var holder = document.createElement('div');
            holder.id = 'google_translate_element';
            holder.style.display = 'none';
            document.body.appendChild(holder);

            var script = document.createElement('script');
            script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            script.async = true;
            document.head.appendChild(script);
        });
    })();
</script>

@if ($translateTo)
    <style>
        /* Hide Google's toolbar and hover popups; keep the page where it was. */
        .goog-te-banner-frame, iframe.skiptranslate, .goog-te-gadget, #goog-gt-tt, .goog-te-balloon-frame { display: none !important; }
        body { top: 0 !important; position: static !important; }
        .goog-text-highlight { background: none !important; box-shadow: none !important; }
    </style>
@endif
