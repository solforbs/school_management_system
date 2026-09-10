<?php

namespace App\Livewire;

use App\Services\ConfigurationManager;
use App\Services\EnvManager;
use Flux\Flux;
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

                // A write-only value is never sent to the browser - the field
                // starts blank and only a typed replacement is written back.
                // `configuration` is a public Livewire property, so anything
                // put here ends up in the payload the client can read.
                if (ConfigurationManager::isWriteOnly($key)) {
                    $this->secrets[$key] = filled($value);
                    $this->configuration[$category][$key] = '';
                    $this->originalValues[$key] = '';

                    continue;
                }

                $this->configuration[$category][$key] = $value;
                $this->originalValues[$key] = $value;
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

        // Everything is written in plain text: each of these is read back
        // through env() by the config that consumes it, so an encrypted
        // value would reach the provider verbatim. See isWriteOnly().
        $envManager = $this->getEnvManager();
        foreach ($toSave as $key => $value) {
            // A blank write-only field means "leave what is already stored"
            // rather than "clear it" - the field always renders empty.
            if (ConfigurationManager::isWriteOnly($key) && blank($value)) {
                continue;
            }

            $envManager->put($key, $value);
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
