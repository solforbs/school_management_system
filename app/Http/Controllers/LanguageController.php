<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetInstitutionLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A person switching the app language for themselves. The language they
 * came from is flashed so the next page can offer to undo the switch.
 */
class LanguageController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('locales.available')))],
            'undo' => ['nullable', 'boolean'],
        ]);

        $previous = app()->getLocale();

        SetInstitutionLocale::remember($validated['locale']);

        $redirect = redirect()->back(fallback: route('home'));

        if ($previous === $validated['locale'] || $request->boolean('undo')) {
            return $redirect;
        }

        return $redirect->with('locale_previous', $previous);
    }
}
