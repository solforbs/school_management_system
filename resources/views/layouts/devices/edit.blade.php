<x-layouts::app :title="__('Edit Biometric Device')">
    <div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                    <a href="{{ route('dashboard') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('devices.index') }}" class="hover:text-zinc-800 dark:hover:text-zinc-200">Biometric
                        Devices</a>
                    <span>/</span>
                    <span class="text-zinc-800 dark:text-zinc-200 font-medium">Edit</span>
                </div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        Edit Biometric Device
                    </flux:heading>
                    <flux:badge size="sm" :color="$device->is_active ? 'emerald' : 'zinc'" class="font-medium">
                        {{ $device->is_active ? 'Active' : 'Inactive' }}
                    </flux:badge>
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Update hardware settings, network IP configuration, and active terminal status.
                </flux:text>
            </div>

            <div>
                <flux:button wire:navigate href="{{ route('devices.index') }}" icon="arrow-left" variant="ghost"
                    class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    Back to Devices
                </flux:button>
            </div>
        </div>

        {{-- Form Card --}}
        <div
            class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 sm:p-8">
            <livewire:edit-device-form :device="$device" />
        </div>
    </div>
</x-layouts::app>
