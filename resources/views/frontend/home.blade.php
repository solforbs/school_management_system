@php
    $chatbotCommands = config('chatbot.commands', []);
    $verifiedCommandCount = collect($chatbotCommands)->where('verified', true)->count();
@endphp

<x-layouts::frontend.app>
    {{-- Hero Section --}}
    <section class="relative overflow-hidden bg-zinc-50 dark:bg-zinc-950">
        <div class="pointer-events-none absolute inset-x-0 -top-40 -z-10 flex justify-center overflow-hidden blur-3xl">
            <div class="aspect-1155/678 w-[72rem] bg-gradient-to-tr from-[#00A3FF] to-[#0047FF] opacity-10 dark:opacity-20"
                style="clip-path: polygon(74% 44%, 100% 61%, 97% 26%, 85% 0%, 80% 2%, 72% 32%, 60% 62%, 32% 0%, 0% 5%, 12% 45%, 29% 70%, 22% 100%, 63% 90%)">
            </div>
        </div>

        <x-frontend.container class="grid gap-12 pt-16 pb-20 lg:grid-cols-2 lg:items-center lg:pt-24 lg:pb-28">
            <div data-animate="left">
                <x-frontend.badge icon="sparkles" class="text-[#0047FF]">
                    Next-Gen School Management Platform
                </x-frontend.badge>

                <h1 class="mt-6 text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl dark:text-white">
                    Run your entire school effortlessly,
                    <span class="bg-gradient-to-r from-[#00A3FF] to-[#0047FF] bg-clip-text text-transparent">without the paperwork.</span>
                </h1>

                <p class="mt-6 max-w-xl text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    From digital admissions and biometric gate attendance to automated KES fee collection and CBC/KCSE report cards—Solforbs gives directors, teachers, and parents one unified, modern workspace.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <flux:button :href="route('register')" class="bg-gradient-to-r from-[#00A3FF] to-[#0047FF] text-white border-none hover:opacity-90 shadow-md font-semibold px-6 py-2.5" wire:navigate>
                        Get Started Free
                    </flux:button>
                    <flux:button href="#live-preview" variant="outline" class="font-medium">
                        Explore Live Features
                    </flux:button>
                </div>

                {{-- Trust Badges --}}
                <div class="mt-10 flex flex-wrap items-center gap-6 border-t border-zinc-200/80 pt-6 dark:border-zinc-800 text-xs text-zinc-500 font-medium">
                    <div class="flex items-center gap-2">
                        <flux:icon icon="check-circle" class="size-4 text-emerald-500 shrink-0" />
                        <span>Biometric ZKTeco Hardware Sync</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon icon="check-circle" class="size-4 text-emerald-500 shrink-0" />
                        <span>Branded SMS Sender ID</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon icon="check-circle" class="size-4 text-emerald-500 shrink-0" />
                        <span>AI Receipt OCR Scanner</span>
                    </div>
                </div>
            </div>

            <!-- Tilted Overlapping Real Device Mockups -->
            <div class="relative w-full max-w-lg mx-auto aspect-[4/3]" data-animate="right" style="--reveal-delay:120ms">
                
                <!-- Back Device (Laptop / Desktop View) -->
                <div class="absolute right-0 top-2 w-[88%] transform rotate-3 hover:rotate-1 transition-transform duration-500 rounded-xl border border-zinc-200 bg-white p-1.5 shadow-2xl shadow-zinc-900/15 dark:border-white/10 dark:bg-zinc-900">
                    <div class="flex items-center gap-1.5 px-3 py-2 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 rounded-t-lg">
                        <span class="size-2.5 rounded-full bg-red-400"></span>
                        <span class="size-2.5 rounded-full bg-amber-400"></span>
                        <span class="size-2.5 rounded-full bg-emerald-400"></span>
                        <span class="ml-3 text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">Solforbs Executive Dashboard</span>
                    </div>
                    <div class="w-full aspect-video rounded-b overflow-hidden bg-zinc-100 dark:bg-zinc-800 relative group">
                        <img src="{{ asset('screenshots/dashboard-desktop.png') }}" alt="Solforbs Executive Dashboard" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" />
                    </div>
                </div>

                <!-- Front Device (Mobile Smartphone View) -->
                <div class="absolute -left-2 bottom-0 w-[34%] transform -rotate-6 hover:-rotate-2 transition-transform duration-500 rounded-[1.8rem] border-[4px] border-zinc-900 bg-zinc-900 shadow-2xl shadow-zinc-900/50 dark:border-zinc-700">
                    <div class="flex justify-center pt-2 pb-1.5 bg-zinc-900 rounded-t-[1.5rem]">
                        <div class="w-12 h-1.5 rounded-full bg-zinc-700 dark:bg-zinc-800"></div>
                    </div>
                    <div class="w-full aspect-[9/16] rounded-b-[1.5rem] overflow-hidden bg-zinc-950 relative">
                        <img src="{{ asset('screenshots/dashboard-mobile.png') }}" alt="Solforbs Mobile Experience" class="w-full h-full object-cover object-top" />
                    </div>
                </div>

            </div>
        </x-frontend.container>
    </section>

    {{-- Interactive Live Feature Showcase --}}
    <section id="live-preview" class="py-20 sm:py-28 bg-white dark:bg-zinc-900/40 border-y border-zinc-200/60 dark:border-zinc-800/80" x-data="{ activeTab: 'fees' }">
        <x-frontend.container>
            <div class="text-center max-w-3xl mx-auto mb-12">
                <x-frontend.badge icon="computer-desktop" class="text-indigo-600 dark:text-indigo-400">
                    Interactive Showcase
                </x-frontend.badge>
                <h2 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    See how Solforbs works <span class="bg-gradient-to-r from-[#00A3FF] to-[#0047FF] bg-clip-text text-transparent">in real life</span>
                </h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
                    Click through the core features below to see the authentic interface designed for speed, clarity, and ease of use.
                </p>
            </div>

            {{-- Feature Tabs Navigation --}}
            <div class="flex flex-wrap justify-center items-center gap-2 sm:gap-3 mb-10">
                <button type="button" @click="activeTab = 'fees'"
                    :class="activeTab === 'fees' ? 'bg-[#0047FF] text-white shadow-md' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                    class="px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                    <flux:icon icon="banknotes" class="size-4" />
                    <span>Fees & M-Pesa</span>
                </button>

                <button type="button" @click="activeTab = 'devices'"
                    :class="activeTab === 'devices' ? 'bg-[#0047FF] text-white shadow-md' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                    class="px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                    <flux:icon icon="device-phone-mobile" class="size-4" />
                    <span>Biometrics & Gate Sync</span>
                </button>

                <button type="button" @click="activeTab = 'reportcards'"
                    :class="activeTab === 'reportcards' ? 'bg-[#0047FF] text-white shadow-md' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                    class="px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                    <flux:icon icon="document-chart-bar" class="size-4" />
                    <span>Exams & Report Cards</span>
                </button>

                <button type="button" @click="activeTab = 'students'"
                    :class="activeTab === 'students' ? 'bg-[#0047FF] text-white shadow-md' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                    class="px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                    <flux:icon icon="academic-cap" class="size-4" />
                    <span>Admissions & Students</span>
                </button>

                <button type="button" @click="activeTab = 'timetable'"
                    :class="activeTab === 'timetable' ? 'bg-[#0047FF] text-white shadow-md' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                    class="px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                    <flux:icon icon="calendar-days" class="size-4" />
                    <span>Timetable Scheduler</span>
                </button>

                <button type="button" @click="activeTab = 'sms'"
                    :class="activeTab === 'sms' ? 'bg-[#0047FF] text-white shadow-md' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                    class="px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                    <flux:icon icon="chat-bubble-left-right" class="size-4" />
                    <span>Branded School SMS</span>
                </button>
            </div>

            {{-- Feature Previews --}}
            <div class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 p-4 sm:p-8 shadow-xl">
                
                {{-- 1. Fee Management Tab --}}
                <div x-show="activeTab === 'fees'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <flux:icon icon="banknotes" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Fee Management & M-Pesa Reconciliation</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Track term fee balances per student in real-time. Parents can settle balances via M-Pesa Paybill, and accountants can instantly scan paper bank deposit slips using our integrated AI Receipt OCR engine.
                        </p>
                        <ul class="space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 pt-2">
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Automated student ledger balance calculations</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> AI OCR scans M-Pesa & Bank slips in seconds</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> One-click SMS fee payment acknowledgements</li>
                        </ul>
                    </div>
                    <div class="lg:col-span-8 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-lg bg-white dark:bg-zinc-900">
                        <img src="{{ asset('screenshots/fees.png') }}" alt="Solforbs Fee Management" class="w-full h-auto object-cover" />
                    </div>
                </div>

                {{-- 2. Biometrics & Devices Tab --}}
                <div x-show="activeTab === 'devices'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" style="display: none;">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                            <flux:icon icon="device-phone-mobile" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Biometric Hardware & Gate Clocks</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Connect standard ZKTeco ADMS biometric turnstiles and gate terminals directly. Every student tap-in is logged instantly, synchronised with cloud records, and dispatches automated arrival texts to guardians.
                        </p>
                        <ul class="space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 pt-2">
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Real-time live status monitor (Online / Offline)</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> One-click remote terminal clock sync</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Offline tethering so gates function during internet drops</li>
                        </ul>
                    </div>
                    <div class="lg:col-span-8 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-lg bg-white dark:bg-zinc-900">
                        <img src="{{ asset('screenshots/devices.png') }}" alt="Solforbs Biometric Hardware" class="w-full h-auto object-cover" />
                    </div>
                </div>

                {{-- 3. Exams & Report Cards Tab --}}
                <div x-show="activeTab === 'reportcards'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" style="display: none;">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400">
                            <flux:icon icon="document-chart-bar" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">CBC, KCSE & Custom Grading Engine</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Subject teachers enter class scores in bulk with one submission. The system computes grading bands, mean percentages, teacher remarks, and generates printable PDF report cards with single-use secure download links.
                        </p>
                        <ul class="space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 pt-2">
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Supports CBC Expectation Bands (Exceeding, Meeting, Approaching, Below)</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Automated term-over-term performance comparison</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Bulk deliver report cards via SMS and Email to parents</li>
                        </ul>
                    </div>
                    <div class="lg:col-span-8 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-lg bg-white dark:bg-zinc-900">
                        <img src="{{ asset('screenshots/reportcards.png') }}" alt="Solforbs Report Cards" class="w-full h-auto object-cover" />
                    </div>
                </div>

                {{-- 4. Admissions & Students Tab --}}
                <div x-show="activeTab === 'students'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" style="display: none;">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                            <flux:icon icon="academic-cap" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Admissions & Comprehensive Student Profiles</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Maintain a single source of truth for every learner. Store passport photos, medical history, primary parent phone contacts, assigned streams, and academic trajectory all in one searchable dashboard.
                        </p>
                        <ul class="space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 pt-2">
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Instant search by Admission No, Student No, or Name</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Direct parent account linkage for unified messaging</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Stream promotions and class transfers in one click</li>
                        </ul>
                    </div>
                    <div class="lg:col-span-8 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-lg bg-white dark:bg-zinc-900">
                        <img src="{{ asset('screenshots/students.png') }}" alt="Solforbs Student Management" class="w-full h-auto object-cover" />
                    </div>
                </div>

                {{-- 5. Timetable Scheduler Tab --}}
                <div x-show="activeTab === 'timetable'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" style="display: none;">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <flux:icon icon="calendar-days" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Conflict-Free Timetable Grid</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Build weekly lesson schedules with zero overlapping teacher or room clashes. Teachers can view their personalized weekly schedule on their phones, while principals see the master school-wide matrix.
                        </p>
                        <ul class="space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 pt-2">
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Automated double-booking collision prevention</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Daily period attendance register locking</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> CSV schedule bulk import & PDF export</li>
                        </ul>
                    </div>
                    <div class="lg:col-span-8 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-lg bg-white dark:bg-zinc-900">
                        <img src="{{ asset('screenshots/timetable.png') }}" alt="Solforbs Timetable Schedule" class="w-full h-auto object-cover" />
                    </div>
                </div>

                {{-- 6. Branded SMS & Group Messaging Tab --}}
                <div x-show="activeTab === 'sms'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" style="display: none;">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400">
                            <flux:icon icon="chat-bubble-left-right" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Branded School SMS Sender IDs</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Give your institution instant authority. Every message sent to parents arrives from your school's own name (e.g. <span class="font-mono font-bold text-zinc-900 dark:text-white bg-zinc-200 dark:bg-zinc-800 px-1.5 py-0.5 rounded">IMAARA_ACAD</span>) rather than a random telephone number.
                        </p>
                        <ul class="space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 pt-2">
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Official custom alphanumeric Sender ID allocation</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> Bulk broadcast group alerts (emergency notices, holidays)</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> 30-second atomic deduplication to prevent double-charging</li>
                        </ul>
                    </div>
                    <div class="lg:col-span-8 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-lg bg-zinc-950 p-6 flex flex-col justify-center min-h-[320px]">
                        {{-- Mock SMS Preview Card --}}
                        <div class="max-w-md mx-auto w-full space-y-3 font-sans">
                            <div class="flex items-center justify-between text-xs text-zinc-400 pb-2 border-b border-zinc-800">
                                <span>Sender: <strong class="text-white">SOLFORBS_SMS</strong></span>
                                <span>Just now</span>
                            </div>
                            <div class="p-3.5 rounded-2xl bg-zinc-800/80 border border-zinc-700 text-zinc-100 text-xs sm:text-sm space-y-1.5 shadow-md">
                                <p class="font-semibold text-emerald-400">Gate Arrival Alert 🔔</p>
                                <p>Dear Parent, <strong>Brian Kimani (Adm: 4082)</strong> has safely arrived and clocked in at Gate 1 at 07:35 AM today.</p>
                            </div>
                            <div class="p-3.5 rounded-2xl bg-zinc-800/80 border border-zinc-700 text-zinc-100 text-xs sm:text-sm space-y-1.5 shadow-md">
                                <p class="font-semibold text-blue-400">Term 2 Fee Receipt 💳</p>
                                <p>Received KES 15,000 via M-Pesa (Ref: QHB79X2LK4) for Brian Kimani. Outstanding Term Balance: KES 0. Thank you.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </x-frontend.container>
    </section>

    {{-- Problem / Solution Feature Grid --}}
    <section id="features" class="py-20 sm:py-28">
        <x-frontend.container>
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    Everything you need, <span class="text-[#00A3FF]">nothing complicated.</span>
                </h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
                    We've eliminated technical friction. Just clear, powerful tools built for Kenyan primary and secondary schools.
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
                <!-- Feature 1 -->
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-zinc-900 transition hover:shadow-md">
                    <div class="flex size-12 items-center justify-center rounded-lg bg-[#00A3FF]/10 text-[#00A3FF]">
                        <flux:icon icon="academic-cap" class="size-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">Easy Admissions</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Stop dealing with lost paper forms. Enroll children online and approve registrations with one click.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-zinc-900 transition hover:shadow-md">
                    <div class="flex size-12 items-center justify-center rounded-lg bg-[#0047FF]/10 text-[#0047FF]">
                        <flux:icon icon="banknotes" class="size-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">Automated Fees & OCR</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Automatically track who has paid and scan paper bank slips with AI OCR to match student ledgers instantly.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-zinc-900 transition hover:shadow-md">
                    <div class="flex size-12 items-center justify-center rounded-lg bg-[#00A3FF]/10 text-[#00A3FF]">
                        <flux:icon icon="device-phone-mobile" class="size-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">Biometric Attendance</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Connect ZKTeco hardware at gates for automatic clock-in and instant arrival SMS dispatched to parents.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-zinc-900 transition hover:shadow-md">
                    <div class="flex size-12 items-center justify-center rounded-lg bg-[#0047FF]/10 text-[#0047FF]">
                        <flux:icon icon="document-chart-bar" class="size-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">Exams & CBC Reports</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Enter marks once and generate official report cards automatically. No more manual Excel formulas.
                    </p>
                </div>
            </div>
        </x-frontend.container>
    </section>

    {{-- Final CTA --}}
    <section class="pb-20 sm:pb-28">
        <x-frontend.container data-animate="scale">
            <div class="rounded-3xl bg-gradient-to-br from-[#00A3FF] to-[#0047FF] px-6 py-16 sm:p-20 text-center text-white shadow-xl">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                    Ready to bring your school into the future?
                </h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-blue-100">
                    Create your Solforbs account and set up your school's workspace today. No credit card required to start.
                </p>
                <div class="mt-8 flex justify-center gap-4">
                    <flux:button :href="route('register')" class="bg-white text-[#0047FF] hover:bg-zinc-50 border-none font-bold px-8 py-3" wire:navigate>
                        Get Started Free
                    </flux:button>
                </div>
            </div>
        </x-frontend.container>
    </section>
</x-layouts::frontend.app>
