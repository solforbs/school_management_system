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
                <flux:table>
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
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $contactMessage->name }}
                                    </div>
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $contactMessage->email }}
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
                                    <form action="{{ route('messages.destroy', $contactMessage) }}" method="POST"
                                        class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" icon="trash" variant="primary" color="red"
                                            onclick="return confirm('{{ __('Are you sure you want to delete this message?') }}')">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </form>
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
