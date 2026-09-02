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
                    From 1-click bulk report card dispatch and subject teacher mark entry to biometric turnstiles, Daraja M-Pesa fee collection, and branded bulk SMS—Solforbs SMS gives directors, bursars, teachers, and parents one unified, modern workspace.
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
                <div class="mt-10 grid grid-cols-2 sm:grid-cols-3 gap-3 border-t border-zinc-200/80 pt-6 dark:border-zinc-800 text-xs text-zinc-600 dark:text-zinc-400 font-medium">
                    <div class="flex items-center gap-2">
                        <flux:icon icon="check-circle" class="size-4 text-emerald-500 shrink-0" />
                        <span>1-Click Report Card SMS</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon icon="check-circle" class="size-4 text-emerald-500 shrink-0" />
                        <span>Dedicated Multi-Role Portals</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon icon="check-circle" class="size-4 text-emerald-500 shrink-0" />
                        <span>Subject Teacher Marks Entry</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon icon="check-circle" class="size-4 text-emerald-500 shrink-0" />
                        <span>Lesson & Gate Biometrics</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon icon="check-circle" class="size-4 text-emerald-500 shrink-0" />
                        <span>Branded Custom SMS ID</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon icon="check-circle" class="size-4 text-emerald-500 shrink-0" />
                        <span>M-Pesa Auto-Reconciliation</span>
                    </div>
                </div>
            </div>

            <!-- Interactive 3D Device Showcase (Click to Swap Front / Back Layer) -->
            <div class="relative w-full max-w-xl mx-auto flex flex-col items-center" data-animate="right" style="--reveal-delay:120ms" x-data="{ focusedDevice: 'desktop' }">
                
                {{-- Interactive Switcher Pill --}}
                <div class="inline-flex items-center gap-1.5 p-1 rounded-full bg-zinc-200/70 dark:bg-zinc-800/80 border border-zinc-300/50 dark:border-zinc-700/60 mb-4 text-xs font-semibold shadow-xs">
                    <button type="button" @click="focusedDevice = 'desktop'"
                        :class="focusedDevice === 'desktop' ? 'bg-white dark:bg-zinc-900 text-[#0047FF] dark:text-[#00A3FF] shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900'"
                        class="px-3 py-1 rounded-full transition flex items-center gap-1.5 cursor-pointer">
                        <flux:icon icon="computer-desktop" class="size-3.5" />
                        <span>Desktop View</span>
                    </button>
                    <button type="button" @click="focusedDevice = 'mobile'"
                        :class="focusedDevice === 'mobile' ? 'bg-white dark:bg-zinc-900 text-[#0047FF] dark:text-[#00A3FF] shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900'"
                        class="px-3 py-1 rounded-full transition flex items-center gap-1.5 cursor-pointer">
                        <flux:icon icon="device-phone-mobile" class="size-3.5" />
                        <span>Mobile View</span>
                    </button>
                </div>

                {{-- Devices Stage Container --}}
                <div class="relative w-full aspect-[4/3]">
                    
                    <!-- Desktop / Laptop Device Frame -->
                    <div @click="focusedDevice = 'desktop'"
                        :class="focusedDevice === 'desktop' 
                            ? 'z-30 right-0 top-0 w-full sm:w-[94%] rotate-0 scale-100 shadow-2xl shadow-zinc-900/30 ring-2 ring-[#0047FF]/20' 
                            : 'z-10 right-0 top-3 w-[84%] sm:w-[86%] rotate-3 opacity-90 hover:opacity-100 hover:rotate-1 scale-95 shadow-xl shadow-zinc-900/10 cursor-pointer'"
                        class="absolute transition-all duration-500 ease-out rounded-2xl border border-zinc-200/90 bg-white p-2 dark:border-white/10 dark:bg-zinc-900 select-none">
                        
                        <div class="flex items-center justify-between px-3 py-2 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-900/80 rounded-t-xl">
                            <div class="flex items-center gap-1.5">
                                <span class="size-2.5 rounded-full bg-red-400"></span>
                                <span class="size-2.5 rounded-full bg-amber-400"></span>
                                <span class="size-2.5 rounded-full bg-emerald-400"></span>
                            </div>
                            <div class="flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-zinc-200/60 dark:bg-zinc-800 text-[10px] font-mono text-zinc-500">
                                <flux:icon icon="lock-closed" class="size-2.5 text-emerald-500" />
                                <span>sms.solforbs.com/dashboard</span>
                            </div>
                            <div class="size-3"></div>
                        </div>
                        <div class="w-full aspect-[16/10] rounded-b-xl overflow-hidden bg-zinc-100 dark:bg-zinc-950 relative group">
                            <img src="{{ asset('screenshots/dashboard-desktop.png') }}" alt="Solforbs Executive Dashboard" class="w-full h-full object-contain object-top transition-transform duration-700 group-hover:scale-[1.02] pointer-events-none" />
                        </div>
                    </div>

                    <!-- Smartphone Device Frame (Real Chassis & Speaker Notch) -->
                    <div @click="focusedDevice = 'mobile'"
                        :class="focusedDevice === 'mobile'
                            ? 'z-30 left-2 sm:left-4 bottom-0 w-[46%] sm:w-[42%] -rotate-2 hover:rotate-0 scale-100 shadow-2xl shadow-zinc-950/70 ring-2 ring-[#0047FF]/30 cursor-pointer'
                            : 'z-20 -left-2 bottom-0 w-[34%] sm:w-[32%] -rotate-6 opacity-90 hover:opacity-100 scale-90 shadow-xl shadow-zinc-950/50 cursor-pointer'"
                        class="absolute transition-all duration-500 ease-out rounded-[2.4rem] border-[6px] border-zinc-900 bg-zinc-900 shadow-2xl ring-1 ring-zinc-800 p-0 relative select-none">
                        
                        {{-- Top Speaker Notch / Dynamic Island --}}
                        <div class="w-14 h-3.5 rounded-full bg-zinc-900 absolute top-2 left-1/2 -translate-x-1/2 z-20 flex items-center justify-center">
                            <div class="size-1.5 rounded-full bg-zinc-700/80"></div>
                        </div>

                        {{-- Screen Bezel & Display Content (iPhone 16 Pro Max 19.5:9 Aspect Ratio) --}}
                        <div class="w-full aspect-[9/19.5] rounded-[2rem] overflow-hidden bg-zinc-900 relative">
                            <img src="{{ asset('screenshots/dashboard-mobile.png') }}" alt="Solforbs Mobile Dashboard" class="w-full h-full object-cover object-top select-none pointer-events-none" />
                        </div>

                        {{-- Bottom Home Indicator Bar --}}
                        <div class="w-16 h-1 rounded-full bg-zinc-600/50 absolute bottom-1.5 left-1/2 -translate-x-1/2 z-20"></div>
                    </div>

                </div>
            </div>
        </x-frontend.container>
    </section>

    {{-- Interactive Live Feature Showcase --}}
    <section id="live-preview" class="py-20 sm:py-28 bg-white dark:bg-zinc-900/40 border-b border-zinc-200/60 dark:border-zinc-800/80" x-data="{ activeTab: 'fees' }">
        <x-frontend.container>
            <div class="text-center max-w-3xl mx-auto mb-12" data-animate="fade">
                <x-frontend.badge icon="computer-desktop" class="text-indigo-600 dark:text-indigo-400">
                    Interactive Showcase
                </x-frontend.badge>
                <h2 class="mt-4 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    See how Solforbs SMS works <span class="bg-gradient-to-r from-[#00A3FF] to-[#0047FF] bg-clip-text text-transparent">in real life</span>
                </h2>
                <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
                    Click through the core modules below to explore the authentic interface engineered for Kenyan primary and secondary schools.
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

                <button type="button" @click="activeTab = 'reportcards'"
                    :class="activeTab === 'reportcards' ? 'bg-[#0047FF] text-white shadow-md' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                    class="px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                    <flux:icon icon="document-chart-bar" class="size-4" />
                    <span>Teacher Marks & Report Cards</span>
                </button>

                <button type="button" @click="activeTab = 'devices'"
                    :class="activeTab === 'devices' ? 'bg-[#0047FF] text-white shadow-md' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                    class="px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                    <flux:icon icon="device-phone-mobile" class="size-4" />
                    <span>Biometrics & Attendance</span>
                </button>

                <button type="button" @click="activeTab = 'sms'"
                    :class="activeTab === 'sms' ? 'bg-[#0047FF] text-white shadow-md' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-700'"
                    class="px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                    <flux:icon icon="chat-bubble-left-right" class="size-4" />
                    <span>Branded School SMS</span>
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
            </div>

            {{-- Feature Previews --}}
            <div class="rounded-3xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 p-4 sm:p-8 shadow-xl">
                
                {{-- 1. Fee Management Tab --}}
                <div x-show="activeTab === 'fees'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" x-data="{ feeView: 'treasury' }">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <flux:icon icon="banknotes" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Fee Management & M-Pesa Reconciliation</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Track term fee balances per student in real-time. Parents settle balances via M-Pesa Paybill, and accountants can instantly scan paper bank deposit slips using our AI Receipt OCR engine.
                        </p>
                        <ul class="space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 pt-2">
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>Executive Invoicing:</strong> Real-time collection meters and payment velocity</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>Per-Student Ledger:</strong> Instant search by Admission No with live 🟢 Paid badges</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>AI Receipt OCR:</strong> Auto-extracts deposit slips and matches student accounts</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>Payment Vouchers:</strong> Manage expenditures, supplier LPOs, and petty cash</li>
                        </ul>
                    </div>

                    {{-- Right Column: Interactive Sub-Toggle & Screenshot Card --}}
                    <div class="lg:col-span-8 flex flex-col space-y-3">
                        {{-- Mini View Switcher --}}
                        <div class="flex items-center justify-between flex-wrap gap-2 px-1">
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
                            <span class="text-[11px] text-zinc-500 font-mono hidden sm:inline" x-text="feeView === 'treasury' ? 'Revenue KPIs & Action Bar' : 'Multi-Student Payment Tracking'"></span>
                        </div>

                        {{-- Screenshot Container --}}
                        <div class="rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-xl bg-white dark:bg-zinc-900 relative">
                            {{-- View 1: Executive Treasury --}}
                            <div x-show="feeView === 'treasury'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100">
                                <img src="{{ asset('screenshots/fees.png') }}" alt="Solforbs Financial Treasury" class="w-full h-auto object-cover" />
                            </div>

                            {{-- View 2: Detailed Multi-Student Ledger --}}
                            <div x-show="feeView === 'ledger'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100" style="display: none;">
                                <img src="{{ asset('screenshots/fees-ledger.png') }}" alt="Solforbs Detailed Student Fee Ledger" class="w-full h-auto object-cover" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. Exams & Report Cards Tab --}}
                <div x-show="activeTab === 'reportcards'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" x-data="{ examView: 'ledger' }" style="display: none;">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400">
                            <flux:icon icon="document-chart-bar" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Subject Marks Entry & 1-Click Report Forms</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Subject teachers enter class marks once. The system calculates grading rubrics (CBC EE/ME/AE/BE & KCSE), rankings, mean scores, remarks, and dispatches report cards via SMS with 1 click.
                        </p>
                        <ul class="space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 pt-2">
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>Direct Teacher Score Entry:</strong> Rapid class marks entry per assigned subject</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>1-Click Bulk Report Card Dispatch:</strong> Send report forms straight to parent phones</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>CBC & 8-4-4 Ready:</strong> Auto-computes expectation levels, positions, and teacher comments</li>
                        </ul>
                    </div>

                    {{-- Right Column: Interactive Sub-Toggle & Display Card --}}
                    <div class="lg:col-span-8 flex flex-col space-y-3">
                        {{-- Sub-Toggle Toolbar --}}
                        <div class="flex items-center justify-between flex-wrap gap-2 px-1">
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
                            <span class="text-[11px] text-zinc-500 font-mono hidden sm:inline" x-text="examView === 'ledger' ? 'Recorded Scores & Class Rankings' : 'Printable CBC & KCSE Assessment Sheet'"></span>
                        </div>

                        {{-- Display Container --}}
                        <div class="rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-xl bg-white dark:bg-zinc-900 relative">
                            {{-- View 1: Exam Results & Marks Ledger --}}
                            <div x-show="examView === 'ledger'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100">
                                <img src="{{ asset('screenshots/exam-results.png') }}" alt="Solforbs Exam Results & Marks Ledger" class="w-full h-auto object-cover" />
                            </div>

                            {{-- View 2: Official Student Report Card --}}
                            <div x-show="examView === 'reportcard'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100" style="display: none;">
                                <img src="{{ asset('screenshots/reportcards.png') }}" alt="Solforbs Official Student Report Card" class="w-full h-auto object-cover" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. Biometrics & Devices Tab --}}
                <div x-show="activeTab === 'devices'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" x-data="{ deviceView: 'software' }" style="display: none;">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                            <flux:icon icon="device-phone-mobile" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Gate Biometrics & Subject Attendance</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Connect standard <strong class="text-zinc-900 dark:text-white font-semibold">ZKTeco K40 Pro</strong> biometric turnstiles for gate arrival check-ins and let teachers take 30-second subject roll calls from their phones.
                        </p>
                        <ul class="space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 pt-1">
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>Sub-Second Biometric Recognition:</strong> Fast optical fingerprint & RFID cards</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>Instant Arrival SMS:</strong> Alerts parent immediately when child passes school gate</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>Subject Period Roll Call:</strong> Track learner attendance lesson by lesson</li>
                        </ul>
                    </div>
                    
                    {{-- Right Column: Interactive Sub-Toggle & Display Card --}}
                    <div class="lg:col-span-8 flex flex-col space-y-3">
                        <div class="flex items-center justify-between flex-wrap gap-2 px-1">
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

                        <div class="rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-xl bg-white dark:bg-zinc-900 relative">
                            <div x-show="deviceView === 'software'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100">
                                <img src="{{ asset('screenshots/devices.png') }}" alt="Solforbs Biometric Hardware Gateway" class="w-full h-auto object-cover" />
                            </div>

                            <div x-show="deviceView === 'hardware'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.99]" x-transition:enter-end="opacity-100 scale-100" style="display: none;" class="p-6 sm:p-8 flex flex-col md:flex-row items-center justify-between gap-6 sm:gap-8 bg-gradient-to-br from-zinc-50 to-white dark:from-zinc-900 dark:to-zinc-950">
                                <div class="w-full md:w-1/2 flex justify-center">
                                    <div class="p-4 bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 shadow-lg">
                                        <img src="{{ asset('screenshots/zktecok40pro.png') }}" alt="ZKTeco K40 Pro Biometric Terminal" class="max-h-52 w-auto object-contain hover:scale-105 transition-transform duration-300 drop-shadow-md" />
                                    </div>
                                </div>
                                <div class="w-full md:w-1/2 space-y-3">
                                    <h4 class="text-xl font-bold text-zinc-900 dark:text-white">ZKTeco K40 Pro Terminal</h4>
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                                        Optical fingerprint recognition, RFID card reader, and offline memory buffer holding up to 100,000 logs during network drops.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4. Branded SMS & Group Messaging Tab --}}
                <div x-show="activeTab === 'sms'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" style="display: none;">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400">
                            <flux:icon icon="chat-bubble-left-right" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Branded School SMS Sender IDs</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Every message arrives under your school's official registered name (e.g. <span class="font-mono font-bold text-zinc-900 dark:text-white bg-zinc-200 dark:bg-zinc-800 px-1.5 py-0.5 rounded">GREEN VALLEY SCHOOL</span>).
                        </p>
                        <ul class="space-y-2.5 text-xs text-zinc-600 dark:text-zinc-400 pt-2">
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>Official Sender ID:</strong> Instantly recognizable brand identity on parent phones</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>Automated Fee Reminders:</strong> Direct balance notifications with payment links</li>
                            <li class="flex items-center gap-2"><flux:icon icon="check" class="size-4 text-emerald-500 shrink-0" /> <strong>1-Click Report Card SMS:</strong> Single-use PDF download links sent straight to parents</li>
                        </ul>
                    </div>
                    <div class="lg:col-span-8 flex justify-center py-4 bg-zinc-100 dark:bg-zinc-950/60 rounded-2xl border border-zinc-200/80 dark:border-zinc-800 shadow-inner">
                        <div class="max-w-xs sm:max-w-sm rounded-[2.5rem] border-[6px] border-zinc-800 dark:border-zinc-700 bg-black shadow-2xl overflow-hidden p-1">
                            <img src="{{ asset('screenshots/sms-preview.png') }}" alt="Real Parent SMS Notifications" class="w-full h-auto object-cover rounded-[2rem]" />
                        </div>
                    </div>
                </div>

                {{-- 5. Admissions & Students Tab --}}
                <div x-show="activeTab === 'students'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" style="display: none;">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                            <flux:icon icon="academic-cap" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Admissions & Student Profiles</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Maintain a single source of truth for every learner: admissions, medical notes, primary parent contacts, assigned streams, and academic trajectory.
                        </p>
                    </div>
                    <div class="lg:col-span-8 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-lg bg-white dark:bg-zinc-900">
                        <img src="{{ asset('screenshots/students.png') }}" alt="Solforbs Student Management" class="w-full h-auto object-cover" />
                    </div>
                </div>

                {{-- 6. Timetable Scheduler Tab --}}
                <div x-show="activeTab === 'timetable'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="grid gap-8 lg:grid-cols-12 lg:items-center" style="display: none;">
                    <div class="lg:col-span-4 space-y-4">
                        <div class="inline-flex p-3 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <flux:icon icon="calendar-days" class="size-6" />
                        </div>
                        <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">Conflict-Free Timetable Grid</h3>
                        <p class="text-zinc-600 dark:text-zinc-400 text-sm leading-relaxed">
                            Build weekly lesson schedules with zero teacher or room clashes. View personalized schedules on mobile phones.
                        </p>
                    </div>
                    <div class="lg:col-span-8 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-lg bg-white dark:bg-zinc-900">
                        <img src="{{ asset('screenshots/timetable.png') }}" alt="Solforbs Timetable Schedule" class="w-full h-auto object-cover" />
                    </div>
                </div>

            </div>
        </x-frontend.container>
    </section>

    {{-- Stakeholder Power Tools Bento Grid (Crisp & Distinct Benefits) --}}
    <section class="py-20 sm:py-28 bg-zinc-50 dark:bg-zinc-950">
        <x-frontend.container>
            <div class="text-center max-w-3xl mx-auto mb-16" data-animate="fade">
                <p class="text-sm font-semibold tracking-wide text-[#00A3FF] uppercase">Built for Real School Teams</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    Tailored Value for Every Stakeholder
                </h2>
                <p class="mt-4 text-base text-zinc-600 dark:text-zinc-400">
                    How Solforbs SMS transforms the daily workday of school administrators, bursars, teachers, and parents.
                </p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Director Card --}}
                <div class="p-6 rounded-3xl border border-zinc-200/80 bg-white hover:border-[#00A3FF] hover:shadow-lg transition dark:border-zinc-800 dark:bg-zinc-900" data-animate style="--reveal-delay:0ms">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-[#00A3FF]/10 text-[#00A3FF] mb-5">
                        <flux:icon icon="building-office-2" class="size-6" />
                    </div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">For Directors & Principals</h3>
                    <ul class="mt-3 space-y-2 text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Multi-campus branch switching</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Real-time fee collection meters</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Live gate turnstile clock-in feed</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Instant branded circular broadcast</span></li>
                    </ul>
                </div>

                {{-- Bursar Card --}}
                <div class="p-6 rounded-3xl border border-zinc-200/80 bg-white hover:border-[#00A3FF] hover:shadow-lg transition dark:border-zinc-800 dark:bg-zinc-900" data-animate style="--reveal-delay:80ms">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 mb-5">
                        <flux:icon icon="banknotes" class="size-6" />
                    </div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">For Accountants & Bursars</h3>
                    <ul class="mt-3 space-y-2 text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Zero manual M-Pesa entry</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>AI receipt scanner for bank slips</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Automated payment vouchers & LPOs</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>1-Click bulk SMS balance alerts</span></li>
                    </ul>
                </div>

                {{-- Teacher Card --}}
                <div class="p-6 rounded-3xl border border-zinc-200/80 bg-white hover:border-[#00A3FF] hover:shadow-lg transition dark:border-zinc-800 dark:bg-zinc-900" data-animate style="--reveal-delay:160ms">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-purple-500/10 text-purple-600 dark:text-purple-400 mb-5">
                        <flux:icon icon="academic-cap" class="size-6" />
                    </div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">For Subject & Class Teachers</h3>
                    <ul class="mt-3 space-y-2 text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Direct subject marks entry</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>1-Click CBC report card generation</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Rapid 30s lesson period roll-call</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Personal timetable on mobile</span></li>
                    </ul>
                </div>

                {{-- Parent Card --}}
                <div class="p-6 rounded-3xl border border-zinc-200/80 bg-white hover:border-[#00A3FF] hover:shadow-lg transition dark:border-zinc-800 dark:bg-zinc-900" data-animate style="--reveal-delay:240ms">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 mb-5">
                        <flux:icon icon="user-group" class="size-6" />
                    </div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">For Parents & Guardians</h3>
                    <ul class="mt-3 space-y-2 text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Instant gate clock-in text message</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>1-Click report card PDF download link</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>24/7 AI chat balance & grade lookup</span></li>
                        <li class="flex items-start gap-1.5"><flux:icon icon="check" class="size-3.5 text-emerald-500 shrink-0 mt-0.5" /> <span>Branded SMS from the school's name</span></li>
                    </ul>
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
