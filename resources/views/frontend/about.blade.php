<x-layouts::frontend.app title="About">
    <x-frontend.page-hero eyebrow="About us"
        description="{{ config('app.name') }} exists because school administration still runs on paper registers, scattered spreadsheets and manual SMS blasts in far too many schools. We think it shouldn't.">
        Software for the whole school office, not just the classroom
    </x-frontend.page-hero>

    <section class="py-4 sm:py-8">
        <x-frontend.container class="grid gap-16 lg:grid-cols-2 lg:items-center">
            <div data-animate="left">
                <p class="text-sm font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">
                    Why we built this
                </p>
                <h2
                    class="mt-3 text-3xl font-semibold tracking-tight text-balance text-zinc-900 sm:text-4xl dark:text-white">
                    One shared system, instead of five disconnected ones
                </h2>
                <div class="mt-6 space-y-4 text-lg text-pretty text-zinc-600 dark:text-zinc-400">
                    <p>
                        Most schools we've looked at don't lack effort — they lack a shared system. Attendance
                        lives in an exercise book, fees live in a ledger, results live in someone's laptop, and
                        parents find out what's happening from a phone call or a paper note sent home.
                    </p>
                    <p>
                        {{ config('app.name') }} puts admissions, attendance, fees, exams and parent
                        communication behind one login, with the right view for every role — so nothing
                        depends on one person's spreadsheet.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4" data-animate="right">
                <div class="space-y-4">
                    <div
                        class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-white/[0.03]">
                        <flux:icon icon="finger-print" class="size-6 text-indigo-600 dark:text-indigo-400" />
                        <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-white">Attendance that syncs itself
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-white/[0.03]">
                        <flux:icon icon="banknotes" class="size-6 text-indigo-600 dark:text-indigo-400" />
                        <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-white">Fees paid by M-Pesa, tracked
                            automatically</p>
                    </div>
                </div>
                <div class="mt-8 space-y-4">
                    <div
                        class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-white/[0.03]">
                        <flux:icon icon="chat-bubble-left-right" class="size-6 text-indigo-600 dark:text-indigo-400" />
                        <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-white">Parents who never have to call
                            the office</p>
                    </div>
                    <div
                        class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-white/10 dark:bg-white/[0.03]">
                        <flux:icon icon="clipboard-document-check"
                            class="size-6 text-indigo-600 dark:text-indigo-400" />
                        <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-white">Report cards nobody has to
                            re-type</p>
                    </div>
                </div>
            </div>
        </x-frontend.container>
    </section>

    <section class="py-20 sm:py-28">
        <x-frontend.container>
            <x-frontend.section-heading eyebrow="What we believe" data-animate>
                A few principles that shape how we build
            </x-frontend.section-heading>

            <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <x-frontend.feature-card icon="academic-cap" title="Built for real workflows" data-animate
                    style="--reveal-delay:0ms">
                    Designed around how school offices actually work day to day, not a generic template
                    bolted onto a school.
                </x-frontend.feature-card>

                <x-frontend.feature-card icon="shield-check" title="Secure by default" data-animate
                    style="--reveal-delay:80ms">
                    Every institution's data is isolated, and every sensitive request from a parent is
                    verified before anything is shared.
                </x-frontend.feature-card>

                <x-frontend.feature-card icon="user-group" title="Every stakeholder, one place" data-animate
                    style="--reveal-delay:160ms">
                    Administrators, teachers, and parents each get the view they need instead of three
                    separate tools that don't talk to each other.
                </x-frontend.feature-card>

                <x-frontend.feature-card icon="banknotes" title="Kenya-first payments" data-animate
                    style="--reveal-delay:240ms">
                    Native M-Pesa fee collection and SMS communication, because that's how the schools we
                    serve actually get paid and stay in touch.
                </x-frontend.feature-card>
            </div>
        </x-frontend.container>
    </section>

    <section class="pb-20 sm:pb-28">
        <x-frontend.container>
            <x-frontend.cta-band heading="See it for yourself"
                description="Explore what each module does, or create your school's workspace today.">
                <flux:button :href="route('services')" variant="outline" wire:navigate>
                    Browse services
                </flux:button>
                <flux:button :href="route('register')" variant="primary" wire:navigate>
                    Get started
                </flux:button>
            </x-frontend.cta-band>
        </x-frontend.container>
    </section>
</x-layouts::frontend.app>
