<x-layouts::app :title="__('Choose your plan')">
    <div class="p-4">
        <div class="mx-auto max-w-5xl">
            <flux:heading size="xl">Choose your plan</flux:heading>
            <flux:text class="mt-1 mb-6">
                @if ($setupFee > 0)
                    Pick the plan your school will run on, then settle the one-off setup fee. You'll register your
                    school straight after — the plan itself isn't billed until your trial ends.
                @else
                    Pick the plan your school will run on. You'll register your school next.
                @endif
            </flux:text>

            @if (session('error'))
                <flux:callout variant="danger" class="mb-6">
                    <flux:callout.text>{{ session('error') }}</flux:callout.text>
                </flux:callout>
            @endif

            @if ($setupFee > 0)
                <flux:card class="mb-6">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <flux:heading size="sm">One-off setup fee</flux:heading>
                            <flux:text size="sm" class="text-zinc-500">
                                Charged once, to get your school set up. Your plan is billed separately, later.
                            </flux:text>
                        </div>
                        <flux:heading size="lg">
                            {{ \App\Models\Plan::CURRENCY }} {{ number_format($setupFee, 2) }}
                        </flux:heading>
                    </div>
                </flux:card>
            @endif

            @if ($plans->isEmpty())
                <flux:card class="py-10 text-center">
                    <flux:text class="text-zinc-500">
                        No plans are available right now.
                        <a href="{{ route('contact') }}" class="underline">Contact us</a> and we'll set your school up.
                    </flux:text>
                </flux:card>
            @else
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($plans as $plan)
                        <flux:card @class(['flex flex-col', 'ring-2 ring-indigo-500' => $plan->is_featured])>
                            @if ($plan->is_featured)
                                <flux:badge color="indigo" size="sm" class="mb-2 self-start">Most popular</flux:badge>
                            @endif

                            <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                            <flux:text size="sm" class="mt-1 text-zinc-500">{{ $plan->description }}</flux:text>

                            <div class="mt-4 flex items-baseline gap-1">
                                <span class="text-3xl font-semibold">{{ $plan->priceLabel() }}</span>
                                @if ($plan->periodLabel())
                                    <span class="text-sm text-zinc-500">{{ $plan->periodLabel() }}</span>
                                @endif
                            </div>

                            <ul class="mt-4 mb-6 grid flex-1 gap-x-4 gap-y-1.5 text-sm text-zinc-600 sm:grid-cols-2 dark:text-zinc-400">
                                @foreach ($plan->inclusions() as $inclusion)
                                    <li>{{ $inclusion }}</li>
                                @endforeach
                            </ul>

                            @if ($plan->isSelfServe())
                                <form method="POST" action="{{ route('onboarding.pay') }}">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                    <flux:button type="submit" variant="primary" class="w-full">
                                        {{ $setupFee > 0
                                            ? 'Pay ' . \App\Models\Plan::CURRENCY . ' ' . number_format($setupFee, 2) . ' & continue'
                                            : 'Choose ' . $plan->name }}
                                    </flux:button>
                                </form>
                            @else
                                {{-- Quoted plans have no amount to check out against. --}}
                                <flux:button :href="route('contact')" variant="outline" class="w-full">
                                    Talk to us
                                </flux:button>
                            @endif
                        </flux:card>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
