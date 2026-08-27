<x-layouts::app :title="$message->name">
    <div class="p-4">
        <div class="mb-4">
            <flux:button href="{{ route('messages.index') }}" icon="arrow-left" variant="ghost" wire:navigate>
                {{ __('Back to messages') }}
            </flux:button>
        </div>

        <flux:card class="max-w-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ $message->name }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                        {{ $message->email }}{{ $message->phone ? ' · '.$message->phone : '' }}
                    </flux:text>
                </div>
                <flux:badge color="indigo">
                    {{ config('contact.topics')[$message->topic] ?? $message->topic }}
                </flux:badge>
            </div>

            <flux:separator class="my-6" />

            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Message') }}</flux:text>
            <p class="mt-2 leading-relaxed whitespace-pre-line text-zinc-800 dark:text-zinc-200">
                {{ $message->message }}
            </p>

            <flux:separator class="my-6" />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Received') }} {{ $message->created_at->format('d M Y, H:i') }}
                    ({{ $message->created_at->diffForHumans() }})
                </flux:text>
                <flux:button href="mailto:{{ $message->email }}" icon="envelope" variant="primary">
                    {{ __('Reply by email') }}
                </flux:button>
            </div>
        </flux:card>
    </div>
</x-layouts::app>
