@php
    use App\Services\ConfigurationManager;
@endphp

<div class="space-y-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="sm">{{ __('Environment Configuration') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ __('Edit application settings and API credentials. Changes are saved to the .env file.') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:button wire:click="resetConfiguration" variant="ghost">
                {{ __('Reset') }}
            </flux:button>
            <flux:button wire:click="save" variant="primary">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>

    {{-- Category Navigation --}}
    <div class="mb-6 flex flex-wrap gap-2 border-b border-zinc-200 dark:border-zinc-700">
        @foreach ($this->categories() as $category => $vars)
            <button type="button" wire:click="$set('activeCategory', '{{ $category }}')"
                class="px-4 py-2.5 text-sm font-semibold transition-colors focus:outline-none"
                :class="'{{ $category }}' === $wire.activeCategory
                    ? 'border-b-2 border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400'
                    : 'border-b-2 border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'">
                {{ __(ucfirst(str_replace('_', ' ', $category))) }}
            </button>
        @endforeach
    </div>

    {{-- Configuration Form --}}
    <form wire:submit="save" class="space-y-4">
        @forelse ($this->currentCategory() as $key => $config)
            <flux:card>
                <div class="space-y-3">
                    <div>
                        <label for="{{ $key }}" class="block text-sm font-semibold text-zinc-900 dark:text-white">
                            {{ $config['label'] }}
                        </label>
                        <p class="mt-1 text-xs text-zinc-500">{{ $config['description'] ?? '' }}</p>
                    </div>

                    @if ($config['type'] === 'select')
                        <select id="{{ $key }}" wire:model="configuration.{{ $activeCategory }}.{{ $key }}"
                            class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 shadow-sm transition focus:border-blue-500 focus:ring focus:ring-blue-200 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:focus:ring-blue-900">
                            <option value="">-- {{ __('Select') }} --</option>
                            @foreach ($config['options'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    @elseif ($config['type'] === 'password')
                        <div class="relative">
                            <input type="{{ $showSensitiveValues ? 'text' : 'password' }}"
                                id="{{ $key }}"
                                wire:model="configuration.{{ $activeCategory }}.{{ $key }}"
                                placeholder="{{ $secrets[$key] && $originalValues[$key] ? __('Configured - leave blank to keep') : $config['label'] }}"
                                class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 shadow-sm transition focus:border-blue-500 focus:ring focus:ring-blue-200 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:focus:ring-blue-900" />

                            @if ($secrets[$key])
                                <button type="button" wire:click="$toggle('showSensitiveValues')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                                    @if ($showSensitiveValues)
                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-4.803m5.596-3.114a1 1 0 10-.894-1.789m7.894 11.817a1 1 0 11.894-1.789M19.303 16.415a1 1 0 00-1.414-1.414m2.121-7.071a1 1 0 00-1.414-1.414m-5.656 5.656a1 1 0 11-1.414-1.414m7.071-7.071a1 1 0 00-1.414-1.414" />
                                        </svg>
                                    @else
                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    @endif
                                </button>
                            @endif
                        </div>
                    @else
                        <input type="{{ $config['type'] }}"
                            id="{{ $key }}"
                            wire:model.blur="configuration.{{ $activeCategory }}.{{ $key }}"
                            placeholder="{{ $config['label'] }}"
                            class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 shadow-sm transition focus:border-blue-500 focus:ring focus:ring-blue-200 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:focus:ring-blue-900" />
                    @endif

                    @error("configuration.{{ $activeCategory }}.{{ $key }}")
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </flux:card>
        @empty
            <flux:card>
                <flux:text class="text-zinc-500">
                    {{ __('No configuration available for this category.') }}
                </flux:text>
            </flux:card>
        @endforelse
    </form>

    {{-- Info Box --}}
    <flux:card class="bg-blue-50 dark:bg-blue-950">
        <div class="flex gap-3">
            <svg class="size-5 flex-shrink-0 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                    clip-rule="evenodd" />
            </svg>
            <div class="text-sm text-blue-800 dark:text-blue-200">
                <p class="font-semibold">{{ __('Sensitive data is encrypted') }}</p>
                <p class="mt-1">
                    {{ __('Fields marked with a password icon are encrypted at rest in your .env file. Passwords are never sent back to the browser.') }}
                </p>
            </div>
        </div>
    </flux:card>
</div>
