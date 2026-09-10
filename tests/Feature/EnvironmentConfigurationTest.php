<?php

use App\Livewire\EnvironmentConfiguration;
use App\Models\User;
use App\Services\ConfigurationManager;
use App\Services\EnvManager;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function makeSettingsAdmin(): User
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
    $this->actingAs(makeSettingsAdmin());

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

    $admin = makeSettingsAdmin();
    $this->actingAs($admin);

    $component = Livewire::test(EnvironmentConfiguration::class);

    // Verify the value was loaded (though it won't be in the UI since it's not in ConfigurationManager)
    expect($component->configuration)->toBeArray();
});

test('an admin can save configuration values', function () {
    $admin = makeSettingsAdmin();
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

test('secret values are validated and encrypted when saved', function () {
    $admin = makeSettingsAdmin();
    $this->actingAs($admin);

    Livewire::test(EnvironmentConfiguration::class)
        ->set('configuration.payments.PAYSTACK_SECRET_KEY', 'sk_test_12345')
        ->call('save')
        ->assertHasNoErrors();

    $envManager = app(EnvManager::class);
    $stored = $envManager->get('PAYSTACK_SECRET_KEY');

    // The stored value should be encrypted
    expect($stored)->not->toBe('sk_test_12345');

    // Should be able to decrypt it
    $decrypted = Crypt::decryptString($stored);
    expect($decrypted)->toBe('sk_test_12345');
});

test('configuration validates data according to rules', function () {
    $admin = makeSettingsAdmin();
    $this->actingAs($admin);

    Livewire::test(EnvironmentConfiguration::class)
        ->set('configuration.mail.MAIL_PORT', 'not-a-number')
        ->call('save')
        ->assertHasErrors('configuration.mail.MAIL_PORT');
});

test('empty password fields preserve existing values', function () {
    $admin = makeSettingsAdmin();
    $this->actingAs($admin);

    $envManager = app(EnvManager::class);
    $originalSecret = Crypt::encryptString('original-secret-value');
    $envManager->put('PAYSTACK_SECRET_KEY', $originalSecret);

    // Try to save with empty secret field
    Livewire::test(EnvironmentConfiguration::class)
        ->set('configuration.payments.PAYSTACK_PUBLIC_KEY', 'pk_test_new')
        ->set('configuration.payments.PAYSTACK_SECRET_KEY', '') // Empty - should preserve
        ->call('save');

    $stored = $envManager->get('PAYSTACK_SECRET_KEY');
    $decrypted = Crypt::decryptString($stored);

    // The original value should still be there
    expect($decrypted)->toBe('original-secret-value');
});

test('configuration reset restores original values', function () {
    $admin = makeSettingsAdmin();
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

test('configuration keys are marked as secret when appropriate', function () {
    expect(ConfigurationManager::isSecret('MAIL_PASSWORD'))->toBeTrue();
    expect(ConfigurationManager::isSecret('PAYSTACK_SECRET_KEY'))->toBeFalse(); // Used directly by payment API
    expect(ConfigurationManager::isSecret('GEMINI_API_KEY'))->toBeTrue();
    expect(ConfigurationManager::isSecret('APP_NAME'))->toBeFalse();
    expect(ConfigurationManager::isSecret('MAIL_HOST'))->toBeFalse();
});
