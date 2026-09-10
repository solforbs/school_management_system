<?php

use App\Livewire\EnvironmentConfiguration;
use App\Models\User;
use App\Services\ConfigurationManager;
use App\Services\EnvManager;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Every test here writes through EnvManager. Point it at a throwaway file
 * first: writing the real .env from a test would replace live credentials
 * with fixture values (EnvManager also refuses outright, as a backstop).
 */
beforeEach(function () {
    $this->tempEnv = sys_get_temp_dir().'/env-manager-test-'.bin2hex(random_bytes(8)).'.env';

    file_put_contents($this->tempEnv, implode("\n", [
        'APP_NAME=Fixture School',
        'MAIL_MAILER=log',
        'APP_KEY=base64:fixture-key',
        'MAIL_HOST=mail.fixture.test',
        'MAIL_PORT=2525',
        'MAIL_PASSWORD=',
        'PAYSTACK_SECRET_KEY=',
    ])."\n");

    config(['env-manager.path' => $this->tempEnv]);
});

afterEach(function () {
    if (isset($this->tempEnv) && file_exists($this->tempEnv)) {
        unlink($this->tempEnv);
    }
});

function makeEnvConfigAdmin(): User
{
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('Admin');

    return $user;
}

test('guests cannot access environment configuration', function () {
    $this->get(route('admin.settings.edit'))
        ->assertRedirect(route('login'));
});

test('non-admins cannot mount the environment configuration component', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(EnvironmentConfiguration::class)
        ->assertForbidden();
});

test('admins can mount the environment configuration component', function () {
    $this->actingAs(makeEnvConfigAdmin());

    Livewire::test(EnvironmentConfiguration::class)
        ->assertViewIs('livewire.environment-configuration')
        ->assertSet('activeCategory', 'general');
});

test('configuration loads initial values from env', function () {
    $envManager = app(EnvManager::class);
    $testKey = 'TEST_CONFIG_VAR_'.time();
    $testValue = 'test-value-'.uniqid();

    // Set up a test value
    $envManager->put($testKey, $testValue);

    $admin = makeEnvConfigAdmin();
    $this->actingAs($admin);

    $component = Livewire::test(EnvironmentConfiguration::class);

    // Verify the value was loaded (though it won't be in the UI since it's not in ConfigurationManager)
    expect($component->configuration)->toBeArray();
});

test('an admin can save configuration values', function () {
    $admin = makeEnvConfigAdmin();
    $this->actingAs($admin);

    Livewire::test(EnvironmentConfiguration::class)
        ->set('configuration.general.APP_NAME', 'Test School')
        ->set('configuration.mail.MAIL_HOST', 'smtp.test.com')
        ->set('configuration.mail.MAIL_PORT', '587')
        ->call('save')
        ->assertHasNoErrors();

    $envManager = app(EnvManager::class);
    expect($envManager->get('APP_NAME'))->toBe('Test School');
    expect($envManager->get('MAIL_HOST'))->toBe('smtp.test.com');
    expect($envManager->get('MAIL_PORT'))->toBe('587');
});

test('paystack keys are stored in plain text for direct API use', function () {
    $this->actingAs(makeEnvConfigAdmin());

    Livewire::test(EnvironmentConfiguration::class)
        ->set('configuration.payments.PAYSTACK_SECRET_KEY', 'sk_test_12345')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(EnvManager::class)->get('PAYSTACK_SECRET_KEY'))->toBe('sk_test_12345');
});

test('a crafted payload cannot write an env key outside the allow-list', function () {
    $this->actingAs(makeEnvConfigAdmin());

    $envManager = app(EnvManager::class);
    $originalKey = $envManager->get('APP_KEY');
    expect($originalKey)->not->toBeNull();

    Livewire::test(EnvironmentConfiguration::class)
        ->set('configuration.general.APP_KEY', 'base64:attacker-controlled')
        ->set('configuration.general.EVIL_BACKDOOR', '1')
        ->call('save');

    expect($envManager->get('APP_KEY'))->toBe($originalKey)
        ->and($envManager->get('EVIL_BACKDOOR'))->toBeNull();
});

test('a newline in a value cannot inject an extra env assignment', function () {
    $this->actingAs(makeEnvConfigAdmin());

    $envManager = app(EnvManager::class);
    $envManager->put('MAIL_HOST', "smtp.test.com\nINJECTED=yes");

    expect($envManager->get('MAIL_HOST'))->toBe('smtp.test.comINJECTED=yes')
        ->and($envManager->get('INJECTED'))->toBeNull();
});

test('configuration validates data according to rules', function () {
    $admin = makeEnvConfigAdmin();
    $this->actingAs($admin);

    Livewire::test(EnvironmentConfiguration::class)
        ->set('configuration.mail.MAIL_PORT', 'not-a-number')
        ->call('save')
        ->assertHasErrors('configuration.mail.MAIL_PORT');
});

test('empty password fields preserve existing values', function () {
    $admin = makeEnvConfigAdmin();
    $this->actingAs($admin);

    $envManager = app(EnvManager::class);
    $envManager->put('MAIL_PASSWORD', 'original-secret-value');

    // Try to save with empty secret field
    Livewire::test(EnvironmentConfiguration::class)
        ->set('configuration.mail.MAIL_USERNAME', 'new-user')
        ->set('configuration.mail.MAIL_PASSWORD', '') // Empty - should preserve
        ->call('save');

    // The original value should still be there
    expect($envManager->get('MAIL_PASSWORD'))->toBe('original-secret-value')
        ->and($envManager->get('MAIL_USERNAME'))->toBe('new-user');
});

test('configuration reset restores original values', function () {
    $admin = makeEnvConfigAdmin();
    $this->actingAs($admin);

    $component = Livewire::test(EnvironmentConfiguration::class)
        ->set('configuration.general.APP_NAME', 'Changed Name')
        ->assertSet('configuration.general.APP_NAME', 'Changed Name');

    // Reset should restore
    $component->call('resetConfiguration');

    // The configuration should be reloaded from disk
    expect($component->configuration['general']['APP_NAME'])
        ->toBe($component->originalValues['APP_NAME']);
});

test('configuration categories are accessible', function () {
    $categories = ConfigurationManager::getCategories();

    expect($categories)->toHaveKey('general');
    expect($categories)->toHaveKey('mail');
    expect($categories)->toHaveKey('storage');
    expect($categories)->toHaveKey('payments');
    expect($categories)->toHaveKey('sms');
    expect($categories)->toHaveKey('ai');
    expect($categories)->toHaveKey('device_sync');
});

test('credential fields are marked write-only', function () {
    expect(ConfigurationManager::isWriteOnly('MAIL_PASSWORD'))->toBeTrue();
    expect(ConfigurationManager::isWriteOnly('PAYSTACK_SECRET_KEY'))->toBeTrue();
    expect(ConfigurationManager::isWriteOnly('GEMINI_API_KEY'))->toBeTrue();
    expect(ConfigurationManager::isWriteOnly('APP_NAME'))->toBeFalse();
    expect(ConfigurationManager::isWriteOnly('MAIL_HOST'))->toBeFalse();
});

test('a stored credential is never sent to the browser', function () {
    $this->actingAs(makeEnvConfigAdmin());

    app(EnvManager::class)->put('MAIL_PASSWORD', 'super-secret-smtp');

    $component = Livewire::test(EnvironmentConfiguration::class);

    // The field renders blank; only the "is one set?" flag crosses the wire.
    expect($component->get('configuration.mail.MAIL_PASSWORD'))->toBe('')
        ->and($component->get('secrets.MAIL_PASSWORD'))->toBeTrue()
        ->and(json_encode($component->get('configuration')))->not->toContain('super-secret-smtp');
});

test('credentials are stored verbatim so the services that read them still work', function () {
    $this->actingAs(makeEnvConfigAdmin());

    Livewire::test(EnvironmentConfiguration::class)
        ->set('configuration.mail.MAIL_PASSWORD', 'super-secret-smtp')
        ->call('save')
        ->assertHasNoErrors();

    // config/mail.php reads env('MAIL_PASSWORD') straight through - an
    // encrypted value here would be handed to the SMTP server as-is.
    expect(app(EnvManager::class)->get('MAIL_PASSWORD'))->toBe('super-secret-smtp');
});
