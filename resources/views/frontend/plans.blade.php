<x-layouts::frontend.app title="Plans">
    <x-frontend.page-hero eyebrow="Plans"
        description="Pick the plan that matches your school today — actual rates depend on your size and needs, so get in touch and we'll confirm a quote.">
        Simple pricing that grows with your school
    </x-frontend.page-hero>

    <section class="py-4 sm:py-8">
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
                <div @class([
                    'grid gap-8 lg:items-start',
                    'lg:grid-cols-2' => $plans->count() === 2,
                    'lg:grid-cols-3' => $plans->count() !== 2,
                ])>
                    @foreach ($plans as $plan)
                        {{-- A quoted plan has no price to sign up against, so it
                             points at a person instead of the register form. --}}
                        <x-frontend.pricing-card :name="$plan->name" :price="$plan->priceLabel()" :period="$plan->periodLabel()" :description="$plan->description"
                            :featured="$plan->is_featured"
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

                <p class="mx-auto mt-10 max-w-2xl text-center text-sm text-zinc-500 dark:text-zinc-500">
                    Rates vary with your school's size and needs. <a href="{{ route('contact') }}" wire:navigate
                        class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">Contact us</a>
                    for a quote based on your enrolment.
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

    <section class="bg-zinc-50 py-20 sm:py-28 dark:bg-white/[0.02]">
        <x-frontend.container class="max-w-3xl">
            <x-frontend.section-heading eyebrow="Questions" data-animate>
                A few things schools usually ask
            </x-frontend.section-heading>

            <div class="mt-12 space-y-8" data-animate>
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">How does school approval work?
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                        After you register and set up your school's workspace, our team reviews and activates it
                        before your staff can sign in and start using it.
                    </p>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Can I change plans later?</h3>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                        Yes — reach out through the contact page and we'll adjust your plan as your school grows.
                    </p>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Is my school's data separate from
                        other schools?</h3>
                    <p class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                        Yes. Every institution gets its own isolated workspace — students, staff and records are
                        never shared across schools.
                    </p>
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
