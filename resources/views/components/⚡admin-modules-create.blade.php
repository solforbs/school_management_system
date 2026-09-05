<?php

use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Nwidart\Modules\Facades\Module;

/**
 * Scaffolds a brand-new module from the dashboard.
 *
 * This used to be able to take the whole application down: it called a
 * nonexistent Artisan command (`module:make-service-provider` - the real
 * name is `module:make-provider`, and `module:make` already runs it
 * internally anyway) and passed Livewire's validated-data array where a
 * module name string was expected, so every click threw an uncaught
 * exception. Worse, `module:make` activates a module immediately, and
 * nwidart/laravel-modules boots every *active* module's service provider on
 * every request across the whole site (not just admin pages) - so a module
 * whose provider class never became autoloadable (a silently-failed
 * `composer dump-autoload`, a bad name producing invalid PHP) turned into a
 * site-wide fatal error the moment anyone loaded any page, admin or not.
 * That exact failure is exactly what happened live: creating a "Standard"
 * module took the whole app down with "Class ... StandardServiceProvider
 * not found" until it was manually disabled outside the app.
 *
 * Two things make this safe now:
 *
 * 1. The module name is validated as a safe PHP identifier, checked for
 *    collisions, before anything touches the filesystem.
 * 2. The module is always created *disabled* and is NEVER auto-enabled
 *    here, even after a apparently-successful scaffold. This isn't just
 *    caution - it's a hard requirement: `module:make` refreshes Composer's
 *    autoloader via a subprocess, but a PHP process's own autoloader is
 *    loaded once at boot and can't see a file another process just
 *    rewrote. So "is the new class loadable" can never be answered
 *    correctly in this same request no matter how reliable composer is -
 *    only a later, separate request can see the update. That's exactly
 *    what the "Enable" button below (see ⚡admin-modules-index.blade.php)
 *    is for: it performs that loadability check in a fresh request, where
 *    it's actually meaningful, before ever flipping the module live.
 */
new class extends Component {
    #[Validate('required|string|max:50')]
    public string $name = '';

    public function mount(): void
    {
        $this->name = '';
    }

    public function createModule(): void
    {
        $this->validate();

        $studly = Str::studly(trim($this->name));

        if ($studly === '' || ! preg_match('/^[A-Z][A-Za-z0-9]*$/', $studly)) {
            $this->addError('name', 'Module name must contain only letters and numbers, and start with a letter.');

            return;
        }

        if (in_array(strtolower($studly), self::reservedNames(), true)) {
            $this->addError('name', "\"{$studly}\" is a reserved name and can't be used for a module.");

            return;
        }

        // Module::has() already compares case-insensitively, which matters
        // here beyond the obvious duplicate check: two modules whose names
        // differ only by case would collide as folders on a
        // case-insensitive filesystem (Windows/most local dev setups).
        if (Module::has($studly)) {
            $this->addError('name', "A module named \"{$studly}\" already exists.");

            return;
        }

        try {
            // --disabled: always. See the class docblock for why this can
            // never be safely flipped to enabled within this same request.
            $exitCode = Artisan::call('module:make', [
                'name' => [$studly],
                '--disabled' => true,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException('module:make reported a failure: '.trim(Artisan::output()));
            }
        } catch (\Throwable $e) {
            Log::error('Module scaffolding failed', ['module' => $studly, 'error' => $e->getMessage()]);
            Flux::toast(text: "Couldn't create \"{$studly}\": ".$e->getMessage(), variant: 'danger');

            return;
        }

        // Bonus starter scaffolding beyond the base module - genuinely
        // optional, so a failure here never undoes the module that's
        // already safely (and disabled) created above.
        $this->addStarterScaffolding($studly);

        Flux::toast(
            text: "\"{$studly}\" module created. It's left disabled on purpose - click \"Enable\" on it below to activate it.",
            variant: 'success',
        );
        $this->reset('name');
        $this->dispatch('loadModules');
    }

    private function addStarterScaffolding(string $studly): void
    {
        $steps = [
            fn () => Artisan::call('module:make-controller', ['controller' => 'HomeController', 'module' => $studly]),
            fn () => Artisan::call('module:make-model', ['model' => 'Example', 'module' => $studly]),
            fn () => Artisan::call('module:make-migration', ['name' => 'create_examples_table', 'module' => $studly]),
        ];

        foreach ($steps as $step) {
            try {
                $step();
            } catch (\Throwable $e) {
                Log::warning('Optional module starter scaffolding step failed', [
                    'module' => $studly,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private static function reservedNames(): array
    {
        return ['app', 'modules', 'vendor', 'public', 'storage', 'config', 'database', 'bootstrap', 'resources', 'routes', 'tests'];
    }
};
?>

<div>
    <form wire:submit.prevent="createModule">
        <div class="mb-4">
            <flux:input label="Module name" wire:model="name" placeholder="e.g Library" />
            <flux:error name="name" />
        </div>
        <flux:button type="submit" icon="plus" label="create">Create</flux:button>
    </form>
</div>
