@props([
    'heading' => 'Ready to bring your school onto one platform?',
    'description' => 'Set up your workspace in minutes, or talk to our team about bringing your institution online.',
])

<div {{ $attributes->class(['relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-[#00A3FF] via-blue-600 to-[#0077CC] px-6 py-14 text-center shadow-2xl shadow-[#00A3FF]/25 border border-white/20 sm:px-16']) }}>
    {{-- Decorative ambient glow elements --}}
    <div class="pointer-events-none absolute -top-24 -left-24 -z-10 size-80 rounded-full bg-white/15 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 -right-24 -z-10 size-80 rounded-full bg-cyan-300/25 blur-3xl"></div>

    <h2 class="mx-auto max-w-2xl text-2xl font-bold tracking-tight text-balance text-white sm:text-3xl lg:text-4xl">
        {{ $heading }}
    </h2>

    @if ($description)
        <p class="mx-auto mt-3 max-w-xl text-sm sm:text-base text-pretty text-blue-50/95 leading-relaxed font-medium">
            {{ $description }}
        </p>
    @endif

    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            <a href="{{ route('register') }}" wire:navigate class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-bold text-sm text-[#0077CC] bg-white hover:bg-blue-50 shadow-lg hover:shadow-xl hover:scale-105 transition-all cursor-pointer">
                <span>Get started</span>
                <flux:icon icon="arrow-right" class="size-4 text-[#0077CC]" />
            </a>
            <a href="{{ route('contact') }}" wire:navigate class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold text-sm text-white bg-white/15 hover:bg-white/25 border border-white/30 backdrop-blur-xs transition cursor-pointer">
                <span>Talk to us</span>
            </a>
        @endif
    </div>
</div>
