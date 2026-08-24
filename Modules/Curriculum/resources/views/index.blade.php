@php
    $totalCurricula = $curricula->count();
    $activeCount = $curricula->filter(fn($c) => $c->status === 'active')->count();
    $dismissedCount = $curricula->filter(fn($c) => $c->status !== 'active')->count();
@endphp

<x-layouts::app :title="__(config('curriculum.name'))">
    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">
                        Academic Curricula
                    </flux:heading>
                    <flux:badge size="sm" color="indigo" class="font-semibold">
                        {{ $totalCurricula }} {{ Str::plural('Framework', $totalCurricula) }}
                    </flux:badge>
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Manage educational curricula, national standards, and instructional programs.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                @can('create curriculum')
                    <flux:button href="{{ route('curriculum.create') }}" icon="plus" variant="primary" class="shadow-sm hover:shadow-md transition-all">
                        Add Curriculum
                    </flux:button>
                @endcan
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200/50 bg-emerald-50/50 dark:bg-emerald-500/10 dark:border-emerald-500/20 p-4 flex items-start shadow-sm">
                <flux:icon icon="check-circle" class="size-5 text-emerald-500 mr-3 mt-0.5" />
                <div class="text-sm font-medium text-emerald-800 dark:text-emerald-400">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        {{-- Animated KPI Metrics Grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-kpi-card 
                title="Total Curricula" 
                icon="book-open" 
                color="indigo" 
                :count="$totalCurricula" 
                subtext="registered frameworks" 
                :href="route('curriculum.index')" 
            />
            <x-kpi-card 
                title="Active Programs" 
                icon="check-circle" 
                color="emerald" 
                :count="$activeCount" 
                countSuffix="/{{ $totalCurricula }}" 
                subtext="in active instruction" 
            />
            <x-kpi-card 
                title="Archived / Inactive" 
                icon="archive-box" 
                color="zinc" 
                :count="$dismissedCount" 
                subtext="dismissed curricula" 
            />
        </div>

        {{-- Table Card --}}
        <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Curriculum Framework</flux:table.column>
                        @if (isAdmin())
                            <flux:table.column>Campus / Institution</flux:table.column>
                        @endif
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column align="end">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($curricula as $curriculum)
                            @php
                                $isActive = $curriculum->status === 'active';
                            @endphp
                            <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                {{-- Curriculum Framework Name --}}
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <div class="size-9 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0 transition-transform group-hover:scale-105">
                                            <flux:icon icon="book-open" class="size-4.5" />
                                        </div>
                                        <div>
                                            <a href="{{ route('curriculum.show', $curriculum->id) }}" class="font-semibold text-sm text-zinc-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                                {{ $curriculum->name }}
                                            </a>
                                            <div class="text-[11px] text-zinc-400 mt-0.5">
                                                Added {{ $curriculum->created_at?->format('M d, Y') ?? '—' }}
                                            </div>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                {{-- Campus / Institution (Admin only) --}}
                                @if (isAdmin())
                                    <flux:table.cell>
                                        <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-300 text-xs">
                                            <flux:icon icon="building-library" class="size-3.5 text-zinc-400 shrink-0" />
                                            <span>{{ $curriculum->institution?->name ?? '—' }}</span>
                                        </div>
                                    </flux:table.cell>
                                @endif

                                {{-- Status --}}
                                <flux:table.cell>
                                    @if ($isActive)
                                        <flux:badge color="emerald" size="sm" class="font-semibold">
                                            <span class="size-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                                            Active
                                        </flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm" class="font-medium capitalize">
                                            {{ $curriculum->status ?? 'Dismissed' }}
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>

                                {{-- Actions --}}
                                <flux:table.cell align="end">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button href="{{ route('curriculum.show', $curriculum->id) }}" icon="eye" size="sm" variant="subtle" tooltip="View Details" />
                                        
                                        @can('edit curriculum')
                                            <flux:button href="{{ route('curriculum.edit', $curriculum->id) }}" icon="pencil" size="sm" variant="subtle" tooltip="Edit Curriculum" />
                                        @endcan
                                        
                                        @can('delete curriculum')
                                            <form action="{{ route('curriculum.destroy', $curriculum->id) }}" method="POST" class="inline" onsubmit="return confirm('Remove {{ $curriculum->name }} permanently?');">
                                                @csrf
                                                @method('DELETE')
                                                <flux:button type="submit" icon="trash" size="sm" variant="subtle" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10" tooltip="Delete Curriculum" />
                                            </form>
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="{{ isAdmin() ? 4 : 3 }}">
                                    <x-empty-state icon="book-open" color="indigo" title="No Curricula Configured" description="Register your first academic curriculum (e.g. CBC, 8-4-4, or IGCSE) to structure classes and grading standards.">
                                        @can('create curriculum')
                                            <flux:button href="{{ route('curriculum.create') }}" icon="plus" variant="primary">
                                                Add First Curriculum
                                            </flux:button>
                                        @endcan
                                    </x-empty-state>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    </div>
</x-layouts::app>
