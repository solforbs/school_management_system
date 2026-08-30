@php
    $navLinks = [
        'home' => 'Home',
        'services' => 'Services',
        'plans' => 'Pricing',
        'about' => 'About Us',
        'contact' => 'Contact',
    ];
@endphp

<header x-data="{ mobileOpen: false, scrolled: false }" x-init="scrolled = window.scrollY > 8" x-on:scroll.window="scrolled = window.scrollY > 8"
    x-on:keydown.escape.window="mobileOpen = false"
    class="sticky top-0 z-50 border-b border-transparent bg-white/60 backdrop-blur-md transition-all duration-300 dark:bg-zinc-950/60"
    :class="scrolled ? 'border-zinc-200/70 bg-white/85 shadow-sm dark:border-white/10 dark:bg-zinc-950/85' : ''">
    <x-frontend.container>
        <nav class="flex h-16 items-center justify-between">
            <a href="{{ route('home') }}" wire:navigate
                class="flex items-center gap-2 font-semibold text-zinc-900 transition hover:opacity-80 dark:text-white">
                <img src="{{ asset('logos/solforbs-logo.png') }}" alt="Solforbs" class="h-16 w-auto object-contain">
            </a>

            <div class="hidden lg:flex lg:items-center lg:gap-1">
                <a href="{{ route('home') }}" wire:navigate @class([
                    'relative rounded-lg px-3 py-2 text-sm font-medium transition',
                    'text-zinc-900 dark:text-white' => request()->routeIs('home'),
                    'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => !request()->routeIs(
                        'home'),
                ])>
                    Home
                    @if (request()->routeIs('home'))
                        <span
                            class="absolute inset-x-3 -bottom-[1px] h-0.5 rounded-full bg-accent dark:bg-accent"></span>
                    @endif
                </a>

                <!-- Features Mega Dropdown -->
                <div x-data="{ open: false }" class="relative" @mouseenter="open = true" @mouseleave="open = false">
                    <button
                        class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-zinc-600 transition hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                        Features
                        <flux:icon icon="chevron-down" class="size-3" />
                    </button>

                    <div x-show="open" x-transition x-cloak
                        class="absolute left-1/2 -translate-x-1/2 top-full mt-1 w-[500px] rounded-2xl border border-zinc-200 bg-white p-4 shadow-xl dark:border-white/10 dark:bg-zinc-900">
                        <div class="grid grid-cols-2 gap-2">
                            <!-- Column 1 -->
                            <a href="#"
                                class="flex items-start gap-3 rounded-xl p-3 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#00A3FF]/10 text-[#00A3FF]">
                                    <flux:icon icon="academic-cap" class="size-5" />
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">Admissions</div>
                                    <div class="text-xs text-zinc-500">Online student enrollment</div>
                                </div>
                            </a>
                            <a href="#"
                                class="flex items-start gap-3 rounded-xl p-3 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#0047FF]/10 text-[#0047FF]">
                                    <flux:icon icon="banknotes" class="size-5" />
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">Fee Management
                                    </div>
                                    <div class="text-xs text-zinc-500">Automated tracking & SMS</div>
                                </div>
                            </a>
                            <a href="#"
                                class="flex items-start gap-3 rounded-xl p-3 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#00A3FF]/10 text-[#00A3FF]">
                                    <flux:icon icon="calendar-days" class="size-5" />
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">Attendance</div>
                                    <div class="text-xs text-zinc-500">Fast daily roll calls</div>
                                </div>
                            </a>
                            <a href="#"
                                class="flex items-start gap-3 rounded-xl p-3 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#0047FF]/10 text-[#0047FF]">
                                    <flux:icon icon="document-chart-bar" class="size-5" />
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">Exams & Reports
                                    </div>
                                    <div class="text-xs text-zinc-500">Auto-calculated grades</div>
                                </div>
                            </a>

                            <!-- Column 2 -->
                            <a href="#"
                                class="flex items-start gap-3 rounded-xl p-3 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#00A3FF]/10 text-[#00A3FF]">
                                    <flux:icon icon="clock" class="size-5" />
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">Timetables</div>
                                    <div class="text-xs text-zinc-500">Drag & drop scheduling</div>
                                </div>
                            </a>
                            <a href="#"
                                class="flex items-start gap-3 rounded-xl p-3 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#0047FF]/10 text-[#0047FF]">
                                    <flux:icon icon="users" class="size-5" />
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">Parent Portal</div>
                                    <div class="text-xs text-zinc-500">Live chat assistant</div>
                                </div>
                            </a>
                            <a href="#"
                                class="flex items-start gap-3 rounded-xl p-3 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#00A3FF]/10 text-[#00A3FF]">
                                    <flux:icon icon="book-open" class="size-5" />
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">Curriculum</div>
                                    <div class="text-xs text-zinc-500">Lesson planning system</div>
                                </div>
                            </a>
                            <a href="#"
                                class="flex items-start gap-3 rounded-xl p-3 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#0047FF]/10 text-[#0047FF]">
                                    <flux:icon icon="identification" class="size-5" />
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-zinc-900 dark:text-white">Staff & HR</div>
                                    <div class="text-xs text-zinc-500">Roles and permissions</div>
                                </div>
                            </a>
                        </div>
                        <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-white/5">
                            <a href="{{ route('services') }}" wire:navigate
                                class="flex items-center justify-center gap-2 text-sm font-medium text-[#0047FF] hover:text-[#00A3FF]">
                                View all platform features
                                <flux:icon icon="arrow-right" class="size-4" />
                            </a>
                        </div>
                    </div>
                </div>

                @foreach (['services' => 'Services', 'plans' => 'Pricing', 'about' => 'About Us', 'contact' => 'Contact'] as $route => $label)
                    <a href="{{ route($route) }}" wire:navigate @class([
                        'relative rounded-lg px-3 py-2 text-sm font-medium transition',
                        'text-zinc-900 dark:text-white' => request()->routeIs($route),
                        'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => !request()->routeIs(
                            $route),
                    ])>
                        {{ $label }}
                        @if (request()->routeIs($route))
                            <span
                                class="absolute inset-x-3 -bottom-[1px] h-0.5 rounded-full bg-accent dark:bg-accent"></span>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="hidden lg:flex lg:items-center lg:gap-3">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary" wire:navigate>
                        Dashboard
                    </flux:button>
                @else
                    <flux:button :href="route('login')" variant="ghost" wire:navigate>
                        Log in
                    </flux:button>
                    <flux:button :href="route('register')" class="bg-[#0047FF] text-white hover:bg-[#00A3FF] border-none"
                        wire:navigate>
                        Get started
                    </flux:button>
                @endauth
            </div>

            <button type="button" x-on:click="mobileOpen = ! mobileOpen"
                class="flex size-10 items-center justify-center rounded-lg text-zinc-600 hover:bg-zinc-100 lg:hidden dark:text-zinc-300 dark:hover:bg-white/10"
                :aria-expanded="mobileOpen" aria-label="Toggle navigation menu">
                <flux:icon x-show="! mobileOpen" icon="bars-3" class="size-6" />
                <flux:icon x-show="mobileOpen" x-cloak icon="x-mark" class="size-6" />
            </button>
        </nav>
    </x-frontend.container>

    <div x-show="mobileOpen" x-cloak x-transition.opacity.duration.150ms
        class="border-t border-zinc-200 bg-white lg:hidden dark:border-white/10 dark:bg-zinc-950">
        <x-frontend.container class="flex flex-col gap-1 py-4">
            @foreach ($navLinks as $route => $label)
                <a href="{{ route($route) }}" wire:navigate x-on:click="mobileOpen = false"
                    @class([
                        'rounded-lg px-3 py-2.5 text-sm font-medium',
                        'bg-zinc-100 text-zinc-900 dark:bg-white/10 dark:text-white' => request()->routeIs(
                            $route),
                        'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5' => !request()->routeIs(
                            $route),
                    ])>
                    {{ $label }}
                </a>
            @endforeach

            <div class="mt-3 flex flex-col gap-2 border-t border-zinc-200 pt-3 dark:border-white/10">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary" class="w-full" wire:navigate>
                        Dashboard
                    </flux:button>
                @else
                    <flux:button :href="route('login')" variant="outline" class="w-full" wire:navigate>
                        Log in
                    </flux:button>
                    <flux:button :href="route('register')" variant="primary" class="w-full" wire:navigate>
                        Get started
                    </flux:button>
                @endauth
            </div>
        </x-frontend.container>
    </div>
</header>
