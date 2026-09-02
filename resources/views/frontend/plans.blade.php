<x-layouts::frontend.app title="Plans">
    <x-frontend.page-hero eyebrow="Plans"
        description="Pick the plan that matches your school today — actual rates depend on your size and needs, so get in touch and we'll confirm a quote.">
        Simple pricing that grows with your school
    </x-frontend.page-hero>

    <section class="py-4 sm:py-8" x-data="{ billingPeriod: 'termly', selectedPlan: '{{ $plans->where('is_featured', true)->first()?->slug ?? ($plans->first()?->slug ?? 'premium') }}' }">
        <x-frontend.container>
            @if ($plans->isEmpty())
                {{-- No plan is on sale. Better to say so and point at a human
                     than to render an empty grid. --}}
                <div class="mx-auto max-w-xl text-center" data-animate>
                    <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                        We're putting together pricing for the coming term.
                        <a href="{{ route('contact') }}" wire:navigate
                            class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Get in touch</a>
                        and we'll put a quote together for your school.
                    </p>
                </div>
            @else
                {{-- Billing Switcher (Per Term vs Annual / Full Year) --}}
                <div class="flex justify-center mb-10" data-animate>
                    <div class="inline-flex items-center gap-2 p-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 shadow-inner">
                        <button type="button" @click="billingPeriod = 'termly'"
                            :class="billingPeriod === 'termly' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-xs font-bold' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900'"
                            class="px-4 py-1.5 rounded-full text-xs sm:text-sm transition cursor-pointer">
                            Per Term (Standard)
                        </button>
                        <button type="button" @click="billingPeriod = 'annual'"
                            :class="billingPeriod === 'annual' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-xs font-bold' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900'"
                            class="px-4 py-1.5 rounded-full text-xs sm:text-sm transition flex items-center gap-1.5 cursor-pointer">
                            <span>Full Year (3 Terms)</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Save 15%</span>
                        </button>
                    </div>
                </div>

                <div @class([
                    'grid gap-5 lg:gap-6 lg:items-start',
                    'lg:grid-cols-2' => $plans->count() === 2,
                    'lg:grid-cols-3' => $plans->count() !== 2,
                ])>
                    @foreach ($plans as $plan)
                        @php
                            if ($plan->is_custom_priced) {
                                $annualPrice = 'Custom';
                            } elseif ((float) $plan->price <= 0) {
                                $annualPrice = 'Free';
                            } else {
                                // Annual prepayment: 3 school terms with 15% discount
                                $annualAmount = (float) $plan->price * 3 * 0.85;
                                $annualPrice = 'KES ' . number_format($annualAmount);
                            }
                        @endphp
                        {{-- A quoted plan has no price to sign up against, so it
                             points at a person instead of the register form. --}}
                        <x-frontend.pricing-card 
                            :name="$plan->name" 
                            :slug="$plan->slug"
                            :price="$plan->priceLabel()" 
                            :annualPrice="$annualPrice"
                            :period="$plan->periodLabel()" 
                            :description="$plan->description"
                            :featured="$plan->is_featured"
                            :isCustom="$plan->is_custom_priced"
                            :ctaHref="$plan->isSelfServe() ? route('register') : route('contact')"
                            :ctaLabel="$plan->isSelfServe() ? 'Get started' : 'Talk to us'"
                            :data-animate="$plan->is_featured ? 'scale' : true"
                            style="--reveal-delay:{{ $loop->index * 90 }}ms">
                            @forelse ($plan->inclusions() as $inclusion)
                                <x-frontend.check-item>{{ $inclusion }}</x-frontend.check-item>
                            @empty
                                <x-frontend.check-item>Get in touch for what's included</x-frontend.check-item>
                            @endforelse
                        </x-frontend.pricing-card>
                    @endforeach
                </div>

                {{-- Payment Trust Band --}}
                <div class="mt-14 p-6 rounded-2xl bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200/70 dark:border-zinc-800 shadow-xs" data-animate>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                        <div class="flex flex-col items-center gap-1.5">
                            <div class="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-sm">
                                🟢
                            </div>
                            <span class="text-xs font-bold text-zinc-900 dark:text-white">Lipa na M-Pesa</span>
                            <span class="text-[11px] text-zinc-500">Safaricom Daraja Automated</span>
                        </div>
                        <div class="flex flex-col items-center gap-1.5">
                            <div class="size-9 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-sm">
                                💳
                            </div>
                            <span class="text-xs font-bold text-zinc-900 dark:text-white">Cards & Bank Wire</span>
                            <span class="text-[11px] text-zinc-500">Visa, Mastercard & EFT</span>
                        </div>
                        <div class="flex flex-col items-center gap-1.5">
                            <div class="size-9 rounded-xl bg-[#00A3FF]/10 text-[#00A3FF] flex items-center justify-center font-bold text-sm">
                                ⚡
                            </div>
                            <span class="text-xs font-bold text-zinc-900 dark:text-white">Instant Setup</span>
                            <span class="text-[11px] text-zinc-500">Zero Hardware Lock-In</span>
                        </div>
                        <div class="flex flex-col items-center gap-1.5">
                            <div class="size-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-sm">
                                🔒
                            </div>
                            <span class="text-xs font-bold text-zinc-900 dark:text-white">100% Isolated Data</span>
                            <span class="text-[11px] text-zinc-500">Daily Encrypted Cloud Backups</span>
                        </div>
                    </div>
                </div>

                <p class="mx-auto mt-8 max-w-2xl text-center text-sm text-zinc-500 dark:text-zinc-500">
                    Rates vary with your school's size and needs. <a href="{{ route('contact') }}" wire:navigate
                        class="font-medium text-[#00A3FF] hover:underline">Contact our onboarding team</a>
                    for a custom quotation tailored to your exact learner enrollment.
                </p>
            @endif
        </x-frontend.container>
    </section>

    <section class="py-20 sm:py-28">
        <x-frontend.container>
            <x-frontend.section-heading eyebrow="Every plan includes" data-animate>
                The essentials, no matter your size
            </x-frontend.section-heading>

            <div class="mt-16 grid gap-6 sm:grid-cols-3">
                <x-frontend.feature-card icon="lock-closed" title="An isolated workspace" data-animate
                    style="--reveal-delay:0ms">
                    Your school's students, staff and records stay in their own private space on the platform.
                </x-frontend.feature-card>
                <x-frontend.feature-card icon="shield-check" title="Role-based access" data-animate
                    style="--reveal-delay:80ms">
                    Every staff account only sees what its role allows, from day one.
                </x-frontend.feature-card>
                <x-frontend.feature-card icon="chat-bubble-left-right" title="The parent assistant" data-animate
                    style="--reveal-delay:160ms">
                    Verified, self-service answers for parents on every plan, not just the higher tiers.
                </x-frontend.feature-card>
            </div>
        </x-frontend.container>
    </section>

    {{-- Frequently Asked Questions (Interactive Accordion) --}}
    <section class="bg-zinc-50 py-20 sm:py-28 dark:bg-white/[0.02]">
        <x-frontend.container class="max-w-4xl">
            <div class="text-center max-w-2xl mx-auto mb-14" data-animate="fade">
                <p class="text-sm font-semibold tracking-wide text-[#00A3FF] uppercase">Transparency First</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">
                    Frequently Asked Questions
                </h2>
                <p class="mt-4 text-base text-zinc-600 dark:text-zinc-400">
                    Everything you need to know about pricing, payments, refunds, and getting your school started.
                </p>
            </div>

            <div class="space-y-4" x-data="{ activeFaq: 1 }">
                {{-- FAQ 1: Payment Methods --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/70 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 1 ? null : 1" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-[#00A3FF] transition cursor-pointer">
                        <span class="text-base sm:text-lg">How do we pay? Can we use Lipa na M-Pesa or Bank Wire?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 1 ? 'rotate-180 text-[#00A3FF]' : ''" />
                    </button>
                    <div x-show="activeFaq === 1" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        Yes! We support automated <strong>Lipa na M-Pesa (Paybill & Buy Goods)</strong> with instant workspace activation, direct Bank Wire / EFT transfers, and Visa/Mastercard. Official electronic invoices and payment receipts are automatically emailed to your school's finance office.
                    </div>
                </div>

                {{-- FAQ 2: Refund Policy --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/70 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 2 ? null : 2" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-[#00A3FF] transition cursor-pointer">
                        <span class="text-base sm:text-lg">What is your refund and cancellation policy?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 2 ? 'rotate-180 text-[#00A3FF]' : ''" />
                    </button>
                    <div x-show="activeFaq === 2" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        We offer a <strong>14-Day 100% Money-Back Guarantee</strong>. If Solforbs does not meet your expectations during the first two weeks of your subscription, we will issue a full refund with zero questions asked. You can also cancel or adjust your subscription before the next billing term with no penalties.
                    </div>
                </div>

                {{-- FAQ 3: Onboarding & Excel Migration --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/70 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 3 ? null : 3" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-[#00A3FF] transition cursor-pointer">
                        <span class="text-base sm:text-lg">How do we migrate our existing student data from Excel?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 3 ? 'rotate-180 text-[#00A3FF]' : ''" />
                    </button>
                    <div x-show="activeFaq === 3" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        Migration is 100% free and handled by our technical onboarding team. We provide easy Excel templates for student bio-data, guardian contacts, and past fee balances, importing your complete school roster in under 24 hours.
                    </div>
                </div>

                {{-- FAQ 4: Biometric Hardware Requirements --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/70 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 4 ? null : 4" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-[#00A3FF] transition cursor-pointer">
                        <span class="text-base sm:text-lg">Do we need to buy expensive servers or special hardware?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 4 ? 'rotate-180 text-[#00A3FF]' : ''" />
                    </button>
                    <div x-show="activeFaq === 4" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        No expensive servers required. Solforbs is 100% cloud-hosted. For biometric gate attendance, we support standard, affordable <strong>ZKTeco K40 Pro</strong> devices that connect over standard Wi-Fi or Ethernet cables.
                    </div>
                </div>

                {{-- FAQ 5: Changing Plans as School Grows --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/70 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 5 ? null : 5" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-[#00A3FF] transition cursor-pointer">
                        <span class="text-base sm:text-lg">Can we upgrade our plan later as learner enrollment increases?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 5 ? 'rotate-180 text-[#00A3FF]' : ''" />
                    </button>
                    <div x-show="activeFaq === 5" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        Yes. You can upgrade from Standard to Premium or Enterprise at any time. When upgrading, all your existing student records, marks ledgers, and biometric templates transfer seamlessly with zero downtime.
                    </div>
                </div>

                {{-- FAQ 6: Data Ownership & Security --}}
                <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/70 overflow-hidden shadow-xs">
                    <button type="button" @click="activeFaq = activeFaq === 6 ? null : 6" class="w-full px-6 py-5 text-left flex items-center justify-between gap-4 font-bold text-zinc-900 dark:text-white hover:text-[#00A3FF] transition cursor-pointer">
                        <span class="text-base sm:text-lg">Who owns our school data, and can we export our records?</span>
                        <flux:icon icon="chevron-down" class="size-5 text-zinc-400 transition-transform duration-200 shrink-0" ::class="activeFaq === 6 ? 'rotate-180 text-[#00A3FF]' : ''" />
                    </button>
                    <div x-show="activeFaq === 6" x-collapse x-cloak class="px-6 pb-5 text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed border-t border-zinc-100 dark:border-zinc-800/60 pt-4">
                        Your institution owns 100% of your data. You can export student records, fee transactions, academic results, and attendance registers to Excel, CSV, or PDF at any time. All data is backed up daily with enterprise encryption.
                    </div>
                </div>
            </div>
        </x-frontend.container>
    </section>

    <section class="py-20 sm:py-28">
        <x-frontend.container>
            <x-frontend.cta-band heading="Not sure which plan fits?"
                description="Tell us about your school and we'll help you figure it out.">
                <flux:button :href="route('contact')" variant="primary" wire:navigate>
                    Talk to us
                </flux:button>
                <flux:button :href="route('register')" variant="ghost" wire:navigate>
                    Get started
                </flux:button>
            </x-frontend.cta-band>
        </x-frontend.container>
    </section>
</x-layouts::frontend.app>
