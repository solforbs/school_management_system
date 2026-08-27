<?php

namespace App\Http\Middleware;

use App\Http\Controllers\OnboardingController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HasInstitution
{
    /**
     * Handle an incoming request.
     *
     * A Director may own more than one institution, so beyond just having
     * one, they need one chosen as "active" - everything else (dashboard,
     * billing, modules) runs against that one. With exactly one
     * institution and nothing chosen yet, it's auto-activated so a
     * single-school Director never sees an extra step; with more than
     * one, they're sent to the institutions index to pick.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Admins own the platform, not a school - nothing to onboard.
        if (isAdmin()) {
            return $next($request);
        }

        // An offline device is a copy of a school that already exists. Its
        // institution row is synced, not owned by whoever is signed in, so
        // the ownership check below would send a paired user through
        // onboarding on a device that has nothing to onboard.
        if (syncClientMode()) {
            return $next($request);
        }

        // Parents, Students, Teachers and Accountants are attached to a
        // school by someone else (a Director) - they never own an
        // institution themselves, so they must never be forced through
        // onboarding.
        if ($user->hasAnyRole(['Parent', 'Student', 'Teacher', 'Accountant'])) {
            return $next($request);
        }

        $institutionCount = $user->institution()->count();

        // Anyone else without an institution yet (a brand new sign-up, or a
        // Director who hasn't registered their school) is sent to onboard.
        if ($institutionCount === 0) {
            // The school can't be registered until the setup fee is
            // settled. With no fee configured, setupFeeSettled() is true
            // for everyone and this is the same single step it always was.
            return OnboardingController::setupFeeSettled($user)
                ? redirect()->route('institution.create')
                : redirect()->route('onboarding.plan');
        }

        // The institution routes themselves - including where choosing
        // happens - always stay reachable, regardless of active-selection
        // state.
        if ($request->is('dashboard/institutions*')) {
            return $next($request);
        }

        // A previously-chosen institution that's no longer owned (lost,
        // transferred) leaves a stale pointer - treat it as unset.
        if ($user->active_institution_id && ! $user->institution()->where('id', $user->active_institution_id)->exists()) {
            $user->update(['active_institution_id' => null]);
            $user->unsetRelation('activeInstitution');
        }

        if (! $user->active_institution_id) {
            if ($institutionCount === 1) {
                $user->update(['active_institution_id' => $user->institution()->value('id')]);
                // Global middleware earlier in this same request (approval/
                // payment gates) may have already resolved and cached
                // activeInstitution as null via currentInstitution() -
                // without this, that stale cache would leak into the rest
                // of the request (sidebar, controllers) despite the id
                // being updated.
                $user->unsetRelation('activeInstitution');

                return $next($request);
            }

            return redirect()->route('institution.index')
                ->with('warning', 'Choose which school to manage.');
        }

        return $next($request);
    }
}
