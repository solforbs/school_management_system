<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetInstitutionLocale
{
    /**
     * Picks the language the app renders in, first match wins:
     *
     * 1. `?lang=xx` on any address - the escape hatch for someone stuck in
     *    a language they can't read. It is remembered like a normal choice.
     * 2. The person's own choice, kept in a cookie.
     * 3. The signed-in user's institution default.
     * 4. The app default (English).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $fromQuery = $request->query('lang');

        if (is_string($fromQuery) && self::isAvailable($fromQuery)) {
            self::remember($fromQuery);
            app()->setLocale($fromQuery);

            return $next($request);
        }

        $personal = $request->cookie(config('locales.cookie'));

        app()->setLocale(is_string($personal) && self::isAvailable($personal)
            ? $personal
            : self::institutionDefault());

        return $next($request);
    }

    /**
     * The language a person gets when they have not chosen one themselves.
     */
    public static function institutionDefault(): string
    {
        $locale = currentInstitution()?->locale;

        return is_string($locale) && self::isAvailable($locale)
            ? $locale
            : config('locales.default');
    }

    public static function isAvailable(string $locale): bool
    {
        return array_key_exists($locale, config('locales.available'));
    }

    /**
     * Stores a personal choice. Choosing the institution default clears it,
     * so the person follows the institution again if it later changes.
     */
    public static function remember(string $locale): void
    {
        if ($locale === self::institutionDefault()) {
            Cookie::queue(Cookie::forget(config('locales.cookie')));

            return;
        }

        Cookie::queue(config('locales.cookie'), $locale, 60 * 24 * 365);
    }
}
