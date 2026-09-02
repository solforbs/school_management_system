<x-layouts::frontend.app title="Contact Us">
    <x-frontend.page-hero eyebrow="Contact Solforbs"
        description="Speak directly with our technical onboarding specialists and product advisors in Nairobi. We are ready to help you set up your school workspace.">
        Let's talk about your school
    </x-frontend.page-hero>

    <section class="pb-20 sm:pb-28">
        <x-frontend.container class="grid gap-12 lg:grid-cols-5">
            {{-- Left Column: Direct Contacts & Channels --}}
            <div class="lg:col-span-2 space-y-6" data-animate="left">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                        Get in Touch Directly
                    </h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        Whether you want to schedule a live system demonstration, inquire about biometric turnstiles, or need technical assistance.
                    </p>
                </div>

                {{-- Direct Phone & WhatsApp Cards --}}
                <div class="space-y-3">
                    <a href="https://wa.me/254725996394?text=Hello%20Solforbs%20Team,%20I%20would%20like%20to%20inquire%20about%20the%20School%20Management%20System" target="_blank"
                        class="flex items-center gap-4 p-4 rounded-2xl border border-zinc-200 bg-white hover:border-[#00A3FF] hover:shadow-md transition dark:border-zinc-800 dark:bg-zinc-900 group">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <flux:icon icon="phone" class="size-5" />
                        </span>
                        <div>
                            <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Direct Hotline & WhatsApp</p>
                            <p class="text-sm font-bold text-zinc-900 group-hover:text-[#00A3FF] transition dark:text-white">+254 725 996 394</p>
                        </div>
                    </a>

                    <a href="https://wa.me/254711777992?text=Hello%20Solforbs%20Team,%20I%20would%20like%20to%20inquire%20about%20the%20School%20Management%20System" target="_blank"
                        class="flex items-center gap-4 p-4 rounded-2xl border border-zinc-200 bg-white hover:border-[#00A3FF] hover:shadow-md transition dark:border-zinc-800 dark:bg-zinc-900 group">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <flux:icon icon="phone" class="size-5" />
                        </span>
                        <div>
                            <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Alternative Inquiries</p>
                            <p class="text-sm font-bold text-zinc-900 group-hover:text-[#00A3FF] transition dark:text-white">+254 711 777 992</p>
                        </div>
                    </a>

                    <a href="mailto:support@solforbs.com"
                        class="flex items-center gap-4 p-4 rounded-2xl border border-zinc-200 bg-white hover:border-[#00A3FF] hover:shadow-md transition dark:border-zinc-800 dark:bg-zinc-900 group">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#00A3FF]/10 text-[#00A3FF]">
                            <flux:icon icon="envelope" class="size-5" />
                        </span>
                        <div>
                            <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Technical & General Support</p>
                            <p class="text-sm font-bold text-zinc-900 group-hover:text-[#00A3FF] transition dark:text-white">support@solforbs.com</p>
                        </div>
                    </a>

                    <a href="mailto:sales@solforbs.com"
                        class="flex items-center gap-4 p-4 rounded-2xl border border-zinc-200 bg-white hover:border-[#00A3FF] hover:shadow-md transition dark:border-zinc-800 dark:bg-zinc-900 group">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#00A3FF]/10 text-[#00A3FF]">
                            <flux:icon icon="building-office-2" class="size-5" />
                        </span>
                        <div>
                            <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">School Sales & Custom Quotes</p>
                            <p class="text-sm font-bold text-zinc-900 group-hover:text-[#00A3FF] transition dark:text-white">sales@solforbs.com</p>
                        </div>
                    </a>
                </div>

                {{-- Fast Response Guarantee & Location --}}
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 p-5 dark:border-zinc-800 dark:bg-zinc-900/50 space-y-3">
                    <div class="flex items-center gap-2 text-xs font-bold text-emerald-600 dark:text-emerald-400">
                        <flux:icon icon="check-badge" class="size-4" />
                        <span>15-Minute Response Pledge (School Hours)</span>
                    </div>
                    <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        📍 <strong>Nairobi, Kenya</strong> • Operating Hours: Mon – Fri 8:00 AM – 6:00 PM EAT, Sat 9:00 AM – 1:00 PM.
                    </p>
                </div>
            </div>

            {{-- Right Column: Livewire Contact Message Form --}}
            <div class="lg:col-span-3" data-animate="right">
                <div class="rounded-3xl border border-zinc-200 bg-white p-6 sm:p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-6 border-b border-zinc-100 dark:border-zinc-800 pb-4">
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Send Us a Direct Message</h3>
                        <p class="text-xs text-zinc-500 mt-1">Fill in the details below and our team will get back to you promptly.</p>
                    </div>
                    <livewire:contact-form />
                </div>
            </div>
        </x-frontend.container>
    </section>
</x-layouts::frontend.app>
