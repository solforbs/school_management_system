@php
    $onlineCount = $devices->filter(fn($d) => $d->zktecoDevice?->isOnline())->count();
    $offlineCount = $devices->filter(fn($d) => $d->zktecoDevice && !$d->zktecoDevice->isOnline())->count();
    $pendingCount = $devices->filter(fn($d) => !$d->zktecoDevice)->count();
@endphp

<x-layouts::app :title="__('Biometric Devices')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        Biometric Devices
                    </flux:heading>
                    <flux:badge size="sm" color="indigo" class="font-semibold">
                        {{ $deviceCount }} Total
                    </flux:badge>
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Manage hardware endpoints, biometric turnstiles, and live attendance clocks.
                </flux:text>
            </div>
            
            <div class="flex flex-wrap items-center gap-2.5">
                @if (Route::has('sync-devices.index'))
                    @can('view sync device')
                        <flux:button href="{{ route('sync-devices.index') }}" icon="arrow-path" variant="ghost" class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                            Sync Devices
                        </flux:button>
                    @endcan
                @endif

                <flux:button wire:navigate href="{{ route('devices.create') }}" icon="plus" variant="primary" class="shadow-sm hover:shadow-md transition-all">
                    Add Device
                </flux:button>
            </div>
        </div>

        {{-- Success Notification --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200/50 bg-emerald-50/50 dark:bg-emerald-500/10 dark:border-emerald-500/20 p-4 flex items-start shadow-sm">
                <flux:icon icon="check-circle" class="size-5 text-emerald-500 mr-3 mt-0.5" />
                <div class="text-sm font-medium text-emerald-800 dark:text-emerald-400">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        {{-- Animated KPI Metrics --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 sm:gap-4">
            <x-kpi-card title="Total Hardware" icon="device-phone-mobile" color="indigo" :count="$deviceCount" subtext="configured endpoints" />
            <x-kpi-card title="Online Now" icon="signal" color="emerald" :count="$onlineCount" countSuffix="/{{ $deviceCount }}" subtext="connected & transmitting" />
            <div class="col-span-2 sm:col-span-1">
                <x-kpi-card title="Offline / Pending" icon="exclamation-triangle" color="amber" :count="$offlineCount + $pendingCount" subtext="awaiting sync / connection" />
            </div>
        </div>

        {{-- Device Table Card --}}
        <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            @if ($devices->isEmpty())
                <div class="p-6 sm:p-10">
                    <x-empty-state icon="device-phone-mobile" color="indigo" title="No Biometric Devices Configured" description="Register your first biometric attendance terminal to begin recording real-time student and staff check-ins.">
                        <flux:button wire:navigate href="{{ route('devices.create') }}" icon="plus" variant="primary">
                            Add First Device
                        </flux:button>
                    </x-empty-state>
                </div>
            @else
                {{-- Mobile Stacked Cards (Visible on screen < 640px) --}}
                <div class="block sm:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($devices as $device)
                        @php
                            $isOnline = $device->zktecoDevice?->isOnline();
                            $hasDevice = (bool)$device->zktecoDevice;
                        @endphp
                        <div class="p-4 space-y-3 hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                            {{-- Row 1: Icon, Name, Serial Number & Status Badge --}}
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="relative flex size-10 shrink-0 items-center justify-center rounded-xl font-semibold shadow-xs {{ $isOnline ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 ring-1 ring-inset ring-emerald-500/20' : ($hasDevice ? 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 ring-1 ring-inset ring-zinc-500/20' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400 ring-1 ring-inset ring-amber-500/20') }}">
                                        <flux:icon icon="device-phone-mobile" class="size-5" />
                                        @if ($isOnline)
                                            <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-sm text-zinc-900 dark:text-white truncate">
                                            {{ $device->zktecoDevice?->name ?: 'Biometric Attendance Device' }}
                                        </div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="text-[11px] text-zinc-400 dark:text-zinc-500">SN:</span>
                                            <span class="font-mono text-[11px] font-semibold text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded border border-zinc-200/50 dark:border-zinc-700/50">
                                                {{ $device->serial_number }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    @if ($isOnline)
                                        <flux:badge color="emerald" size="sm" class="font-semibold whitespace-nowrap">
                                            <span class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                                            Online
                                        </flux:badge>
                                    @elseif ($hasDevice)
                                        <flux:badge color="zinc" size="sm" class="font-medium whitespace-nowrap">
                                            Offline
                                        </flux:badge>
                                    @else
                                        <flux:badge color="amber" size="sm" class="font-medium whitespace-nowrap">
                                            Not Connected
                                        </flux:badge>
                                    @endif
                                </div>
                            </div>

                            {{-- Row 2: Institution & IP Address --}}
                            <div class="grid grid-cols-2 gap-2 text-xs pt-1 border-t border-zinc-100 dark:border-zinc-800/80">
                                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-300 truncate">
                                    <flux:icon icon="building-office-2" class="size-3.5 text-zinc-400 shrink-0" />
                                    <span class="truncate"><span translate="no">{{ $device->institution?->name ?? '—' }}</span></span>
                                </div>
                                <div class="flex items-center justify-end gap-1.5 font-mono text-[11px] text-zinc-600 dark:text-zinc-300">
                                    <flux:icon icon="signal" class="size-3 text-zinc-400 shrink-0" />
                                    <span>{{ $device->zktecoDevice?->ip_address ?: 'No IP' }}</span>
                                </div>
                            </div>

                            {{-- Row 3: Action Buttons Footer --}}
                            <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-zinc-100 dark:border-zinc-800/80">
                                @can('manage devices')
                                    <flux:button wire:navigate href="{{ route('devices.manage', $device->id) }}" icon="cog-6-tooth" size="xs" variant="subtle" class="flex-1 justify-center">
                                        Manage
                                    </flux:button>
                                @endcan
                                <form action="{{ route('devices.set-time', $device) }}" method="POST" class="inline flex-1">
                                    @csrf
                                    <flux:button type="submit" variant="subtle" icon="clock" size="xs"
                                        :disabled="! $device->zktecoDevice" class="w-full justify-center">
                                        Sync Clock
                                    </flux:button>
                                </form>
                                <flux:button wire:navigate href="{{ route('devices.edit', $device) }}" icon="pencil" size="xs" variant="subtle" class="flex-1 justify-center">
                                    Edit
                                </flux:button>
                                <x-confirm-form
                                    :action="route('devices.destroy', $device)"
                                    title="Delete this device permanently?"
                                    message="The terminal is removed and stops syncing."
                                    confirm="Delete Device"
                                    tooltip="Delete Device"
                                    trigger-class="flex-1 justify-center text-xs"
                                    variant="subtle"
                                    icon="trash"
                                    :fields="['device_id' => $device->id, 'institution_id' => $device->institution->id]" />
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Desktop Table View --}}
                <div class="hidden sm:block overflow-x-auto">
                    <flux:table class="p-2 min-w-[720px]">
                        <flux:table.columns>
                            <x-sortable-column column="device_name">Device Details</x-sortable-column>
                            <x-sortable-column column="institution">Campus / Institution</x-sortable-column>
                            <x-sortable-column column="ip_address">Network IP</x-sortable-column>
                            <flux:table.column>Live Status</flux:table.column>
                            <flux:table.column>Actions</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($devices as $device)
                                @php
                                    $isOnline = $device->zktecoDevice?->isOnline();
                                    $hasDevice = (bool)$device->zktecoDevice;
                                @endphp
                                <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                    {{-- Device Details --}}
                                    <flux:table.cell>
                                        <div class="flex items-center gap-3">
                                            <div class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl font-semibold shadow-xs {{ $isOnline ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 ring-1 ring-inset ring-emerald-500/20' : ($hasDevice ? 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 ring-1 ring-inset ring-zinc-500/20' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400 ring-1 ring-inset ring-amber-500/20') }}">
                                                <flux:icon icon="device-phone-mobile" class="size-5" />
                                                @if ($isOnline)
                                                    <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                                    </span>
                                                @endif
                                            </div>
                                            <div>
                                                <div class="font-semibold text-zinc-900 dark:text-white">
                                                    {{ $device->zktecoDevice?->name ?: 'Biometric Attendance Device' }}
                                                </div>
                                                <div class="flex items-center gap-1.5 mt-0.5">
                                                    <span class="text-[11px] text-zinc-400 dark:text-zinc-500">SN:</span>
                                                    <span class="font-mono text-[11px] font-medium text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded border border-zinc-200/50 dark:border-zinc-700/50">
                                                        {{ $device->serial_number }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </flux:table.cell>

                                    {{-- Institution --}}
                                    <flux:table.cell>
                                        <div class="flex items-center gap-2 text-zinc-600 dark:text-zinc-300 text-sm">
                                            <flux:icon icon="building-office-2" class="size-4 text-zinc-400 shrink-0" />
                                            <span class="truncate max-w-[200px]"><span translate="no">{{ $device->institution?->name ?? '—' }}</span></span>
                                        </div>
                                    </flux:table.cell>

                                    {{-- Network IP --}}
                                    <flux:table.cell>
                                        @if ($device->zktecoDevice?->ip_address)
                                            <span class="font-mono text-xs text-zinc-700 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800/80 px-2 py-1 rounded-md border border-zinc-200/60 dark:border-zinc-700/60">
                                                {{ $device->zktecoDevice->ip_address }}
                                            </span>
                                        @else
                                            <span class="text-zinc-400 text-xs italic">Unassigned</span>
                                        @endif
                                    </flux:table.cell>

                                    {{-- Status --}}
                                    <flux:table.cell>
                                        @if ($isOnline)
                                            <flux:badge color="emerald" size="sm" class="font-semibold">
                                                <span class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                                                Online
                                            </flux:badge>
                                        @elseif ($hasDevice)
                                            <flux:badge color="zinc" size="sm" class="font-medium">
                                                Offline
                                            </flux:badge>
                                        @else
                                            <flux:badge color="amber" size="sm" class="font-medium">
                                                Not Connected
                                            </flux:badge>
                                        @endif
                                    </flux:cell>

                                    {{-- Actions --}}
                                    <flux:table.cell>
                                        <div class="flex items-center gap-1">
                                            @can('manage devices')
                                                <flux:button wire:navigate href="{{ route('devices.manage', $device->id) }}" icon="cog-6-tooth" size="sm" variant="subtle" tooltip="Manage Device" />
                                            @endcan
                                            <form action="{{ route('devices.set-time', $device) }}" method="POST" class="inline">
                                                @csrf
                                                <flux:button type="submit" variant="subtle" icon="clock" size="sm"
                                                    :disabled="! $device->zktecoDevice"
                                                    tooltip="Set this terminal's clock to the server time">Set Time</flux:button>
                                            </form>
                                            <flux:button wire:navigate href="{{ route('devices.edit', $device) }}" icon="pencil" size="sm" variant="subtle" tooltip="Edit Device" />
                                            <x-confirm-form
                                                :action="route('devices.destroy', $device)"
                                                title="Delete this device permanently?"
                                                message="The terminal is removed and stops syncing."
                                                confirm="Delete Device"
                                                tooltip="Delete Device"
                                                :fields="['device_id' => $device->id, 'institution_id' => $device->institution->id]" />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
