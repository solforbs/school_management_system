<?php

namespace App\Livewire;

use App\Services\ConfigurationManager;
use App\Services\EnvManager;
use Flux\Flux;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class EnvironmentConfiguration extends Component
{
    /** @var array<string, array<string, ?string>> */
    public array $configuration = [];

    /** @var array<string, ?string> */
    public array $originalValues = [];

    /** @var array<string, bool> */
    public array $secrets = [];

    public string $activeCategory = 'general';

    public bool $showSensitiveValues = false;

    protected function getEnvManager(): EnvManager
    {
        return app(EnvManager::class);
    }

    public function mount(): void
    {
        abort_unless(isAdmin(), 403);

        $this->loadConfiguration();
    }

    protected function loadConfiguration(): void
    {
        $envManager = $this->getEnvManager();
        $categories = ConfigurationManager::getCategories();

        foreach ($categories as $category => $vars) {
            $this->configuration[$category] = [];

            foreach (array_keys($vars) as $key) {
                $value = $envManager->get($key);
                $this->configuration[$category][$key] = $value;
                $this->originalValues[$key] = $value;

                // Track which fields are secrets
                if (ConfigurationManager::isSecret($key)) {
                    $this->secrets[$key] = true;
                }
            }
        }
    }

    #[Computed]
    /** @return array<string, mixed> */
    public function categories(): array
    {
        return ConfigurationManager::getCategories();
    }

    #[Computed]
    /** @return array<string, mixed> */
    public function currentCategory(): array
    {
        return $this->categories()[$this->activeCategory] ?? [];
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'configuration.')) {
            // Extract category and key from property name like 'configuration.mail.MAIL_HOST'
            preg_match('/configuration\.(\w+)\.(\w+)/', $property, $matches);
            if (count($matches) === 3) {
                $category = $matches[1];
                $key = $matches[2];

                // Validate immediately
                $rule = ConfigurationManager::getRules($key);
                if ($rule) {
                    $this->validateOnly($property, [$property => $rule]);
                }
            }
        }
    }

    public function save(): void
    {
        abort_unless(isAdmin(), 403);

        $rules = [];
        $values = [];

        // Build strictly from the server-side allow-list. The `configuration`
        // property is client-hydrated, so iterating it directly would let a
        // crafted Livewire payload write any key at all into .env.
        foreach (ConfigurationManager::getCategories() as $category => $vars) {
            foreach (array_keys($vars) as $key) {
                $rule = ConfigurationManager::getRules($key);
                if ($rule) {
                    $rules["configuration.{$category}.{$key}"] = $rule;
                }

                $values[$key] = $this->configuration[$category][$key] ?? null;
            }
        }

        $this->validate($rules);

        $toSave = [];
        foreach ($values as $key => $value) {
            if ($value !== ($this->originalValues[$key] ?? null)) {
                $toSave[$key] = $value;
            }
        }

        if (empty($toSave)) {
            Flux::toast(variant: 'info', text: __('No changes to save.'));

            return;
        }

        // Save secrets encrypted, others as plain values
        $envManager = $this->getEnvManager();
        foreach ($toSave as $key => $value) {
            if (ConfigurationManager::isSecret($key)) {
                if (blank($value)) {
                    // Empty secret field - keep existing value
                    continue;
                }
                // Store encrypted
                $encrypted = Crypt::encryptString($value);
                $envManager->put($key, $encrypted);
            } else {
                $envManager->put($key, $value);
            }
        }

        // Reload to get fresh values
        $this->loadConfiguration();

        Flux::toast(variant: 'success', text: __('Configuration saved successfully.'));
    }

    public function resetConfiguration(): void
    {
        abort_unless(isAdmin(), 403);

        $this->loadConfiguration();

        Flux::toast(variant: 'info', text: __('Configuration reset to saved values.'));
    }

    public function render(): View
    {
        return view('livewire.environment-configuration');
    }
}
