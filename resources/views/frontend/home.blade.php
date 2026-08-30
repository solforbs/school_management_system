@php
    $chatbotCommands = config('chatbot.commands', []);
    $verifiedCommandCount = collect($chatbotCommands)->where('verified', true)->count();
@endphp

<x-layouts::frontend.app>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-zinc-50 dark:bg-zinc-950">
        <div class="pointer-events-none absolute inset-x-0 -top-40 -z-10 flex justify-center overflow-hidden blur-3xl">
            <div class="aspect-1155/678 w-[72rem] bg-gradient-to-tr from-[#00A3FF] to-[#0047FF] opacity-10 dark:opacity-20"
                style="clip-path: polygon(74% 44%, 100% 61%, 97% 26%, 85% 0%, 80% 2%, 72% 32%, 60% 62%, 32% 0%, 0% 5%, 12% 45%, 29% 70%, 22% 100%, 63% 90%)">
            </div>
        </div>

        <x-frontend.container class="grid gap-12 pt-16 pb-20 lg:grid-cols-2 lg:items-center lg:pt-24 lg:pb-28">
            <div data-animate="left">
                <x-frontend.badge icon="sparkles" class="text-[#0047FF]">
                    Solforbs School Management System (SMS)
                </x-frontend.badge>

                <h1 class="mt-6 text-4xl font-bold tracking-tight text-balance text-zinc-900 sm:text-5xl dark:text-white">
                    Run your school effortlessly,
                    <span class="bg-gradient-to-r from-[#00A3FF] to-[#0047FF] bg-clip-text text-transparent">without the spreadsheets.</span>
                </h1>

                <p class="mt-6 max-w-xl text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    Stop chasing fee payments and digging through messy paperwork. Solforbs gives you one simple platform to manage admissions, attendance, and exams—so you can focus on education.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-4">
                    <flux:button :href="route('register')" class="bg-gradient-to-r from-[#00A3FF] to-[#0047FF] text-white border-none hover:opacity-90" wire:navigate>
                        Start for Free
                    </flux:button>
                    <flux:button href="#how-it-works" variant="outline">
                        See how it works
                    </flux:button>
                </div>
            </div>

            <!-- Tilted Overlapping Device Mockups -->
            <div class="relative w-full max-w-lg mx-auto aspect-[4/3]" data-animate="right" style="--reveal-delay:120ms">
                
                <!-- Back Device (Tablet/Laptop) -->
                <div class="absolute right-0 top-4 w-[85%] transform rotate-3 hover:rotate-2 transition duration-500 rounded-xl border border-zinc-200 bg-white p-1.5 shadow-2xl shadow-zinc-900/10 dark:border-white/10 dark:bg-zinc-900">
                    <div class="flex items-center gap-1.5 px-3 py-2 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="size-2.5 rounded-full bg-red-400"></span>
                        <span class="size-2.5 rounded-full bg-amber-400"></span>
                        <span class="size-2.5 rounded-full bg-emerald-400"></span>
                        <span class="ml-3 text-[10px] font-semibold text-zinc-500 uppercase tracking-wide">Dashboard</span>
                    </div>
                    <div class="bg-zinc-100 dark:bg-zinc-800 w-full aspect-video rounded-b flex items-center justify-center flex-col gap-2 relative overflow-hidden">
                        <!-- Add your tablet/laptop screenshot here: -->
                        <!-- <img src="{{ asset('screenshots/tablet.png') }}" class="w-full h-full object-cover" /> -->
                         <flux:icon icon="photo" class="size-8 text-zinc-400" />
                         <span class="text-xs font-medium text-zinc-500">Desktop Screenshot Placeholder</span>
                    </div>
                </div>

                <!-- Front Device (Mobile) -->
                <div class="absolute left-2 bottom-4 w-[32%] transform -rotate-6 hover:-rotate-3 transition duration-500 rounded-[1.5rem] border-[4px] border-zinc-800 bg-zinc-900 shadow-2xl shadow-zinc-900/40 dark:border-zinc-700">
                    <div class="flex justify-center pt-2 pb-1 bg-zinc-900 rounded-t-[1.2rem]">
                        <div class="w-10 h-1.5 rounded-full bg-zinc-800 dark:bg-zinc-950"></div>
                    </div>
                    <div class="bg-zinc-100 dark:bg-zinc-800 w-full aspect-[9/16] rounded-b-[1.2rem] flex items-center justify-center flex-col gap-2 relative overflow-hidden">
                        <!-- Add your mobile screenshot here: -->
                        <!-- <img src="{{ asset('screenshots/mobile.png') }}" class="w-full h-full object-cover" /> -->
                         <flux:icon icon="device-phone-mobile" class="size-8 text-zinc-400" />
                         <span class="text-[10px] font-medium text-zinc-500 text-center px-2">Mobile App Placeholder</span>
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
                    We've eliminated the confusing technical jargon. Just clear, simple tools to solve your daily problems.
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
                        Stop dealing with lost paper forms. Let parents enroll their children online and approve them with one click.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-zinc-900 transition hover:shadow-md">
                    <div class="flex size-12 items-center justify-center rounded-lg bg-[#0047FF]/10 text-[#0047FF]">
                        <flux:icon icon="banknotes" class="size-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">Automated Fees</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Never chase a payment again. Automatically track who has paid and send friendly SMS reminders to parents who haven't.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-zinc-900 transition hover:shadow-md">
                    <div class="flex size-12 items-center justify-center rounded-lg bg-[#00A3FF]/10 text-[#00A3FF]">
                        <flux:icon icon="calendar-days" class="size-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">Smart Attendance</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Teachers can mark attendance in seconds from their phones. Instantly know which students are missing across the whole school.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-zinc-900 transition hover:shadow-md">
                    <div class="flex size-12 items-center justify-center rounded-lg bg-[#0047FF]/10 text-[#0047FF]">
                        <flux:icon icon="document-chart-bar" class="size-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">Exams & Reports</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Enter marks once and generate beautiful report cards automatically. No more manual calculations or Excel formulas.
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
