<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Nwidart\Modules\Facades\Module;

new class extends Component {
    /** The module the delete dialog is confirming. */
    public ?string $confirmingModule = null;

    #[On('loadModules')]
    public function modules()
    {
        $modules = Module::all();
        return $modules;
    }
    public function enableClickedModule($name)
    {
        Module::enable($name);
        $this->dispatch('loadModules');
        Flux::toast(text: $name . ' module enabled!', variant: 'success');
    }
    public function disableClickedModule($name)
    {
        Module::disable($name);
        $this->dispatch('loadModules');
        Flux::toast(text: $name . ' module disabled!', variant: 'success');
    }
    public function deleteClickedModule($name = null)
    {
        $name ??= $this->confirmingModule;

        if (! $name) {
            return;
        }

        $this->confirmingModule = null;
        Flux::modal('confirm-delete-module')->close();

        Module::delete($name);
        $this->dispatch('loadModules');
        Flux::toast(text: $name . ' module deleted!', variant: 'success');
    }
};
?>

<div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem;">
    @forelse ($this->modules() as $module)
        <flux:card class="relative overflow-hidden transition-all duration-200 hover:shadow-lg">
            <!-- Decorative accent bar -->
            <div class="absolute top-0 left-0 w-1 h-full {{ $module->isEnabled() ? 'bg-green-500' : 'bg-red-500' }}">
            </div>

            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="lg" class="font-semibold">
                        {{ $module->getName() }}
                    </flux:heading>

                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                        Module Package
                    </flux:text>
                </div>

                <!-- Status Badge -->
                <flux:badge :color="$module->isEnabled() ? 'green' : 'red'" class="shrink-0">
                    <span
                        class="w-2 h-2 rounded-full {{ $module->isEnabled() ? 'bg-green-500' : 'bg-red-500' }} inline-block mr-1.5"></span>
                    {{ $module->isEnabled() ? 'Active' : 'Inactive' }}
                </flux:badge>
            </div>

            <flux:spacer class="mb-6" />

            <!-- Action Buttons -->
            <div class="flex items-center gap-3 flex-wrap">
                @if ($module->isEnabled())
                    <flux:button class="flex-1" variant="outline" icon="x-circle"
                        wire:click="disableClickedModule('{{ $module->getName() }}')">
                        Disable
                    </flux:button>
                    <flux:button href="{{ route(strtolower($module->getName()) . '.index') }}" icon="eye" wire:navigate>
                        Visit
                    </flux:button>
                @else
                    <flux:button class="flex-1" variant="primary" icon="check-badge"
                        wire:click="enableClickedModule('{{ $module->getName() }}')">
                        Enable
                    </flux:button>
                @endif


                <flux:modal.trigger name="confirm-delete-module">
                    <flux:button variant="danger" icon="trash"
                        wire:click="$set('confirmingModule', '{{ $module->getName() }}')">
                        Delete
                    </flux:button>
                </flux:modal.trigger>
            </div>
        </flux:card>
    @empty
    @endforelse

    <flux:modal name="confirm-delete-module" class="max-w-md" wire:close="$set('confirmingModule', null)">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Delete this module?</flux:heading>
                <flux:subheading>
                    <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $confirmingModule }}</span>
                    will be removed from disk. This action can't be undone.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="filled">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="danger" wire:click="deleteClickedModule">Delete Module</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
