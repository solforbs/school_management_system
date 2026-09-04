<?php

use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Curriculum\Models\Curriculum;

new #[Title('Curricula & Frameworks')] class extends Component {
    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view curriculum'), 403);
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('delete curriculum'), 403);
        $curriculum = Curriculum::when(!isAdmin(), fn($q) => $q->where('institution_id', currentInstitutionId()))->findOrFail($id);
        $curriculum->delete();
    }

    #[Computed]
    public function curricula(): Collection
    {
        return Curriculum::with('institution')
            ->when(!isAdmin(), fn($q) => $q->where('institution_id', currentInstitutionId()))
            ->when(
                $this->search !== '',
                fn($q) => $q->where(function ($sq) {
                    $sq->where('name', 'like', '%' . $this->search . '%')->orWhere('system', 'like', '%' . $this->search . '%');
                }),
            )
            ->orderBy('name')
            ->get();
    }
}; ?>

@php
    $curricula = $this->curricula;
    $totalFrameworks = $curricula->count();
    $activeInstitutions = $curricula->pluck('institution_id')->unique()->count();
    $canManageClasses = Route::has('classes.index') && auth()->user()->can('view classes');
@endphp

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Curricula
                    &amp; Frameworks</flux:heading>
                <flux:badge size="sm" color="indigo" class="font-semibold">
                    {{ $totalFrameworks }} Frameworks
                </flux:badge>
            </div>
            <flux:text class="text-zinc-500 text-sm mt-1">
                Manage educational systems (CBC, 8-4-4, Cambridge, IB), grade structures, and academic frameworks.
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            @can('create curriculum')
                <flux:button href="{{ route('curriculum.create') }}" wire:navigate.hover icon="plus" variant="primary"
                    class="shadow-sm hover:shadow-md transition-all">
                    Add Curriculum
                </flux:button>
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

    {{-- Animated KPI Metrics Grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi-card title="Active Frameworks" icon="book-open" color="indigo" :count="$totalFrameworks"
            subtext="curricula registered" :href="route('curriculum.index')" />
        <x-kpi-card title="Institutions Enrolled" icon="building-library" color="sky" :count="$activeInstitutions"
            subtext="campuses adhering" :href="isAdmin() ? route('institution.index') : null" />
        <x-kpi-card title="Class Streams" icon="academic-cap" color="emerald" :count="$totalFrameworks"
            subtext="structured pathways" :href="$canManageClasses ? route('classes.index') : null" />
    </div>

    {{-- Curricula Table Card --}}
    <div
        class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
        <div class="overflow-x-auto">
            <flux:table class="p-2">
                <flux:table.columns>
                    <x-sortable-column column="name">Curriculum Framework</x-sortable-column>
                    @if (isAdmin())
                        <flux:table.column>Institution</flux:table.column>
                    @endif
                    <flux:table.column>Description &amp; System Scope</flux:table.column>
                    <flux:table.column align="end">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($curricula as $curriculum)
                        <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                            {{-- Name & Code --}}
                            <flux:table.cell>
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="size-8 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400 ring-1 ring-inset ring-indigo-500/20 flex items-center justify-center font-bold text-xs shrink-0 transition-transform group-hover:scale-105">
                                        <flux:icon icon="book-open" class="size-4" />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('curriculum.show', $curriculum->id) }}" wire:navigate.hover
                                            class="font-semibold text-sm text-zinc-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                            {{ $curriculum->name }}
                                        </a>
                                        <flux:badge size="sm" color="indigo"
                                            class="font-medium text-[11px] uppercase">
                                            {{ $curriculum->systemLabel() }}
                                        </flux:badge>
                                    </div>
                                </div>
                            </flux:table.cell>

                            {{-- Institution / Campus (Admin only) --}}
                            @if (isAdmin())
                                <flux:table.cell>
                                    <div class="flex items-center gap-1.5 text-zinc-500 text-xs truncate max-w-[150px]">
                                        <flux:icon icon="building-library" class="size-3.5 text-zinc-400 shrink-0" />
                                        <span>{{ $curriculum->institution?->name ?? '—' }}</span>
                                    </div>
                                </flux:table.cell>
                            @endif

                            {{-- Description / Grading Scheme --}}
                            <flux:table.cell>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-1">
                                    {{ $curriculum->gradingLabel() }}
                                </span>
                            </flux:table.cell>

                            {{-- Actions --}}
                            <flux:table.cell align="end">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button href="{{ route('curriculum.show', $curriculum->id) }}"
                                        wire:navigate.hover icon="eye" size="sm" variant="subtle"
                                        tooltip="View Details" />

                                    @can('edit curriculum')
                                        <flux:button href="{{ route('curriculum.edit', $curriculum->id) }}"
                                            wire:navigate.hover icon="pencil" size="sm" variant="subtle"
                                            tooltip="Edit Curriculum" />
                                    @endcan

                                    @can('delete curriculum')
                                        <x-confirm-form
                                            :action="route('curriculum.destroy', $curriculum->id)"
                                            title="Remove {{ $curriculum->name }}?"
                                            message="Classes on this curriculum fall back to the school-wide grading scale."
                                            confirm="Remove Curriculum"
                                            tooltip="Delete Curriculum" />
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="{{ isAdmin() ? 4 : 3 }}">
                                <x-empty-state icon="book-open" color="indigo" title="No Curricula Configured"
                                    description="Register your first academic curriculum (e.g. CBC, 8-4-4, or IGCSE) to structure classes and grading standards.">
                                    @can('create curriculum')
                                        <flux:button href="{{ route('curriculum.create') }}" wire:navigate.hover
                                            icon="plus" variant="primary">
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
