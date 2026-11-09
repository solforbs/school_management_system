{{--
    Machine-translates every page into the institution's language using
    Google's page translator. The "googtrans" cookie tells it which
    language to switch to on load; English clears it so pages render as-is.

    wire:navigate swaps <body> (dropping the translator's widget) and
    Livewire morphs put fresh English text back into the DOM, so the
    page is re-translated after every navigation and component update.

    Layouts must keep <html lang="en">: the text is written in English, and
    if lang names the target language Google treats the page as already
    translated and leaves it untouched. Google sets lang itself afterwards.
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
        var domains = ['', '; domain=' + host];

        domains.forEach(function (domain) {
            document.cookie = target
                ? 'googtrans=/en/' + target + '; path=/' + domain
                : 'googtrans=; path=/' + domain + '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        });

        window.__autoTranslateTarget = target;

        // Head scripts only run once per full page load, so the listeners
        // below are registered a single time and survive wire:navigate.
        if (window.__autoTranslateBooted) {
            return;
        }
        window.__autoTranslateBooted = true;

        var scriptRequested = false;
        var retranslateTimer = null;

        function mountWidget() {
            if (!window.google || !google.translate || !google.translate.TranslateElement) {
                return false;
            }

            if (!document.getElementById('google_translate_element')) {
                var holder = document.createElement('div');
                holder.id = 'google_translate_element';
                holder.style.display = 'none';
                holder.setAttribute('translate', 'no');
                document.body.appendChild(holder);
            }

            if (!document.querySelector('#google_translate_element .goog-te-combo')) {
                new google.translate.TranslateElement({ pageLanguage: 'en', autoDisplay: false }, 'google_translate_element');
            }

            return true;
        }

        function applyLanguage(attempt) {
            attempt = attempt || 0;
            var combo = document.querySelector('#google_translate_element .goog-te-combo');

            if (!combo) {
                if (attempt < 20) {
                    setTimeout(function () { applyLanguage(attempt + 1); }, 150);
                }
                return;
            }

            combo.value = window.__autoTranslateTarget;
            combo.dispatchEvent(new Event('change'));
        }

        // Google rebuilds translated sentences from clones, which strips
        // Alpine's scope from x-text/x-html nodes ("count is not defined").
        // Leaving those nodes untranslated keeps them bound. Contact links
        // and avatars hold people's details, which must never be translated;
        // names elsewhere are marked translate="no" in the views.
        var untranslatable = '[x-text],[x-html],a[href^="tel:"],a[href^="mailto:"],[data-flux-avatar],[data-flux-profile],[data-flux-sidebar-profile]';

        function protectAlpineBindings(root) {
            if (!window.__autoTranslateTarget || !root || !root.querySelectorAll) {
                return;
            }

            if (root.matches && root.matches(untranslatable)) {
                root.classList.add('notranslate');
            }

            root.querySelectorAll(untranslatable).forEach(function (el) {
                el.classList.add('notranslate');
            });
        }

        function translatePage() {
            var target = window.__autoTranslateTarget;

            if (!target) {
                return;
            }

            protectAlpineBindings(document.body);

            if (!scriptRequested) {
                scriptRequested = true;
                // Don't rely on the googtrans cookie alone: on real domains it
                // can be rejected (e.g. multi-part TLDs like .co.ke), so the
                // language is also applied explicitly once the widget exists.
                window.googleTranslateElementInit = function () {
                    if (mountWidget()) {
                        applyLanguage();
                    }
                };

                var script = document.createElement('script');
                script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
                script.async = true;
                document.head.appendChild(script);

                return;
            }

            if (mountWidget()) {
                applyLanguage();
            }
        }

        function scheduleRetranslate() {
            clearTimeout(retranslateTimer);
            retranslateTimer = setTimeout(translatePage, 120);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', translatePage);
        } else {
            translatePage();
        }

        document.addEventListener('livewire:navigated', function () {
            protectAlpineBindings(document.body);
            scheduleRetranslate();
        });

        document.addEventListener('livewire:init', function () {
            // Runs synchronously after a morph, before Google's observer
            // reacts, so re-rendered bindings are protected in time.
            Livewire.hook('morphed', function (payload) {
                protectAlpineBindings(payload && payload.el);
            });

            Livewire.hook('commit', function (payload) {
                payload.succeed(function () {
                    scheduleRetranslate();
                });
            });
        });
    })();
</script>

<style>
    /* Hide Google's toolbar and hover popups; keep the page where it was. */
    .goog-te-banner-frame, iframe.skiptranslate, .goog-te-gadget, #goog-gt-tt, .goog-te-balloon-frame, .VIpgJd-ZVi9od-ORHb-OEVmcd { display: none !important; }
    body { top: 0 !important; }
    .goog-text-highlight { background: none !important; box-shadow: none !important; }
</style>
