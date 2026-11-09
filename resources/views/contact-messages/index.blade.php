<x-layouts::app :title="__('Messages')">
    <div class="p-4">
        <div class="mb-4 flex flex-row items-center justify-between">
            <flux:heading size="lg">{{ __('Messages') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('Submissions from the public contact form') }}
            </flux:text>
        </div>
        @if (session('success'))
            <flux:toast>
                <flux:heading>{{ __('Success') }}</flux:heading>
                <flux:text>{{ session('success') }}</flux:text>
            </flux:toast>
        @endif
        <flux:card>
            @if ($messages->isEmpty())
                <div class="py-12 text-center">
                    <flux:icon icon="inbox" class="mx-auto size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:text class="mt-3 text-zinc-500 dark:text-zinc-400">
                        {{ __('No messages yet.') }}
                    </flux:text>
                </div>
            @else
                <flux:table class="p-2">
                    <flux:table.columns>
                        <x-sortable-column column="name">{{ __('From') }}</x-sortable-column>
                        <x-sortable-column column="topic">{{ __('Topic') }}</x-sortable-column>
                        <flux:table.column>{{ __('Message') }}</flux:table.column>
                        <x-sortable-column column="created_at">{{ __('Received') }}</x-sortable-column>
                        <flux:table.column>{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($messages as $contactMessage)
                            <flux:table.row>
                                <flux:table.cell>
                                    <div class="font-medium text-zinc-900 dark:text-white"><span translate="no">{{ $contactMessage->name }}</span>
                                    </div>
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400"><span translate="no">{{ $contactMessage->email }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="indigo">
                                        {{ config('contact.topics')[$contactMessage->topic] ?? $contactMessage->topic }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-xs truncate text-zinc-600 dark:text-zinc-400">
                                    {{ str($contactMessage->message)->limit(70) }}
                                </flux:table.cell>
                                <flux:table.cell>{{ $contactMessage->created_at->diffForHumans() }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button href="{{ route('messages.show', $contactMessage) }}" icon="eye"
                                        variant="primary" color="emerald" wire:navigate>
                                        {{ __('View') }}
                                    </flux:button>
                                    <x-confirm-form
                                        :action="route('messages.destroy', $contactMessage)"
                                        :title="__('Delete this message?')"
                                        :message="__('This permanently removes the contact message.')"
                                        :confirm="__('Delete')"
                                        :label="__('Delete')" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        @if ($messages->hasPages())
            <div class="mt-4">
                {{ $messages->links() }}
            </div>
        @endif
    </div>
</x-layouts::app>
