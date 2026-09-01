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
                                'Enroll students digitally, maintain guardian and address records, and give every learner a single comprehensive profile.',
                        ],
                        [
                            'icon' => 'device-phone-mobile',
                            'title' => 'Biometric Attendance',
                            'body' =>
                                'Connect ZKTeco gate terminals for tap-in attendance that syncs automatically with instant parent SMS alerts.',
                        ],
                        [
                            'icon' => 'banknotes',
                            'title' => 'Fees & AI Receipt OCR',
                            'body' =>
                                'Bill fees per term, accept M-Pesa payments directly, and scan paper bank slips in seconds with integrated AI OCR.',
                        ],
                        [
                            'icon' => 'clipboard-document-check',
                            'title' => 'Examinations & Report Cards',
                            'body' =>
                                'Record marks, compute CBC & KCSE grading bands, and generate official printable PDF report cards with QR codes.',
                        ],
                        [
                            'icon' => 'calendar-days',
                            'title' => 'Timetable & Classes',
                            'body' =>
                                'Build conflict-free weekly timetables, assign teachers to streams, and track period lesson attendance effortlessly.',
                        ],
                        [
                            'icon' => 'chat-bubble-left-right',
                            'title' => 'Branded School SMS',
                            'body' =>
                                'Send broadcast group messages and automated attendance alerts to parents from your school\'s own custom Sender ID.',
                        ],
                        [
                            'icon' => 'shield-check',
                            'title' => 'Roles & Permissions',
                            'body' =>
                                'Fine-grained access control so directors, accountants, teachers, and staff only access what they need.',
                        ],
                        [
                            'icon' => 'building-office-2',
                            'title' => 'Multi-Campus Support',
                            'body' => 'Each institution gets its own private, isolated workspace with custom branding and logos.',
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

    {{-- Spotlight: Attendance & Biometrics --}}
    <section class="py-20 sm:py-28">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div data-animate="left">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">Biometric Attendance & Hardware
                </p>
                <h2
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl dark:text-white">
                    Attendance without the guesswork
                </h2>
                <p class="mt-4 text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    Connect market-standard <strong class="text-zinc-900 dark:text-white font-semibold">ZKTeco K40 Pro</strong> biometric turnstiles and gate devices directly. Fast fingerprint and RFID card verification eliminates buddy punching, while onboard memory caches logs during network drops.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <x-frontend.check-item><strong>ZKTeco K40 Pro & ADMS Ready:</strong> Fast sub-second fingerprint recognition & RFID card support</x-frontend.check-item>
                    <x-frontend.check-item><strong>Anti-Buddy Punching:</strong> Tamper-proof attendance tied to unique biometric templates</x-frontend.check-item>
                    <x-frontend.check-item><strong>Instant Parent SMS:</strong> Real-time arrival and departure alerts dispatched with custom school Sender ID</x-frontend.check-item>
                    <x-frontend.check-item><strong>Offline Tethering:</strong> Gates function continuously during internet outages, syncing automatically on reconnect</x-frontend.check-item>
                </ul>
            </div>

            <div class="relative" data-animate="right" x-data="{ deviceView: 'software' }">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                    <div class="inline-flex items-center gap-1.5 p-1 rounded-full bg-zinc-200/70 dark:bg-zinc-800 border border-zinc-300/50 dark:border-zinc-700/60 text-xs font-semibold shadow-xs">
                        <button type="button" @click="deviceView = 'software'"
                            :class="deviceView === 'software' ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-400 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900'"
                            class="px-3 py-1 rounded-full transition flex items-center gap-1.5 cursor-pointer">
                            <flux:icon icon="cpu-chip" class="size-3.5" />
                            <span>Live Cloud Gateways</span>
                        </button>
                        <button type="button" @click="deviceView = 'hardware'"
                            :class="deviceView === 'hardware' ? 'bg-white dark:bg-zinc-900 text-indigo-700 dark:text-indigo-400 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900'"
                            class="px-3 py-1 rounded-full transition flex items-center gap-1.5 cursor-pointer">
                            <flux:icon icon="device-phone-mobile" class="size-3.5" />
                            <span>ZKTeco K40 Pro Terminal</span>
                        </button>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xl overflow-hidden">
                    <div x-show="deviceView === 'software'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100">
                        <img src="{{ asset('screenshots/devices.png') }}" alt="Biometric Hardware Management" class="w-full h-auto object-cover" />
                    </div>
                    <div x-show="deviceView === 'hardware'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100" style="display: none;" class="p-6 sm:p-8 flex flex-col sm:flex-row items-center justify-between gap-6 bg-gradient-to-br from-zinc-50 to-white dark:from-zinc-900 dark:to-zinc-950">
                        <div class="w-full sm:w-1/2 flex justify-center">
                            <div class="p-3 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 shadow-md">
                                <img src="{{ asset('screenshots/zktecok40pro.png') }}" alt="ZKTeco K40 Pro Hardware Terminal" class="max-h-44 w-auto object-contain hover:scale-105 transition-transform duration-300 drop-shadow-md" />
                            </div>
                        </div>
                        <div class="w-full sm:w-1/2 space-y-2.5">
                            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/60">
                                <span class="size-1.5 rounded-full bg-indigo-500"></span>
                                <span>Hardware Ready</span>
                            </div>
                            <h4 class="text-base font-bold text-zinc-900 dark:text-white">ZKTeco K40 Pro Terminal</h4>
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                                Sub-second optical fingerprint sensor and RFID card reader. Logs check-ins to local memory during power or network outages.
                            </p>
                            <div class="grid grid-cols-2 gap-1.5 font-mono text-[10px] pt-0.5">
                                <div class="p-1.5 rounded bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200/60 dark:border-zinc-700">
                                    <span class="text-zinc-400 block text-[8px] uppercase">Speed</span>
                                    <strong class="text-zinc-900 dark:text-white">&lt; 0.5s Scan</strong>
                                </div>
                                <div class="p-1.5 rounded bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200/60 dark:border-zinc-700">
                                    <span class="text-zinc-400 block text-[8px] uppercase">Protocol</span>
                                    <strong class="text-zinc-900 dark:text-white">ADMS Cloud</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-frontend.container>
    </section>

    {{-- Spotlight: Fees & AI OCR --}}
    <section class="bg-zinc-50 py-20 sm:py-28 dark:bg-white/[0.02]">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div class="order-2 relative lg:order-1" data-animate="left" x-data="{ feeView: 'treasury' }">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                    <div class="inline-flex items-center gap-1.5 p-1 rounded-full bg-zinc-200/70 dark:bg-zinc-800 border border-zinc-300/50 dark:border-zinc-700/60 text-xs font-semibold shadow-xs">
                        <button type="button" @click="feeView = 'treasury'"
                            :class="feeView === 'treasury' ? 'bg-white dark:bg-zinc-900 text-emerald-700 dark:text-emerald-400 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900'"
                            class="px-3 py-1 rounded-full transition flex items-center gap-1.5 cursor-pointer">
                            <flux:icon icon="banknotes" class="size-3.5" />
                            <span>Executive Treasury</span>
                        </button>
                        <button type="button" @click="feeView = 'ledger'"
                            :class="feeView === 'ledger' ? 'bg-white dark:bg-zinc-900 text-emerald-700 dark:text-emerald-400 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900'"
                            class="px-3 py-1 rounded-full transition flex items-center gap-1.5 cursor-pointer">
                            <flux:icon icon="table-cells" class="size-3.5" />
                            <span>Detailed Student Ledger</span>
                        </button>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xl overflow-hidden">
                    <div x-show="feeView === 'treasury'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100">
                        <img src="{{ asset('screenshots/fees.png') }}" alt="Fee Management & AI OCR" class="w-full h-auto object-cover" />
                    </div>
                    <div x-show="feeView === 'ledger'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100" style="display: none;">
                        <img src="{{ asset('screenshots/fees-ledger.png') }}" alt="Detailed Student Fee Ledger" class="w-full h-auto object-cover" />
                    </div>
                </div>
            </div>

            <div class="order-1 lg:order-2" data-animate="right">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">Fee Management & M-Pesa</p>
                <h2
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl dark:text-white">
                    Get paid the way parents already pay
                </h2>
                <p class="mt-4 text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    Bill fees per term, track every student's balance, accept M-Pesa payments directly, and scan paper bank deposit slips with our AI OCR scanner.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <x-frontend.check-item>Per-term fee structures, tracked per student</x-frontend.check-item>
                    <x-frontend.check-item>Instant AI text extraction for bank deposit slips and M-Pesa codes</x-frontend.check-item>
                    <x-frontend.check-item>Automated payment receipt SMS sent straight to parents</x-frontend.check-item>
                    <x-frontend.check-item>Comprehensive fee defaulter reports with bulk reminder blasts</x-frontend.check-item>
                </ul>
            </div>
        </x-frontend.container>
    </section>

    {{-- Spotlight: Examinations & Report Cards --}}
    <section class="py-20 sm:py-28">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div data-animate="left">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">Academic Grading</p>
                <h2
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl dark:text-white">
                    Official CBC & KCSE Report Cards in One Click
                </h2>
                <p class="mt-4 text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    Enter subject marks once. Solforbs automatically calculates mean scores, assigns expectation bands, formats teacher remarks, and generates tamper-evident PDF report cards ready for distribution.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <x-frontend.check-item>Full support for CBC expectation levels (EE, ME, AE, BE) and 8-4-4 letter grades</x-frontend.check-item>
                    <x-frontend.check-item>Tamper-proof PDF generation with QR verification</x-frontend.check-item>
                    <x-frontend.check-item>Single-use secure download links delivered via SMS and Email</x-frontend.check-item>
                </ul>
            </div>

            <div class="relative" data-animate="right">
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xl overflow-hidden">
                    <img src="{{ asset('screenshots/reportcards.png') }}" alt="Academic Grading & Report Cards" class="w-full h-auto object-cover" />
                </div>
            </div>
        </x-frontend.container>
    </section>

    {{-- Spotlight: Branded SMS --}}
    <section class="bg-zinc-50 py-20 sm:py-28 dark:bg-white/[0.02]">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div class="order-2 relative lg:order-1" data-animate="left">
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-950 p-8 shadow-xl">
                    <div class="max-w-sm mx-auto space-y-3 font-sans">
                        <div class="flex items-center justify-between text-xs text-zinc-400 pb-2 border-b border-zinc-800">
                            <span>Sender ID: <strong class="text-white">YOUR_SCHOOL_NAME</strong></span>
                            <span>Delivered</span>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-zinc-800/90 border border-zinc-700 text-zinc-100 text-xs sm:text-sm space-y-1.5 shadow-md">
                            <p class="font-semibold text-emerald-400">Attendance Alert 🔔</p>
                            <p>Dear Parent, Faith Muthoni (Adm: 4082) has clocked in safely at Gate 1 at 07:32 AM.</p>
                        </div>
                        <div class="p-3.5 rounded-2xl bg-zinc-800/90 border border-zinc-700 text-zinc-100 text-xs sm:text-sm space-y-1.5 shadow-md">
                            <p class="font-semibold text-blue-400">School Notice 📢</p>
                            <p>Reminder: Annual General Meeting (AGM) will be held this Friday at 10:00 AM in the Main Hall.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-1 lg:order-2" data-animate="right">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">Communication</p>
                <h2
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl dark:text-white">
                    Branded SMS from your school's own name
                </h2>
                <p class="mt-4 text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    Stand out with an official custom alphanumeric Sender ID. Broadcast emergency announcements, term opening dates, fee reminders, and event notices to all parents in seconds.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <x-frontend.check-item>Dedicated custom school Sender ID for verified authority</x-frontend.check-item>
                    <x-frontend.check-item>Group broadcast messaging filtered by class or entire school</x-frontend.check-item>
                    <x-frontend.check-item>High-speed gateway with 30-second atomic deduplication</x-frontend.check-item>
                </ul>
            </div>
        </x-frontend.container>
    </section>

    <x-frontend.cta-band />
</x-layouts::frontend.app>
