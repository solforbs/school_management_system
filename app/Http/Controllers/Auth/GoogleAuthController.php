<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * "Continue with Google" on the login page.
 *
 * An existing account is matched by its stored google_id, then by e-mail;
 * an unknown Google e-mail creates a fresh account (self-registration is
 * already open via Fortify), which then lands on onboarding like any other
 * new sign-up.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(Setting::googleLoginEnabled(), 404);
        $this->configureDriver();

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(Setting::googleLoginEnabled(), 404);
        $this->configureDriver();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google sign-in failed at callback', ['error' => $e->getMessage()]);

            return $this->fail('Google sign-in was cancelled or could not be completed.');
        }

        $email = $googleUser->getEmail();

        if (blank($email)) {
            return $this->fail('Google did not share an e-mail address, so we could not sign you in.');
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $email)
            ->first();
        $isNew = $user === null;

        if ($user && $user->status === 'suspended') {
            return $this->fail('Your account has been suspended. Please contact support.');
        }

        if ($isNew) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ]);
        }

        // Returning users get their Google identity linked for next time.
        if (blank($user->google_id)) {
            $user->google_id = $googleUser->getId();
            $user->save();
        }

        // Google has already confirmed the address.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        Log::info('Google sign-in', ['user_id' => $user->id, 'new' => $isNew]);

        return redirect()->intended(route('dashboard'));
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }

    /**
     * Point the Socialite driver at whatever the Admin saved on the site
     * settings page (falling back to config/services.php), since Socialite
     * reads the credentials straight from config at call time.
     */
    private function configureDriver(): void
    {
        config(['services.google' => Setting::googleOauth()]);
    }
}
