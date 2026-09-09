<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Institution\Database\Seeders\DemoAccountSeeder;

/**
 * Lets an onboarding director step into the shared demo school and back
 * out again. The real user's id is parked in the session so "Leave demo"
 * can restore them.
 */
class DemoAccessController extends Controller
{
    private const RETURN_KEY = 'demo.return_to';

    public function enter(): RedirectResponse
    {
        $user = Auth::user();

        // Only for a director who hasn't set up their own school yet -
        // there's nothing for anyone else to preview here.
        abort_unless($user && ! isAdmin() && ! $user->institution()->exists(), 403);

        $demoDirector = User::where('email', DemoAccountSeeder::DIRECTOR_EMAIL)->first();

        abort_unless($demoDirector !== null, 503, 'The demo school is not available right now.');

        session([self::RETURN_KEY => $user->id]);
        Auth::login($demoDirector);

        Log::info('Demo entered', ['user_id' => $user->id]);

        return redirect()->route('dashboard');
    }

    public function leave(): RedirectResponse
    {
        $originalId = session(self::RETURN_KEY);
        $original = $originalId ? User::find((int) $originalId) : null;

        session()->forget(self::RETURN_KEY);

        if ($original) {
            Auth::login($original);

            return redirect()->route('onboarding.plan');
        }

        // No one to go back to (session lost) - just sign out of the demo.
        Auth::logout();

        return redirect()->route('login');
    }
}
