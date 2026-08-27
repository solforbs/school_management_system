<x-layouts::frontend.app title="Services">
    <x-frontend.page-hero eyebrow="Services"
        description="Every module below is part of the same platform — one login, one student record, one source of truth for your whole school.">
        Everything your school office needs, in one place
    </x-frontend.page-hero>

    <section class="py-4 sm:py-8">
        <x-frontend.container>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $services = [
                        [
                            'icon' => 'academic-cap',
                            'title' => 'Admissions & Students',
                            'body' =>
                                'Enroll students, keep guardian and address details in one place, and give every learner a single academic profile.',
                        ],
                        [
                            'icon' => 'finger-print',
                            'title' => 'Biometric Attendance',
                            'body' =>
                                'Connect ZKTeco devices for tap-in attendance that syncs automatically, with manual registers as a fallback.',
                        ],
                        [
                            'icon' => 'banknotes',
                            'title' => 'Fees & M-Pesa Payments',
                            'body' =>
                                'Bill fees per term, track balances per student, and accept M-Pesa payments directly into the platform.',
                        ],
                        [
                            'icon' => 'clipboard-document-check',
                            'title' => 'Examinations & Report Cards',
                            'body' =>
                                'Record marks, compute grades, and generate report cards teachers, parents and admins can all trust.',
                        ],
                        [
                            'icon' => 'calendar-days',
                            'title' => 'Timetable & Classes',
                            'body' =>
                                'Build class timetables, assign subjects and teachers, and keep every lesson slot conflict-free.',
                        ],
                        [
                            'icon' => 'chat-bubble-left-right',
                            'title' => 'Parent Communication',
                            'body' =>
                                'A secure self-service chat assistant for results, attendance and fees, verified with a one-time code.',
                        ],
                        [
                            'icon' => 'shield-check',
                            'title' => 'Roles & Permissions',
                            'body' =>
                                'Fine-grained, role-based access so administrators, teachers and staff only see what they need to.',
                        ],
                        [
                            'icon' => 'building-office-2',
                            'title' => 'Multi-School Support',
                            'body' => 'Each institution gets its own private, isolated workspace on the same platform.',
                        ],
                    ];
                @endphp

                @foreach ($services as $service)
                    <x-frontend.feature-card :icon="$service['icon']" :title="$service['title']" data-animate
                        style="--reveal-delay:{{ $loop->index * 70 }}ms">
                        {{ $service['body'] }}
                    </x-frontend.feature-card>
                @endforeach
            </div>
        </x-frontend.container>
    </section>

    {{-- Spotlight: Attendance --}}
    <section class="py-20 sm:py-28">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div data-animate="left">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">Attendance
                </p>
                <h2
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl dark:text-white">
                    Attendance without the guesswork
                </h2>
                <p class="mt-4 text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    Connect a ZKTeco biometric device at the gate or classroom door, and every tap-in flows
                    straight into the platform — no register to reconcile by hand at the end of the day.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <x-frontend.check-item>Biometric devices sync attendance automatically</x-frontend.check-item>
                    <x-frontend.check-item>Manual entry still available where a device isn't
                        installed</x-frontend.check-item>
                    <x-frontend.check-item>Class-level and school-wide attendance visibility for
                        staff</x-frontend.check-item>
                    <x-frontend.check-item>Parents can check their child's attendance through the chat
                        assistant</x-frontend.check-item>
                </ul>
            </div>

            <div class="relative" data-animate="right">
                <div
                    class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-white/10 dark:bg-zinc-900">
                    <div class="flex items-center gap-2 border-b border-zinc-100 pb-4 dark:border-white/10">
                        <span
                            class="flex size-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                            <flux:icon icon="finger-print" class="size-5" />
                        </span>
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">Device sync log</span>
                    </div>
                    <ul class="mt-4 space-y-3">
                        @foreach (['Gate reader · 07:42', 'Block A reader · 07:51', 'Block B reader · 08:03'] as $entry)
                            <li
                                class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2.5 text-sm dark:bg-white/5">
                                <span class="text-zinc-600 dark:text-zinc-300">{{ $entry }}</span>
                                <flux:icon icon="check-circle" class="size-4 text-emerald-500" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </x-frontend.container>
    </section>

    {{-- Spotlight: Fees --}}
    <section class="bg-zinc-50 py-20 sm:py-28 dark:bg-white/[0.02]">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div class="order-2 relative lg:order-1" data-animate="left">
                <div
                    class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-white/10 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-zinc-100 pb-4 dark:border-white/10">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">Term fee balance</span>
                        <span
                            class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">M-Pesa</span>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="h-2.5 w-full rounded-full bg-zinc-100 dark:bg-white/5"></div>
                        <div class="h-2.5 w-4/5 rounded-full bg-indigo-500"></div>
                    </div>
                    <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">80% of this term's fees collected so far
                    </p>
                </div>
            </div>

            <div class="order-1 lg:order-2" data-animate="right">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">Fees</p>
                <h2
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl dark:text-white">
                    Get paid the way parents already pay
                </h2>
                <p class="mt-4 text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    Bill fees per term, track every student's balance, and let parents pay by M-Pesa directly —
                    so the finance office stops chasing paper receipts.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <x-frontend.check-item>Per-term fee structures, tracked per student</x-frontend.check-item>
                    <x-frontend.check-item>M-Pesa payments recorded automatically</x-frontend.check-item>
                    <x-frontend.check-item>Parents check balances any time through the chat
                        assistant</x-frontend.check-item>
                </ul>
            </div>
        </x-frontend.container>
    </section>

    {{-- Spotlight: Assistant --}}
    <section class="py-20 sm:py-28">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div data-animate="left">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">Parent
                    communication</p>
                <h2
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl dark:text-white">
                    A parent assistant that actually answers
                </h2>
                <p class="mt-4 text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    Every school on the platform gets the same chat assistant you can try right now — the one
                    in the corner of this page. Parents ask about results, attendance, fees or their child's
                    profile, and get a verified answer in seconds, no phone call needed.
                </p>
                {{-- No wire:navigate: Livewire carries a #fragment through to
                the new URL but never scrolls to it, so this would land at the
                top of the homepage instead of at the assistant section. --}}
                <flux:button :href="route('home').
                '#assistant'" variant="outline" class="mt-6">
                    See it explained on the homepage
                </flux:button>
            </div>

            <div class="relative" data-animate="right">
                <div
                    class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-white/10 dark:bg-zinc-900">
                    <div class="flex items-center gap-2 border-b border-zinc-100 pb-3 dark:border-white/10">
                        <span
                            class="flex size-8 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                            <flux:icon icon="chat-bubble-left-right" class="size-4" />
                        </span>
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ config('app.name') }}
                            Assistant</span>
                    </div>
                    <div class="mt-3 flex justify-start">
                        <div
                            class="max-w-[85%] rounded-2xl bg-zinc-100 px-3 py-2 text-sm text-zinc-800 dark:bg-white/10 dark:text-zinc-100">
                            Sure — what's your child's admission number?
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-zinc-400 dark:text-zinc-500">
                        ↘ Try the real thing — click the chat bubble in the corner of your screen.
                    </p>
                </div>
            </div>
        </x-frontend.container>
    </section>

    <section class="pb-20 sm:pb-28">
        <x-frontend.container>
            <x-frontend.cta-band heading="Ready to see the whole platform?"
                description="Compare plans, or create your school's workspace today.">
                <flux:button :href="route('plans')" variant="outline" wire:navigate>
                    View plans
                </flux:button>
                <flux:button :href="route('register')" variant="primary" wire:navigate>
                    Get started
                </flux:button>
            </x-frontend.cta-band>
        </x-frontend.container>
    </section>
</x-layouts::frontend.app>
