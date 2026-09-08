<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Signing in as another user, and stepping back out.
 *
 * The real user's id is parked in the session ({@see Impersonation::SESSION_KEY})
 * so the banner can offer a one-click return. Who may do this to whom is
 * decided entirely by {@see Impersonation::canImpersonate()}.
 */
class ImpersonationController extends Controller
{
    public function start(User $user): RedirectResponse
    {
        $actor = Auth::user();

        abort_unless(Impersonation::canImpersonate($actor, $user), 403);

        session([Impersonation::SESSION_KEY => $actor->id]);

        // Auth::login() migrates the session (id rotates, data kept), so the
        // impersonator id set just above survives.
        Auth::login($user);

        Log::info('Impersonation started', [
            'impersonator_id' => $actor->id,
            'target_id' => $user->id,
        ]);

        return redirect()->route('dashboard');
    }

    public function leave(): RedirectResponse
    {
        $originalId = session(Impersonation::SESSION_KEY);
        $original = $originalId ? User::find((int) $originalId) : null;

        abort_unless($original !== null, 403);

        $wasActingAs = Auth::id();

        session()->forget(Impersonation::SESSION_KEY);
        Auth::login($original);

        Log::info('Impersonation ended', [
            'impersonator_id' => $original->id,
            'target_id' => $wasActingAs,
        ]);

        return redirect()->route('dashboard');
    }
}
