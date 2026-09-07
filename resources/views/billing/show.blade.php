<x-layouts::app :title="__('Billing')">
    <div class="p-4 space-y-6">
        <flux:heading size="lg">Billing & Subscription</flux:heading>

        <flux:card class="p-6">
            <flux:heading size="md">Current Plan</flux:heading>
            <div class="mt-2 flex items-center gap-3">
                <flux:badge :color="$institution->hasActiveSubscription() ? 'emerald' : 'amber'">
                    {{ $institution->plan?->name ?? 'No plan yet' }}
                </flux:badge>
                @if ($institution->subscription_expires_at)
                    <flux:text class="text-sm text-gray-500">
                        {{ $institution->subscriptionActive() ? 'Renews' : 'Expired' }}
                        {{ $institution->subscription_expires_at->format('d M Y') }}
                    </flux:text>
                @endif
            </div>
        </flux:card>

        @php
            $credit = $institution->unusedPlanCredit();
        @endphp
        @if ($credit > 0)
            <flux:text class="text-sm text-gray-500">
                You have <span class="font-semibold text-gray-700 dark:text-gray-300">KES
                    {{ number_format($credit, 2) }}</span> of unused value on your current plan
                ({{ $institution->subscriptionDaysRemaining() }} day(s) left). Upgrading applies it against the new
                plan's price — you pay only the difference.
            </flux:text>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($plans as $plan)
                @php
                    $isCurrent = $institution->hasActiveSubscription() && (int) $institution->subscription_plan === (int) $plan->id;
                    $quote = $institution->upgradeQuoteFor($plan);
                    $amountDue = $quote['due'];
                    $showsCredit = ! $isCurrent && $credit > 0 && $amountDue < (float) $plan->price;
                @endphp
                <flux:card class="p-6 flex flex-col {{ $isCurrent ? 'ring-2 ring-emerald-500' : '' }}">
                    <flux:heading size="md">{{ $plan->name }}</flux:heading>
                    <flux:text class="text-2xl font-bold mt-2">
                        KES {{ number_format($plan->price, 2) }}
                        <span class="text-sm font-normal text-gray-500">/ {{ $plan->billing_cycle }}</span>
                    </flux:text>
                    @if ($plan->description)
                        <flux:text class="text-sm text-gray-500 mt-2">{{ $plan->description }}</flux:text>
                    @endif

                    <div class="mt-4 flex-1"></div>

                    @if ($isCurrent)
                        <flux:badge color="emerald" class="w-fit">Current Plan</flux:badge>
                    @elseif ($amountDue > 0)
                        @if ($showsCredit)
                            <flux:text class="text-xs text-gray-500 mb-1">
                                KES {{ number_format($plan->price, 2) }} &minus; KES {{ number_format($credit, 2) }} credit
                            </flux:text>
                        @endif
                        <form action="{{ route('billing.initiate') }}" method="POST">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <flux:button type="submit" variant="primary" class="w-full">
                                Pay KES {{ number_format($amountDue, 2) }}
                            </flux:button>
                        </form>
                    @else
                        <flux:button disabled variant="filled" class="w-full">
                            {{ $credit > 0 ? 'Covered by your credit' : 'Not available' }}
                        </flux:button>
                    @endif
                </flux:card>
            @endforeach
        </div>

        <flux:card>
            <flux:heading size="md" class="mb-4">Payment History</flux:heading>
            <flux:table class="p-2">
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Plan</flux:table.column>
                    <flux:table.column>Amount</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Reference</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($payments as $payment)
                        <flux:table.row>
                            <flux:table.cell>{{ $payment->created_at->format('d M Y H:i') }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $payment->plan?->name }}
                                {{-- Says what the money was for. Without this a setup
                                     fee is indistinguishable from a plan payment. --}}
                                @if ($payment->isSetupFee())
                                    <flux:badge size="sm" color="zinc" class="ml-1">Setup fee</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>KES {{ number_format($payment->amount, 2) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge
                                    :color="match ($payment->status) {
                                                                        'successful' => 'emerald',
                                                                        'pending' => 'amber',
                                                                        default => 'red',
                                                                    }">
                                    {{ ucfirst($payment->status) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $payment->reference }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">No payments yet.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</x-layouts::app>
