<?php

use App\Models\Setting;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

// Google sign-in is gated on an admin having switched it on AND a client id
// being resolvable. Most tests here exercise the happy path, so wire that up
// once; the "disabled" cases override it explicitly.
beforeEach(function () {
    Setting::set(Setting::GOOGLE_LOGIN, true);
    Setting::put(Setting::GOOGLE_CLIENT_ID, 'test-client');
});

/**
 * Stub the Google provider so `Socialite::driver('google')->user()` returns
 * a fixed identity (or throws).
 */
function fakeGoogleUser(string $id, ?string $email, string $name = 'Google Person'): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->allows(['getId' => $id, 'getEmail' => $email, 'getName' => $name]);

    $provider = Mockery::mock(Provider::class);
    $provider->allows('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('the login page shows a Continue with Google button', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('auth.google.redirect'), escape: false)
        ->assertSee('Continue with Google');
});

test('the redirect route sends the visitor to Google', function () {
    config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'secret']);

    $provider = Mockery::mock(Provider::class);
    $provider->allows('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth?x=1'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.redirect'))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth?x=1');
});

test('an existing account is matched by email and linked on first Google sign-in', function () {
    $user = User::factory()->create(['email' => 'director@example.com', 'google_id' => null]);

    fakeGoogleUser('google-abc', 'director@example.com');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('google-abc');
});

test('an unknown Google email creates a verified account and signs in', function () {
    expect(User::where('email', 'newbie@example.com')->exists())->toBeFalse();

    fakeGoogleUser('google-new', 'newbie@example.com', 'New Bie');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    $created = User::where('email', 'newbie@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('New Bie')
        ->and($created->google_id)->toBe('google-new')
        ->and($created->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($created);
});

test('a suspended account cannot sign in with Google', function () {
    User::factory()->create(['email' => 'gone@example.com', 'status' => 'suspended']);

    fakeGoogleUser('google-sus', 'gone@example.com');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
});

test('the button is hidden and the routes 404 when Google sign-in is switched off', function () {
    Setting::set(Setting::GOOGLE_LOGIN, false);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Continue with Google');

    $this->get(route('auth.google.redirect'))->assertNotFound();
    $this->get(route('auth.google.callback'))->assertNotFound();
});

test('the button is hidden and the routes 404 until a client id is configured', function () {
    Setting::put(Setting::GOOGLE_CLIENT_ID, null);
    config(['services.google.client_id' => null]);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Continue with Google');

    $this->get(route('auth.google.redirect'))->assertNotFound();
});

test('the callback uses the client id an admin saved in settings', function () {
    Setting::put(Setting::GOOGLE_CLIENT_ID, 'db-client');
    config(['services.google.client_id' => 'env-client']);

    fakeGoogleUser('google-cfg', 'cfg@example.com');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard', absolute: false));

    expect(config('services.google.client_id'))->toBe('db-client');
});

test('a cancelled or failed Google handshake returns to login with an error', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->allows('user')->andThrow(new RuntimeException('invalid state'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});
