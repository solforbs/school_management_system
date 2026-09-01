<li {{ $attributes->class(['flex items-start gap-2.5']) }}>
    <flux:icon icon="check-circle" class="mt-0.5 size-4 shrink-0 text-[#00A3FF] dark:text-[#00A3FF]" />
    <span class="text-zinc-700 dark:text-zinc-300 text-xs sm:text-sm leading-snug">{{ $slot }}</span>
</li>
