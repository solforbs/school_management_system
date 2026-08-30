@props(['id', 'title', 'description' => null])

<div id="{{ $id }}" class="stepper-section scroll-mt-8 bg-white dark:bg-zinc-900 shadow-[0_2px_8px_-2px_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 transition-all duration-300"
     :class="activeSection === '{{ $id }}' ? 'ring-1 ring-blue-500/20 shadow-blue-500/5' : ''">
     
    <div class="mb-6">
        <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">{{ $title }}</flux:heading>
        @if($description)
            <flux:text class="text-zinc-500 text-sm mt-1">{{ $description }}</flux:text>
        @endif
    </div>

    {{ $slot }}
</div>
