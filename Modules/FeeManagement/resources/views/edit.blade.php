@php
    $feeItem = $fee ?? $feeManagement;
@endphp
<x-layouts::app :title="__('Edit Fee')">
    <div class="flex flex-col gap-6 w-full max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-200 dark:border-zinc-800 pb-6 mb-2">
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex size-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-500/10 ring-1 ring-blue-500/20">
                    <flux:icon icon="pencil-square" class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Edit Fee</flux:heading>
                    <flux:text class="text-zinc-500 text-sm mt-1">
                        Update details for <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $feeItem?->title }}</span>
                    </flux:text>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button href="{{ route('feemanagement.index') }}" variant="subtle" icon="arrow-left" class="hidden sm:flex">
                    Back to Fees
                </flux:button>
            </div>
        </div>

        @if (isset($errors) && $errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('feemanagement.update', $feeItem->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('feemanagement::partials._form', ['feeManagement' => $feeItem, 'fee' => $feeItem])
        </form>
    </div>
</x-layouts::app>