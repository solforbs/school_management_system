<x-layouts::app :title="__('Expenditure')">
    <div class="p-4">

        @if (session('success'))
            <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between rounded-t-lg border-b border-gray-200 bg-gray-50 px-6 py-4">
                <div>
                    <h4 class="mb-0 text-lg font-semibold text-gray-900">{{ $expenditure->title }}</h4>
                    <small class="text-sm text-gray-500">
                        {{ $expenditure->category?->name ?? 'Uncategorised' }} ·
                        {{ $expenditure->spent_on?->format('d M Y') }}
                    </small>
                </div>
                <flux:badge
                    :color="match ($expenditure->status) {
                        'paid' => 'emerald',
                        'approved' => 'blue',
                        'cancelled' => 'red',
                        default => 'amber',
                    }">
                    {{ ucfirst($expenditure->status) }}
                </flux:badge>
            </div>

            <div class="p-6">
                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Amount</p>
                        <p class="text-sm font-semibold text-gray-900">{{ number_format($expenditure->amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Paid To</p>
                        <p class="text-sm text-gray-900">{{ $expenditure->payee ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Payment Method</p>
                        <p class="text-sm text-gray-900">
                            {{ ucfirst(str_replace('_', ' ', $expenditure->payment_method)) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Reference</p>
                        <p class="text-sm text-gray-900">{{ $expenditure->reference ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Paid At</p>
                        <p class="text-sm text-gray-900">{{ $expenditure->paid_at?->format('d M Y H:i') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Recorded By</p>
                        <p class="text-sm text-gray-900">{{ $expenditure->recordedBy?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Institution</p>
                        <p class="text-sm text-gray-900">{{ $expenditure->institution?->name }}</p>
                    </div>
                </div>

                @if ($expenditure->notes)
                    <h5 class="text-md mb-2 font-semibold text-gray-800">Notes</h5>
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ $expenditure->notes }}</p>
                @endif
            </div>

            <div class="flex justify-end gap-3 rounded-b-lg border-t border-gray-200 bg-gray-50 px-6 py-4">
                <a href="{{ route('expenditure.index') }}"
                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" wire:navigate>
                    Back to Expenditure
                </a>
                @can('edit expenditure')
                    <a href="{{ route('expenditure.edit', $expenditure->id) }}"
                        class="rounded-md border border-transparent bg-yellow-500 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-600" wire:navigate>
                        Edit
                    </a>
                @endcan
                @can('delete expenditure')
                    <form action="{{ route('expenditure.destroy', $expenditure->id) }}" method="POST"
                        onsubmit="return confirm('Remove this expenditure?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-layouts::app>
