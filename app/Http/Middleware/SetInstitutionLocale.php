<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetInstitutionLocale
{
    /**
     * Renders the app in the language the signed-in user's institution
     * chose. Guests and admins keep the app default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = currentInstitution()?->locale;

        if ($locale && array_key_exists($locale, config('locales.available'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
