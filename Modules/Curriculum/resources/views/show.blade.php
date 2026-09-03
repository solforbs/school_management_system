<x-layouts::app :title="$curriculum->name">
    <div class="flex flex-col gap-6 w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                    <a href="{{ route('dashboard') }}" wire:navigate.hover
                        class="hover:text-zinc-800 dark:hover:text-zinc-200">Dashboard</a>
                    <span>/</span>
                    <a href="{{ route('curriculum.index') }}" wire:navigate.hover
                        class="hover:text-zinc-800 dark:hover:text-zinc-200">Curricula</a>
                    <span>/</span>
                    <span class="text-zinc-800 dark:text-zinc-200 font-medium">{{ $curriculum->name }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        {{ $curriculum->name }}
                    </flux:heading>
                    <flux:badge :color="$curriculum->status === 'active' ? 'emerald' : 'zinc'" size="sm"
                        class="font-semibold capitalize">
                        @if ($curriculum->status === 'active')
                            <span class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                        @endif
                        {{ $curriculum->status }}
                    </flux:badge>
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1 flex items-center gap-1.5">
                    <flux:icon icon="building-library" class="size-4 text-zinc-400 shrink-0" />
                    {{ $curriculum->institution?->name ?? 'All Campuses' }}
                </flux:text>
            </div>

            <div class="flex items-center gap-2.5">
                <flux:button href="{{ route('curriculum.index') }}" wire:navigate.hover icon="arrow-left"
                    variant="ghost" class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    Back to Curricula
                </flux:button>

                @can('edit curriculum')
                    <flux:button href="{{ route('curriculum.edit', $curriculum->id) }}" wire:navigate.hover icon="pencil"
                        variant="primary" class="shadow-sm">
                        Edit Curriculum
                    </flux:button>
                @endcan

                @can('delete curriculum')
                    <form action="{{ route('curriculum.destroy', $curriculum->id) }}" method="POST"
                        onsubmit="return confirm('Delete this curriculum?');" class="inline">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" icon="trash" variant="subtle"
                            class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10">
                            Delete
                        </flux:button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div
                class="rounded-xl border border-emerald-200/50 bg-emerald-50/50 dark:bg-emerald-500/10 dark:border-emerald-500/20 p-4 flex items-start shadow-sm">
                <flux:icon icon="check-circle" class="size-5 text-emerald-500 mr-3 mt-0.5" />
                <div class="text-sm font-medium text-emerald-800 dark:text-emerald-400">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        {{-- Overview Metric Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-kpi-card title="Instructional Status" icon="check-circle" :color="$curriculum->status === 'active' ? 'emerald' : 'zinc'" :count="ucfirst($curriculum->status)"
                subtext="operational status" />
            <x-kpi-card title="Associated Campus" icon="building-library" color="indigo" :count="$curriculum->institution?->name ?? 'Default Campus'"
                subtext="primary institutional scope" />
            <x-kpi-card title="Registered On" icon="calendar" color="violet" :count="$curriculum->created_at?->format('M d, Y') ?? '—'"
                subtext="system creation date" />
        </div>

        {{-- Associated Institution Card --}}
        <div
            class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/20">
                <flux:heading size="md" class="font-semibold text-zinc-900 dark:text-white">Institution Scope
                </flux:heading>
                <flux:text class="text-xs text-zinc-500">The institution authorized to deliver this curriculum
                    framework.</flux:text>
            </div>

            @if (!$curriculum->institution)
                <div class="p-8">
                    <x-empty-state icon="building-library" color="zinc" title="No Specific Campus Attached"
                        description="This curriculum is currently available across all institutional divisions.">
                    </x-empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <flux:table class="p-2">
                        <flux:table.columns>
                            <flux:table.column>Campus Name</flux:table.column>
                            <flux:table.column>Institution Type</flux:table.column>
                            <flux:table.column>Operating Status</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            <flux:table.row>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="size-8 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0">
                                            <flux:icon icon="building-library" class="size-4" />
                                        </div>
                                        <span
                                            class="font-semibold text-sm text-zinc-900 dark:text-white">{{ $curriculum->institution->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-xs text-zinc-600 dark:text-zinc-400">
                                    {{ $curriculum->institution->type ?? 'General' }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$curriculum->institution->is_active ? 'emerald' : 'zinc'"
                                        size="sm" class="font-medium">
                                        {{ ucfirst($curriculum->institution->status ?? 'Active') }}
                                    </flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
