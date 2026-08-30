@php
    $totalCount = $devices->total();
    $activeCount = $devices->getCollection()->filter(fn($d) => $d->isActive())->count();
    $revokedCount = $devices->getCollection()->filter(fn($d) => !$d->isActive())->count();
@endphp

<x-layouts::app :title="__('Sync Devices')">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        Offline Sync Devices
                    </flux:heading>
                    <flux:badge size="sm" color="indigo" class="font-semibold">
                        {{ $totalCount }} Enrolled
                    </flux:badge>
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Manage paired desktop and mobile clients that securely synchronize attendance and fee data offline.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-2.5">
                @can('create syncdevice')
                    <flux:button href="{{ route('sync-devices.create') }}" icon="plus" variant="primary" class="shadow-sm hover:shadow-md transition-all">
                        Enroll Device
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success') && !session('device_token'))
            <div class="rounded-xl border border-emerald-200/50 bg-emerald-50/50 dark:bg-emerald-500/10 dark:border-emerald-500/20 p-4 flex items-start shadow-sm">
                <flux:icon icon="check-circle" class="size-5 text-emerald-500 mr-3 mt-0.5" />
                <div class="text-sm font-medium text-emerald-800 dark:text-emerald-400">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        {{-- One-Time Secret Pairing Token Card --}}
        @if (session('device_token'))
            <div class="rounded-2xl border-2 border-indigo-500/30 bg-gradient-to-br from-indigo-50/90 via-white to-purple-50/50 dark:from-indigo-950/40 dark:via-zinc-900 dark:to-purple-950/20 p-6 shadow-md" x-data="{ copied: false }">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-4">
                    <div class="flex items-start gap-3.5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm">
                            <flux:icon icon="key" class="size-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">
                                    One-Time Device Pairing Token
                                </flux:heading>
                                <flux:badge size="sm" color="amber" class="font-semibold">Action Required</flux:badge>
                            </div>
                            <flux:text class="text-xs text-zinc-600 dark:text-zinc-300 mt-1 max-w-2xl">
                                Paste this secret token into your offline client application when prompted. For security, it is displayed only once and cannot be retrieved again.
                            </flux:text>
                        </div>
                    </div>
                </div>

                <div class="relative flex items-center justify-between rounded-xl bg-zinc-900 px-4 py-3 border border-zinc-800 shadow-inner">
                    <code class="font-mono text-sm text-indigo-300 select-all break-all mr-4">
                        {{ session('device_token') }}
                    </code>
                    <button 
                        type="button"
                        @click="navigator.clipboard.writeText('{{ session('device_token') }}'); copied = true; setTimeout(() => copied = false, 2500)"
                        class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-500 transition-colors shadow-sm cursor-pointer"
                    >
                        <flux:icon icon="clipboard-document" class="size-3.5" x-show="!copied" />
                        <flux:icon icon="check" class="size-3.5" x-show="copied" />
                        <span x-text="copied ? 'Copied!' : 'Copy Token'">Copy Token</span>
                    </button>
                </div>
            </div>
        @endif

        {{-- Animated KPI Summary Grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-kpi-card title="Total Clients" icon="device-phone-mobile" color="indigo" :count="$totalCount" subtext="registered machines" />
            <x-kpi-card title="Active Sync Clients" icon="arrow-path" color="emerald" :count="$activeCount" countSuffix="/{{ $totalCount }}" subtext="authorized for offline sync" />
            <x-kpi-card title="Revoked History" icon="no-symbol" color="rose" :count="$revokedCount" subtext="cut-off access tokens" />
        </div>

        {{-- Enrolled Devices Table --}}
        <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Device & Client ID</flux:table.column>
                        <flux:table.column>Platform</flux:table.column>
                        <flux:table.column>Syncs As</flux:table.column>
                        <flux:table.column>Last Synchronization</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($devices as $device)
                            @php
                                $isActive = $device->isActive();
                                $platformIcon = match (strtolower($device->platform)) {
                                    'android' => 'device-phone-mobile',
                                    'ios' => 'device-phone-mobile',
                                    default => 'computer-desktop',
                                };
                            @endphp
                            <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                {{-- Device & Client ID --}}
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl font-semibold shadow-xs {{ $isActive ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' }}">
                                            <flux:icon :icon="$platformIcon" class="size-5" />
                                        </div>
                                        <div>
                                            <div class="font-semibold text-zinc-900 dark:text-white">
                                                {{ $device->name }}
                                            </div>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="text-[11px] text-zinc-400 dark:text-zinc-500">Client ID:</span>
                                                <span class="font-mono text-[11px] text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded border border-zinc-200/50 dark:border-zinc-700/50">
                                                    {{ $device->client_id }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                {{-- Platform --}}
                                <flux:table.cell>
                                    <flux:badge size="sm" color="zinc" class="font-medium">
                                        {{ ucfirst($device->platform) }}
                                    </flux:badge>
                                </flux:table.cell>

                                {{-- Syncs As User --}}
                                <flux:table.cell>
                                    <div class="flex items-center gap-2.5">
                                        @php
                                            $userName = $device->user?->name ?? 'Unassigned';
                                            $initials = collect(explode(' ', $userName))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                                        @endphp
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-semibold text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20">
                                            {{ $initials }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $userName }}</div>
                                            <div class="text-[11px] text-zinc-500">{{ $device->user?->getRoleNames()->first() ?? 'Staff' }}</div>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                {{-- Last Sync & IP --}}
                                <flux:table.cell>
                                    <div>
                                        <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                            {{ $device->last_synced_at?->diffForHumans() ?? 'Never synced' }}
                                        </div>
                                        @if ($device->last_seen_ip)
                                            <span class="font-mono text-[11px] text-zinc-400 dark:text-zinc-500">
                                                {{ $device->last_seen_ip }}
                                            </span>
                                        @endif
                                    </div>
                                </flux:table.cell>

                                {{-- Status --}}
                                <flux:table.cell>
                                    @if ($isActive)
                                        <flux:badge color="emerald" size="sm" class="font-semibold">
                                            <span class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                                            Active
                                        </flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm" class="font-medium">
                                            Revoked
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>

                                {{-- Actions --}}
                                <flux:table.cell>
                                    @can('delete syncdevice')
                                        @if ($isActive)
                                            <form action="{{ route('sync-devices.destroy', $device) }}" method="POST"
                                                class="inline"
                                                onsubmit="return confirm('Revoke {{ $device->name }}? It will immediately stop syncing and any unsynced offline data will be rejected.');">
                                                @csrf
                                                @method('DELETE')
                                                <flux:button type="submit" icon="no-symbol" size="sm" variant="subtle" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10" tooltip="Revoke Sync Access">
                                                    Revoke
                                                </flux:button>
                                            </form>
                                        @else
                                            <span class="text-xs text-zinc-400 italic">
                                                Revoked {{ $device->revoked_at?->diffForHumans() }}
                                            </span>
                                        @endif
                                    @endcan
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="6">
                                    <x-empty-state icon="signal" color="indigo" title="No Offline Devices Enrolled" description="Enroll an offline laptop or mobile terminal to allow authorized staff to record attendance and fees without internet.">
                                        @can('create syncdevice')
                                            <flux:button href="{{ route('sync-devices.create') }}" icon="plus" variant="primary">
                                                Enroll First Device
                                            </flux:button>
                                        @endcan
                                    </x-empty-state>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            @if ($devices->hasPages())
                <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                    {{ $devices->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
