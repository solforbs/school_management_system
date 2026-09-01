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

            <div class="relative" data-animate="right" x-data="{ examView: 'ledger' }">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                    <div class="inline-flex items-center gap-1.5 p-1 rounded-full bg-zinc-200/70 dark:bg-zinc-800 border border-zinc-300/50 dark:border-zinc-700/60 text-xs font-semibold shadow-xs">
                        <button type="button" @click="examView = 'ledger'"
                            :class="examView === 'ledger' ? 'bg-white dark:bg-zinc-900 text-purple-700 dark:text-purple-400 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900'"
                            class="px-3 py-1 rounded-full transition flex items-center gap-1.5 cursor-pointer">
                            <flux:icon icon="table-cells" class="size-3.5" />
                            <span>Exam Marks Ledger</span>
                        </button>
                        <button type="button" @click="examView = 'reportcard'"
                            :class="examView === 'reportcard' ? 'bg-white dark:bg-zinc-900 text-purple-700 dark:text-purple-400 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900'"
                            class="px-3 py-1 rounded-full transition flex items-center gap-1.5 cursor-pointer">
                            <flux:icon icon="document-text" class="size-3.5" />
                            <span>Official Student Report Card</span>
                        </button>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xl overflow-hidden">
                    <div x-show="examView === 'ledger'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100">
                        <img src="{{ asset('screenshots/exam-results.png') }}" alt="Exam Results & Marks Ledger" class="w-full h-auto object-cover" />
                    </div>
                    <div x-show="examView === 'reportcard'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100" style="display: none;">
                        <img src="{{ asset('screenshots/reportcards.png') }}" alt="Academic Grading & Official Report Cards" class="w-full h-auto object-cover" />
                    </div>
                </div>
            </div>
        </x-frontend.container>
    </section>

    {{-- Spotlight: Branded SMS --}}
    <section class="bg-zinc-50 py-20 sm:py-28 dark:bg-white/[0.02]">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div class="order-2 relative lg:order-1 flex justify-center" data-animate="left">
                <div class="max-w-xs sm:max-w-sm rounded-[2.5rem] border-[6px] border-zinc-800 dark:border-zinc-700 bg-black shadow-2xl overflow-hidden p-1">
                    <img src="{{ asset('screenshots/sms-preview.png') }}" alt="Real Parent SMS Notifications from Green Valley School" class="w-full h-auto object-cover rounded-[2rem]" />
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

    {{-- Spotlight: Digital Admissions & Comprehensive Student 360° Profiles --}}
    <section class="py-20 sm:py-28">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div data-animate="left">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">Learner Lifecycle</p>
                <h2
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl dark:text-white">
                    Digital Admissions & Comprehensive Student 360° Profiles
                </h2>
                <p class="mt-4 text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    Maintain a single source of truth for every learner from enrollment to graduation. Search instantly by Admission Number, Student ID, or Name with guardian phone linkage.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <x-frontend.check-item>Instant search by Admission No, Student ID, or Name</x-frontend.check-item>
                    <x-frontend.check-item>Direct parent account linkage for unified messaging and fee tracking</x-frontend.check-item>
                    <x-frontend.check-item>Stream promotions, class transfers, and medical records in one click</x-frontend.check-item>
                </ul>
            </div>

            <div class="relative" data-animate="right">
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xl overflow-hidden">
                    <img src="{{ asset('screenshots/students.png') }}" alt="Student Admissions & Profile Directory" class="w-full h-auto object-cover" />
                </div>
            </div>
        </x-frontend.container>
    </section>

    {{-- Spotlight: Conflict-Free Timetabling & Lesson Attendance --}}
    <section class="bg-zinc-50 py-20 sm:py-28 dark:bg-white/[0.02]">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div class="order-2 relative lg:order-1" data-animate="left">
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xl overflow-hidden">
                    <img src="{{ asset('screenshots/timetable.png') }}" alt="Conflict-Free Timetable Matrix" class="w-full h-auto object-cover" />
                </div>
            </div>

            <div class="order-1 lg:order-2" data-animate="right">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">Academic Operations</p>
                <h2
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl dark:text-white">
                    Conflict-Free Timetable & Daily Period Registers
                </h2>
                <p class="mt-4 text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    Build weekly lesson schedules with zero overlapping teacher or classroom collisions. Teachers access their personalized rosters right on their phones, while principals monitor master school-wide coverage.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <x-frontend.check-item>Automated double-booking collision prevention across all teachers and rooms</x-frontend.check-item>
                    <x-frontend.check-item>Period-by-period attendance register locking for lesson accountability</x-frontend.check-item>
                    <x-frontend.check-item>1-click CSV schedule import & printable PDF master grids</x-frontend.check-item>
                </ul>
            </div>
        </x-frontend.container>
    </section>

    {{-- Frequently Asked Questions (FAQ Accordion) --}}
    <section class="py-20 sm:py-28">
        <x-frontend.container>
            <div class="text-center max-w-3xl mx-auto mb-14" data-animate="fade">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">Got Questions?</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    Frequently Asked Questions
                </h2>
                <p class="mt-4 text-base text-zinc-600 dark:text-zinc-400">
                    Clear answers to the most common questions from Kenyan school directors and administrators.
                </p>
            </div>

            <div class="max-w-3xl mx-auto space-y-4" x-data="{ activeFaq: null }">
                {{-- FAQ 1 --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 1 ? null : 1" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-indigo-600 transition cursor-pointer">
                        <span class="text-base sm:text-lg">How does biometric gate attendance work when our school Wi-Fi is down?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 1 ? 'rotate-180 text-indigo-600' : ''" />
                    </button>
                    <div x-show="activeFaq === 1" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        All our certified ZKTeco K40 Pro terminals feature dedicated onboard memory that stores up to 100,000 attendance records offline. Students can continue scanning normally during power cuts or network drops. The moment internet connectivity restores, all queued check-ins sync automatically to the cloud.
                    </div>
                </div>

                {{-- FAQ 2 --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 2 ? null : 2" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-indigo-600 transition cursor-pointer">
                        <span class="text-base sm:text-lg">Does the grading engine support both CBC expectation levels and KCSE 8-4-4?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 2 ? 'rotate-180 text-indigo-600' : ''" />
                    </button>
                    <div x-show="activeFaq === 2" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        Yes! Solforbs provides native support for CBC competency-based assessment bands (Exceeding Expectations, Meeting Expectations, Approaching Expectations, Below Expectations) as well as traditional KCSE 8-4-4 letter grades (A through E). Report cards automatically calculate cohort means, subject remarks, and comparative performance graphs.
                    </div>
                </div>

                {{-- FAQ 3 --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 3 ? null : 3" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-indigo-600 transition cursor-pointer">
                        <span class="text-base sm:text-lg">Can we connect our own school M-Pesa Paybill or Till Number?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 3 ? 'rotate-180 text-indigo-600' : ''" />
                    </button>
                    <div x-show="activeFaq === 3" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        Yes. You can link your school's official Safaricom Daraja M-Pesa Paybill or Buy Goods Till. When parents make fee payments, the system automatically verifies transaction codes, reconciles individual student balances, and sends an instant SMS payment receipt.
                    </div>
                </div>

                {{-- FAQ 4 --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 4 ? null : 4" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-indigo-600 transition cursor-pointer">
                        <span class="text-base sm:text-lg">How are parent SMS notifications branded?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 4 ? 'rotate-180 text-indigo-600' : ''" />
                    </button>
                    <div x-show="activeFaq === 4" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        Every SMS notification (gate arrival alerts, fee payment receipts, report card links, emergency school notices) is delivered under your school's own registered alphanumeric Sender ID (such as <strong class="text-zinc-900 dark:text-white">GREENVALLEY</strong>), establishing trust and official authority with parents.
                    </div>
                </div>

                {{-- FAQ 5 --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/60 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 5 ? null : 5" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-indigo-600 transition cursor-pointer">
                        <span class="text-base sm:text-lg">Is our school data isolated and protected across multiple campuses?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 5 ? 'rotate-180 text-indigo-600' : ''" />
                    </button>
                    <div x-show="activeFaq === 5" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        Absolutely. Each institution runs in an isolated tenant scope. Multi-campus school groups can switch between branches seamlessly, while staff, teachers, and accountants only access records permitted by their role and campus assignment.
                    </div>
                </div>
            </div>
        </x-frontend.container>
    </section>

    <x-frontend.cta-band />
</x-layouts::frontend.app>
