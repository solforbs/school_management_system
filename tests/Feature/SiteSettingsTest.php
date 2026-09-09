<?php

use App\Models\Setting;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function makeSettingsAdmin(): User
{
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('Admin');

    return $user;
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin.settings.edit'));

    $response->assertRedirect(route('login'));
});

test('non-admins cannot view the site settings page', function () {
    Role::firstOrCreate(['name' => 'Parent', 'guard_name' => 'web']);

    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('Parent'); // bypasses HasInstitution's onboarding redirect so the request reaches the settings page's own admin check
    $this->actingAs($user);

    $response = $this->get(route('admin.settings.edit'));

    $response->assertForbidden();
});

test('admins can view the site settings page and see every feature toggle', function () {
    $admin = makeSettingsAdmin();
    $this->actingAs($admin);

    $response = $this->get(route('admin.settings.edit'));

    $response->assertOk();

    foreach (Setting::FEATURES as $meta) {
        $response->assertSee($meta['label']);
    }

    $response->assertSee('Sign-in')->assertSee('Client ID');
});

test('the deployment card shows on the site settings page when pulling is enabled', function () {
    config()->set('deploy.enabled', true);
    $this->actingAs(makeSettingsAdmin());

    $this->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('Deployment')
        ->assertSee('Pull updates');
});

test('the deployment card explains how to switch pulling on when it is disabled', function () {
    config()->set('deploy.enabled', false);
    $this->actingAs(makeSettingsAdmin());

    $this->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('Deployment')
        ->assertSee('DEPLOY_PULL_ENABLED')
        ->assertDontSee('Pull updates');
});

test('toggling a feature off persists immediately', function () {
    $admin = makeSettingsAdmin();
    $this->actingAs($admin);

    expect(Setting::isEnabled('sms'))->toBeTrue();

    Livewire::test('pages::admin.site-settings')
        ->set('toggles.sms', false);

    expect(Setting::isEnabled('sms'))->toBeFalse();
});

test('an admin can save Google OAuth credentials, secret encrypted at rest', function () {
    $this->actingAs(makeSettingsAdmin());

    Livewire::test('pages::admin.site-settings')
        ->set('googleClientId', 'abc.apps.googleusercontent.com')
        ->set('googleClientSecret', 'GOCSPX-super-secret')
        ->set('googleRedirect', 'https://sms.example.test/auth/google/callback')
        ->call('saveGoogle')
        ->assertHasNoErrors()
        ->assertSet('googleClientSecret', '')
        ->assertSet('googleSecretConfigured', true);

    expect(Setting::value(Setting::GOOGLE_CLIENT_ID))->toBe('abc.apps.googleusercontent.com')
        ->and(Setting::value(Setting::GOOGLE_REDIRECT))->toBe('https://sms.example.test/auth/google/callback')
        ->and(Setting::secret(Setting::GOOGLE_CLIENT_SECRET))->toBe('GOCSPX-super-secret');

    // The raw column must not hold the plaintext secret.
    $raw = Setting::query()->where('key', Setting::GOOGLE_CLIENT_SECRET)->value('value');
    expect($raw)->not->toBe('GOCSPX-super-secret')
        ->and($raw)->not->toContain('GOCSPX-super-secret');
});

test('a blank secret field on save keeps the stored secret', function () {
    $this->actingAs(makeSettingsAdmin());

    Setting::putSecret(Setting::GOOGLE_CLIENT_SECRET, 'GOCSPX-original');

    Livewire::test('pages::admin.site-settings')
        ->set('googleClientId', 'abc.apps.googleusercontent.com')
        ->set('googleClientSecret', '')
        ->call('saveGoogle')
        ->assertHasNoErrors();

    expect(Setting::secret(Setting::GOOGLE_CLIENT_SECRET))->toBe('GOCSPX-original');
});

test('the Google sign-in switch drives googleLoginEnabled()', function () {
    $this->actingAs(makeSettingsAdmin());

    Setting::put(Setting::GOOGLE_CLIENT_ID, 'abc.apps.googleusercontent.com');

    $component = Livewire::test('pages::admin.site-settings')
        ->set('googleLogin', false);
    expect(Setting::googleLoginEnabled())->toBeFalse();

    $component->set('googleLogin', true);
    expect(Setting::googleLoginEnabled())->toBeTrue();
});

test('a non-admin cannot mount the site settings component', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::admin.site-settings')
        ->assertForbidden();
});
