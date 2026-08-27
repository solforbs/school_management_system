<?php

use App\Models\Setting;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Site Settings')] class extends Component {
    public array $toggles = [];

    public string $setupFee = '0';

    public function mount(): void
    {
        abort_unless(isAdmin(), 403);

        foreach (array_keys(Setting::FEATURES) as $key) {
            $this->toggles[$key] = Setting::isEnabled($key);
        }

        $this->setupFee = (string) Setting::setupFee();
    }

    /**
     * Saved on submit rather than live: a fee typed digit by digit would
     * otherwise be briefly stored as "5", then "50", then "500".
     */
    public function saveSetupFee(): void
    {
        abort_unless(isAdmin(), 403);

        $this->validate(['setupFee' => 'required|numeric|min:0|max:10000000']);

        Setting::put(Setting::SETUP_FEE, (string) round((float) $this->setupFee, 2));

        $this->setupFee = (string) Setting::setupFee();

        Flux::toast(variant: 'success', text: __('Setup fee saved.'));
    }

    public function updated(string $name, $value): void
    {
        if (! str_starts_with($name, 'toggles.')) {
            return;
        }

        $key = substr($name, strlen('toggles.'));

        Setting::set($key, (bool) $value);

        Flux::toast(variant: 'success', text: __(':feature :state.', [
            'feature' => Setting::FEATURES[$key]['label'],
            'state' => $value ? __('enabled') : __('disabled'),
        ]));
    }
}; ?>

    <div class="p-4">
        <flux:heading size="lg">{{ __('Site Settings') }}</flux:heading>
        <flux:text class="mb-6 mt-1">{{ __('Enable or disable platform-wide features. Changes take effect immediately.') }}</flux:text>

        <flux:card class="max-w-2xl divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach (Setting::FEATURES as $key => $meta)
                <div class="flex items-center justify-between gap-4 py-4 first:pt-0 last:pb-0">
                    <div>
                        <flux:heading size="sm">{{ $meta['label'] }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">{{ $meta['description'] }}</flux:text>
                    </div>

                    <flux:switch wire:model.live="toggles.{{ $key }}" />
                </div>
            @endforeach
        </flux:card>

        <flux:card class="mt-6 max-w-2xl">
            <flux:heading size="sm">{{ __('Onboarding setup fee') }}</flux:heading>
            <flux:text size="sm" class="mb-4 text-zinc-500">
                {{ __('A one-off charge a director settles before registering their school. Set it to 0 to let schools register without paying anything up front. Plans are billed separately, after the trial.') }}
            </flux:text>

            <form wire:submit="saveSetupFee" class="flex flex-wrap items-end gap-3">
                <flux:input type="number" step="0.01" min="0" wire:model="setupFee"
                    :label="__('Amount') . ' (' . \App\Models\Plan::CURRENCY . ')'" class="max-w-48" />

                <flux:button type="submit" variant="primary">
                    <span wire:loading.remove wire:target="saveSetupFee">{{ __('Save') }}</span>
                    <span wire:loading wire:target="saveSetupFee">{{ __('Saving…') }}</span>
                </flux:button>
            </form>
        </flux:card>
    </div>
