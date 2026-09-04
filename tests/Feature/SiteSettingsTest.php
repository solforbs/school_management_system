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

test('a non-admin cannot mount the site settings component', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::admin.site-settings')
        ->assertForbidden();
});
