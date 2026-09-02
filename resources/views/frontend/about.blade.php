<x-layouts::frontend.app title="About Us">
    {{-- Hero Section --}}
    <x-frontend.page-hero eyebrow="About Solforbs SMS"
        description="Solforbs SMS (School Management System) is an all-in-one cloud platform engineered by Solforbs. We build modern software infrastructure that eliminates paperwork, automates fee collection, and unifies school operations.">
        The School Management System engineered by Solforbs
    </x-frontend.page-hero>

    {{-- System Capabilities & Reliability Strip --}}
    <section class="border-y border-zinc-200/80 bg-white py-10 dark:border-zinc-800 dark:bg-zinc-900/50">
        <x-frontend.container>
            <div class="grid grid-cols-2 gap-8 lg:grid-cols-4">
                <div class="text-center sm:text-left">
                    <p class="text-3xl font-extrabold text-[#00A3FF] sm:text-4xl">99.9%</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">Cloud Uptime & Auto-Sync</p>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Continuous daily automated cloud backups</p>
                </div>
                <div class="text-center sm:text-left">
                    <p class="text-3xl font-extrabold text-[#00A3FF] sm:text-4xl">100%</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">Kenyan CBC & 8-4-4 Ready</p>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">KJSEA & KCSE grading rubrics pre-loaded</p>
                </div>
                <div class="text-center sm:text-left">
                    <p class="text-3xl font-extrabold text-[#00A3FF] sm:text-4xl">Multi-Campus</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">Multi-Branch Architecture</p>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">Switch between campuses with one director account</p>
                </div>
                <div class="text-center sm:text-left">
                    <p class="text-3xl font-extrabold text-[#00A3FF] sm:text-4xl">0-Server</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">100% Cloud-Based Setup</p>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">No costly on-premise hardware or IT technicians needed</p>
                </div>
            </div>
        </x-frontend.container>
    </section>

    {{-- The Problem We Solve --}}
    <section class="py-16 sm:py-24">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div data-animate="left">
                <p class="text-sm font-semibold tracking-wide text-[#00A3FF] uppercase">The Company & The Product</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    One unified School Management System engineered by Solforbs
                </h2>
                <div class="mt-6 space-y-4 text-base leading-relaxed text-zinc-600 dark:text-zinc-400">
                    <p>
                        Most schools in Kenya don't lack dedication—they lack connected software. Attendance lives in paper exercise books, fee balances live in disparate spreadsheets on someone's laptop, exam marks require endless manual typing, and communication relies on manual SMS blasts.
                    </p>
                    <p>
                        <strong>Solforbs</strong> engineered <strong>Solforbs SMS</strong> (School Management System) to solve this: bringing digital admissions, biometric turnstiles, Daraja M-Pesa fee collection, CBC grading, and parent portals behind a single, ultra-secure cloud login.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4" data-animate="right">
                <div class="space-y-4">
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-[#00A3FF]/10 text-[#00A3FF]">
                            <flux:icon icon="finger-print" class="size-5" />
                        </div>
                        <h3 class="mt-3 text-sm font-bold text-zinc-900 dark:text-white">Live Gate Biometrics</h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">Instant SMS alerts sent to parents when learners tap turnstiles.</p>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-[#00A3FF]/10 text-[#00A3FF]">
                            <flux:icon icon="banknotes" class="size-5" />
                        </div>
                        <h3 class="mt-3 text-sm font-bold text-zinc-900 dark:text-white">Lipa na M-Pesa Auto-Match</h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">Direct Paybill integration that matches student accounts automatically.</p>
                    </div>
                </div>
                <div class="mt-8 space-y-4">
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-[#00A3FF]/10 text-[#00A3FF]">
                            <flux:icon icon="chat-bubble-left-right" class="size-5" />
                        </div>
                        <h3 class="mt-3 text-sm font-bold text-zinc-900 dark:text-white">Parent Chatbot Portal</h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">Parents check results, balances, and attendance 24/7 without calling.</p>
                    </div>
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-[#00A3FF]/10 text-[#00A3FF]">
                            <flux:icon icon="clipboard-document-check" class="size-5" />
                        </div>
                        <h3 class="mt-3 text-sm font-bold text-zinc-900 dark:text-white">Instant CBC Report Cards</h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">1-Click PDF generation with automatic rankings and teacher comments.</p>
                    </div>
                </div>
            </div>
        </x-frontend.container>
    </section>

    {{-- WHY CHOOSE SOLFORBS SMS? --}}
    <section class="py-16 sm:py-24 border-t border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900/40">
        <x-frontend.container>
            <div class="text-center max-w-3xl mx-auto mb-16" data-animate="fade">
                <p class="text-sm font-semibold tracking-wide text-[#00A3FF] uppercase">The Modern Advantage</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    Why Choose Solforbs SMS?
                </h2>
                <p class="mt-4 text-base text-zinc-600 dark:text-zinc-400">
                    Engineered to empower school directors, principals, and bursars with total control, seamless fee collection, and effortless academic administration.
                </p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Card 1: Intuitive Interface --}}
                <div class="p-6 rounded-3xl border border-zinc-200/80 bg-zinc-50/50 hover:bg-white hover:border-[#00A3FF] hover:shadow-lg transition dark:border-zinc-800 dark:bg-zinc-900/60 dark:hover:bg-zinc-900" data-animate style="--reveal-delay:0ms">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-[#00A3FF]/10 text-[#00A3FF] mb-5">
                        <flux:icon icon="computer-desktop" class="size-6" />
                    </div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Intuitive & User-Friendly</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Easy to set up and navigate. No advanced technical skills required. Your entire staff can master the interface within minutes.
                    </p>
                </div>

                {{-- Card 2: 24/7 Expert Support & On-Site Training --}}
                <div class="p-6 rounded-3xl border border-zinc-200/80 bg-zinc-50/50 hover:bg-white hover:border-[#00A3FF] hover:shadow-lg transition dark:border-zinc-800 dark:bg-zinc-900/60 dark:hover:bg-zinc-900" data-animate style="--reveal-delay:80ms">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 mb-5">
                        <flux:icon icon="user-group" class="size-6" />
                    </div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">On-Site Training & 24/7 Support</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Dedicated hands-on onboarding for bursars, accounts clerks, and teachers, backed by round-the-clock technical assistance in Nairobi.
                    </p>
                </div>

                {{-- Card 3: Financial Management & Cashflow Summaries --}}
                <div class="p-6 rounded-3xl border border-zinc-200/80 bg-zinc-50/50 hover:bg-white hover:border-[#00A3FF] hover:shadow-lg transition dark:border-zinc-800 dark:bg-zinc-900/60 dark:hover:bg-zinc-900" data-animate style="--reveal-delay:160ms">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 mb-5">
                        <flux:icon icon="chart-bar" class="size-6" />
                    </div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Smart Cashflow Summaries</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        View real-time termly fees collected versus expected targets, manage payment vouchers, track LPOs/LSOs, cashbooks, and trial balances.
                    </p>
                </div>

                {{-- Card 4: Pledges Tracking & Bulk Messaging --}}
                <div class="p-6 rounded-3xl border border-zinc-200/80 bg-zinc-50/50 hover:bg-white hover:border-[#00A3FF] hover:shadow-lg transition dark:border-zinc-800 dark:bg-zinc-900/60 dark:hover:bg-zinc-900" data-animate style="--reveal-delay:240ms">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 mb-5">
                        <flux:icon icon="chat-bubble-bottom-center-text" class="size-6" />
                    </div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Pledges & Automated Bulk SMS</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Track fulfillment of parent fee pledges and send automated branded SMS reminders to follow up on balances and boost fee collection.
                    </p>
                </div>

                {{-- Card 5: Instant M-Pesa Receipting & AI OCR --}}
                <div class="p-6 rounded-3xl border border-zinc-200/80 bg-zinc-50/50 hover:bg-white hover:border-[#00A3FF] hover:shadow-lg transition dark:border-zinc-800 dark:bg-zinc-900/60 dark:hover:bg-zinc-900" data-animate style="--reveal-delay:320ms">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-[#00A3FF]/10 text-[#00A3FF] mb-5">
                        <flux:icon icon="receipt-percent" class="size-6" />
                    </div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Instant Receipting & AI OCR</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Automatically match M-Pesa Paybill payments to student ledgers and scan bank deposit slips with our AI camera OCR for instant digital receipts.
                    </p>
                </div>

                {{-- Card 6: 100% Cloud Hosted & Zero Data Loss --}}
                <div class="p-6 rounded-3xl border border-zinc-200/80 bg-zinc-50/50 hover:bg-white hover:border-[#00A3FF] hover:shadow-lg transition dark:border-zinc-800 dark:bg-zinc-900/60 dark:hover:bg-zinc-900" data-animate style="--reveal-delay:400ms">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 mb-5">
                        <flux:icon icon="cloud-arrow-up" class="size-6" />
                    </div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">100% Cloud & Bank-Grade Security</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Hosted on high-speed cloud infrastructure with daily automated backups, strict tenant data isolation, and 100% zero data loss guarantee.
                    </p>
                </div>
            </div>
        </x-frontend.container>
    </section>

    {{-- Comprehensive Comparison Matrix --}}
    <section class="bg-zinc-50 py-20 sm:py-28 dark:bg-white/[0.02]">
        <x-frontend.container>
            <div class="text-center max-w-2xl mx-auto mb-16" data-animate="fade">
                <p class="text-sm font-semibold tracking-wide text-[#00A3FF] uppercase">Why Schools Switch</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    How Solforbs SMS Compares to Alternative Methods
                </h2>
                <p class="mt-4 text-base text-zinc-600 dark:text-zinc-400">
                    See why progressive institutions replace manual paper files, messy Excel spreadsheets, and fragmented single-purpose tools with Solforbs SMS.
                </p>
            </div>

            {{-- Comparison Table Container --}}
            <div class="overflow-x-auto rounded-3xl border border-zinc-200 bg-white shadow-xl dark:border-zinc-800 dark:bg-zinc-900" data-animate>
                <table class="w-full text-left text-sm border-collapse min-w-[700px]">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-950/50">
                            <th class="py-5 px-6 font-bold text-zinc-900 dark:text-white text-base">Key Capability</th>
                            <th class="py-5 px-6 font-extrabold text-[#00A3FF] text-base bg-[#00A3FF]/[0.06] border-x border-[#00A3FF]/20">
                                🌟 Solforbs SMS
                            </th>
                            <th class="py-5 px-6 font-semibold text-zinc-600 dark:text-zinc-400 text-center">Paper & Files</th>
                            <th class="py-5 px-6 font-semibold text-zinc-600 dark:text-zinc-400 text-center">Excel Sheets</th>
                            <th class="py-5 px-6 font-semibold text-zinc-600 dark:text-zinc-400 text-center">Fragmented Apps (e.g. Zeraki)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        {{-- Row 1: All-In-One Unified Login --}}
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-white/[0.01] transition">
                            <td class="py-4 px-6 font-semibold text-zinc-900 dark:text-white">
                                All-in-One Unified Suite
                                <p class="text-xs font-normal text-zinc-500">Admissions, Fees, Exams, Timetables & Staff in one login</p>
                            </td>
                            <td class="py-4 px-6 bg-[#00A3FF]/[0.06] border-x border-[#00A3FF]/20 text-center font-bold text-[#00A3FF]">
                                <span class="inline-flex items-center gap-1.5 text-emerald-600 font-bold">✓ 100% Unified</span>
                            </td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Disconnected</td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Scattered Files</td>
                            <td class="py-4 px-6 text-center text-amber-500 font-medium">⚠️ Exam-Only / Partial</td>
                        </tr>

                        {{-- Row 2: Lipa na M-Pesa Auto Reconciliation --}}
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-white/[0.01] transition">
                            <td class="py-4 px-6 font-semibold text-zinc-900 dark:text-white">
                                Automated Lipa na M-Pesa Matching
                                <p class="text-xs font-normal text-zinc-500">Real-time Paybill verification & student ledger credit</p>
                            </td>
                            <td class="py-4 px-6 bg-[#00A3FF]/[0.06] border-x border-[#00A3FF]/20 text-center font-bold text-emerald-600">
                                ✓ Instant & Automated
                            </td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Manual Bank Slips</td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Manual CSV Typing</td>
                            <td class="py-4 px-6 text-center text-amber-500 font-medium">⚠️ Extra Paid Module</td>
                        </tr>

                        {{-- Row 3: Biometric Turnstiles Sync --}}
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-white/[0.01] transition">
                            <td class="py-4 px-6 font-semibold text-zinc-900 dark:text-white">
                                Biometric Turnstile Hardware Sync
                                <p class="text-xs font-normal text-zinc-500">ZKTeco K40 Pro offline attendance sync + gate SMS</p>
                            </td>
                            <td class="py-4 px-6 bg-[#00A3FF]/[0.06] border-x border-[#00A3FF]/20 text-center font-bold text-emerald-600">
                                ✓ Native Hardware Sync
                            </td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Paper Registers</td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Manual Headcounts</td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Not Supported</td>
                        </tr>

                        {{-- Row 4: AI Receipt OCR Scanner --}}
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-white/[0.01] transition">
                            <td class="py-4 px-6 font-semibold text-zinc-900 dark:text-white">
                                AI Receipt OCR Scanner
                                <p class="text-xs font-normal text-zinc-500">Scan bank deposit slips and auto-extract amounts</p>
                            </td>
                            <td class="py-4 px-6 bg-[#00A3FF]/[0.06] border-x border-[#00A3FF]/20 text-center font-bold text-emerald-600">
                                ✓ AI-Powered
                            </td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Manual Filing</td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Manual Data Entry</td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Not Supported</td>
                        </tr>

                        {{-- Row 5: Multi-Campus Support --}}
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-white/[0.01] transition">
                            <td class="py-4 px-6 font-semibold text-zinc-900 dark:text-white">
                                Multi-Campus Architecture
                                <p class="text-xs font-normal text-zinc-500">Manage multiple school branches under one director dashboard</p>
                            </td>
                            <td class="py-4 px-6 bg-[#00A3FF]/[0.06] border-x border-[#00A3FF]/20 text-center font-bold text-emerald-600">
                                ✓ Built-in Multi-Campus
                            </td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Physically Isolated</td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Fragmented Folders</td>
                            <td class="py-4 px-6 text-center text-amber-500 font-medium">⚠️ Separate Subscriptions</td>
                        </tr>

                        {{-- Row 6: Pricing Transparency & Predictability --}}
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-white/[0.01] transition">
                            <td class="py-4 px-6 font-semibold text-zinc-900 dark:text-white">
                                All-Inclusive Flat Subscription
                                <p class="text-xs font-normal text-zinc-500">No hidden charges per module, per student, or per feature</p>
                            </td>
                            <td class="py-4 px-6 bg-[#00A3FF]/[0.06] border-x border-[#00A3FF]/20 text-center font-bold text-emerald-600">
                                ✓ Flat Termly Pricing
                            </td>
                            <td class="py-4 px-6 text-center text-zinc-400 font-medium">Stationery Costs</td>
                            <td class="py-4 px-6 text-center text-zinc-400 font-medium">Time & Human Error</td>
                            <td class="py-4 px-6 text-center text-red-500 font-medium">✕ Per-Student Add-Ons</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-frontend.container>
    </section>

    {{-- Architectural Principles --}}
    <section class="py-20 sm:py-28">
        <x-frontend.container>
            <x-frontend.section-heading eyebrow="Our Principles" data-animate>
                Four standards that guide how Solforbs is engineered
            </x-frontend.section-heading>

            <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <x-frontend.feature-card icon="academic-cap" title="Engineered for Kenyan Workflows" data-animate
                    style="--reveal-delay:0ms">
                    Designed around real daily school office operations in Kenya, with full CBC assessment keys, KCSE grading curves, and term schedules.
                </x-frontend.feature-card>

                <x-frontend.feature-card icon="shield-check" title="Tenant-Isolated Security" data-animate
                    style="--reveal-delay:80ms">
                    Every school gets its own completely isolated workspace. Student profiles, financial ledgers, and exam marks are never intermingled.
                </x-frontend.feature-card>

                <x-frontend.feature-card icon="user-group" title="Unified Stakeholder Portals" data-animate
                    style="--reveal-delay:160ms">
                    Directors, teachers, accountants, and parents each get tailored, role-specific interfaces with exact permissions and zero noise.
                </x-frontend.feature-card>

                <x-frontend.feature-card icon="banknotes" title="Native Kenyan Integrations" data-animate
                    style="--reveal-delay:240ms">
                    Direct integration with Safaricom Daraja M-Pesa Paybill, ZKTeco biometric gate controllers, and custom alphanumeric SMS sender IDs.
                </x-frontend.feature-card>
            </div>
        </x-frontend.container>
    </section>

    {{-- CTA Banner --}}
    <section class="pb-20 sm:pb-28">
        <x-frontend.container>
            <x-frontend.cta-band heading="Ready to transform your school office?"
                description="Experience the future of Kenyan school management with zero server setup and free historical data onboarding.">
                <flux:button :href="route('services')" variant="outline" class="bg-white/10 hover:bg-white/20 text-white border-white/30" wire:navigate>
                    Explore all services
                </flux:button>
                <flux:button :href="route('register')" class="bg-white text-[#0077CC] hover:bg-blue-50 font-bold border-none shadow-md" wire:navigate>
                    Get started
                </flux:button>
            </x-frontend.cta-band>
        </x-frontend.container>
    </section>
</x-layouts::frontend.app>
