{{--
    Machine-translates every page into the institution's language using
    Google's page translator, driven by its "googtrans" cookie (or its hidden
    widget when the cookie is refused). English loads nothing, so pages
    render as-is.

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
    $translatingLabel = config('locales.available.'.$locale.'.switcher.translating');
@endphp

<script>
    (function () {
        var target = @js($translateTo);
        var host = window.location.hostname;

        // The "googtrans" cookie is the main way in: Google translates from it
        // as soon as it loads and is told the source is English. (Choosing a
        // language in the widget makes Google guess the source instead, and
        // the switcher's own Swahili/French/Arabic phrases can make it guess
        // the page is already translated.) Stale copies on the bare host are
        // cleared first so only this one applies.
        ['', '; domain=' + host].forEach(function (domain) {
            document.cookie = 'googtrans=; path=/' + domain + '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        });
        if (target) {
            document.cookie = 'googtrans=/en/' + target + '; path=/';
        }

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
            if (!window.google || !google.translate || !google.translate.TranslateElement || !document.body) {
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
            var target = window.__autoTranslateTarget;
            var combo = document.querySelector('#google_translate_element .goog-te-combo');

            // The <select> appears before Google fills in its options; setting
            // the value earlier silently fails and no translation happens.
            if (!combo || !combo.querySelector('option[value="' + target + '"]')) {
                if (attempt < 100) {
                    setTimeout(function () { applyLanguage(attempt + 1); }, 50);
                }
                return;
            }

            combo.value = target;
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

        // Fetched straight away from <head> rather than after the page has
        // loaded, so the download overlaps the page's own loading.
        function requestScript() {
            if (scriptRequested || !window.__autoTranslateTarget) {
                return;
            }
            scriptRequested = true;

            window.googleTranslateElementInit = function () {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', translatePage);
                } else {
                    translatePage();
                }
            };

            var script = document.createElement('script');
            script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            script.async = true;
            document.head.appendChild(script);
        }

        // Google needs a few seconds after each full page load. Until the
        // first translated text appears, say so in the chosen language, so
        // the English flash doesn't look like the switch failed.
        function showProgress() {
            if (document.getElementById('auto-translate-progress') || document.querySelector('font')) {
                return;
            }

            var pill = document.createElement('div');
            pill.id = 'auto-translate-progress';
            pill.className = 'notranslate';
            pill.setAttribute('translate', 'no');
            pill.setAttribute('role', 'status');
            pill.textContent = @js($translatingLabel);
            document.body.appendChild(pill);

            var startedAt = Date.now();
            var poll = setInterval(function () {
                if (document.querySelector('font') || Date.now() - startedAt > 15000) {
                    clearInterval(poll);
                    pill.remove();
                }
            }, 150);
        }

        function translatePage() {
            if (!window.__autoTranslateTarget) {
                return;
            }

            protectAlpineBindings(document.body);
            requestScript();
            showProgress();

            // The widget only needs telling when the browser refused the cookie
            // (e.g. multi-part TLDs like .co.ke); doing both makes two
            // translations race and can leave the page in English.
            if (mountWidget() && document.cookie.indexOf('googtrans=/en/') === -1) {
                applyLanguage();
            }
        }

        function scheduleRetranslate() {
            clearTimeout(retranslateTimer);
            retranslateTimer = setTimeout(translatePage, 120);
        }

        requestScript();

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

    #auto-translate-progress {
        position: fixed; z-index: 60; bottom: 1rem; left: 50%; transform: translateX(-50%);
        padding: .375rem .875rem; border-radius: 9999px; font-size: .75rem; font-weight: 600;
        background: #18181b; color: #fff; box-shadow: 0 4px 12px rgb(0 0 0 / .15);
    }
</style>
