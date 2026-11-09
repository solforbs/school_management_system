<?php

use App\Models\Plan;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Institution\Actions\DeleteInstitution;
use Modules\Institution\Models\Institution;

new #[Title('Institutions')] class extends Component
{
    /** @var string[] */
    public const SORTABLE = ['name', 'code', 'phone', 'email', 'created_at'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $sort = 'created_at';

    #[Url]
    public string $direction = 'desc';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view institution'), 403);
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sort = $column;
        $this->direction = 'asc';
    }

    public function approve(int $id): void
    {
        abort_unless(isAdmin(), 403);

        $institution = Institution::findOrFail($id);

        $institution->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by_id' => auth()->id(),
        ]);

        unset($this->institutions);

        Flux::toast(text: 'Institution "'.$institution->name.'" has been approved.', variant: 'success');
    }

    /** The institution the disapprove dialog is confirming. */
    public ?int $disapprovingId = null;

    /**
     * Revoke approval - locks the Director back out of the dashboard
     * (EnsureInstitutionApproved) until an Admin approves it again.
     */
    public function disapprove(?int $id = null): void
    {
        abort_unless(isAdmin(), 403);

        $id ??= $this->disapprovingId;
        abort_unless($id, 404);

        $this->disapprovingId = null;
        Flux::modal('confirm-disapprove-institution')->close();

        $institution = Institution::findOrFail($id);

        $institution->update([
            'is_approved' => false,
            'approved_at' => null,
            'approved_by_id' => null,
        ]);

        unset($this->institutions);

        Flux::toast(text: 'Institution "'.$institution->name.'" is no longer approved.', variant: 'success');
    }

    public function choose(int $id): void
    {
        $institution = Institution::findOrFail($id);

        abort_unless($institution->user_id === auth()->id(), 403);

        auth()->user()->update(['active_institution_id' => $institution->id]);

        session()->flash('success', 'Now managing "'.$institution->name.'".');

        $this->redirectRoute('dashboard', navigate: true);
    }

    /** The institution the delete dialog is confirming. */
    public ?int $deletingId = null;

    /**
     * The institution staged for deletion, with the counts the dialog
     * spells out before the admin commits to a permanent purge.
     */
    #[Computed]
    public function deletingInstitution(): ?Institution
    {
        return $this->deletingId
            ? Institution::withCount(['studentDetails', 'teacherDetails'])->find($this->deletingId)
            : null;
    }

    /**
     * Permanently delete a school and everything under it - records across
     * every module, uploaded files, and the logins that existed only for
     * this school. See Modules\Institution\Actions\DeleteInstitution.
     */
    public function delete(?int $id = null): void
    {
        abort_unless(isAdmin() && auth()->user()->can('delete institution'), 403);

        $id ??= $this->deletingId;
        abort_unless($id, 404);

        $institution = Institution::findOrFail($id);

        try {
            $summary = app(DeleteInstitution::class)->handle($institution);
        } catch (\Throwable $e) {
            report($e);

            $this->deletingId = null;
            Flux::modal('confirm-delete-institution')->close();
            Flux::toast(text: 'Could not delete "'.$institution->name.'". Nothing was removed.', variant: 'danger');

            return;
        }

        $this->deletingId = null;
        Flux::modal('confirm-delete-institution')->close();
        unset($this->institutions);

        $tail = $summary['users_deleted'] > 0
            ? ' '.$summary['users_deleted'].' user account(s) removed with it.'
            : '';

        Flux::toast(text: 'Institution "'.$summary['institution'].'" and all its data were permanently deleted.'.$tail, variant: 'success');
    }

    #[Computed]
    public function institutions(): Collection
    {
        $sort = in_array($this->sort, self::SORTABLE, true) ? $this->sort : 'created_at';
        $direction = $this->direction === 'asc' ? 'asc' : 'desc';

        $query = Institution::withCount(['studentDetails', 'teacherDetails']);

        if (! isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return $query
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';

                $q->where(fn ($q2) => $q2->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderBy($sort, $direction)
            ->get();
    }

    #[Computed]
    public function plans(): Collection
    {
        return Plan::where('is_active', true)->orderBy('name')->get();
    }
}; ?>

@php
    $institution = $this->institutions;
    $plans = $this->plans;
    $totalCampuses = count($institution);
    $activeCampus = $institution->firstWhere('id', auth()->user()->active_institution_id);
    
    $totalStudentsCombined = 0;
    $totalTeachersCombined = 0;
    foreach ($institution as $inst) {
        $totalStudentsCombined += ($inst->student_details_count ?? $inst->studentDetails()->count());
        $totalTeachersCombined += ($inst->teacher_details_count ?? $inst->teacherDetails()->count());
    }
@endphp

    <div class="flex flex-col gap-8 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" x-data="{
        search: '',
        statusFilter: 'all',
        viewMode: 'grid',
        matches(inst) {
            const matchesSearch = !this.search || 
                inst.name.toLowerCase().includes(this.search.toLowerCase()) || 
                inst.code.toLowerCase().includes(this.search.toLowerCase()) ||
                inst.email.toLowerCase().includes(this.search.toLowerCase()) ||
                inst.city.toLowerCase().includes(this.search.toLowerCase());
            const matchesStatus = this.statusFilter === 'all' || 
                (this.statusFilter === 'active' && inst.is_current) ||
                (this.statusFilter === 'approved' && inst.is_approved) ||
                (this.statusFilter === 'pending' && !inst.is_approved);
            return matchesSearch && matchesStatus;
        }
    }">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Institutions &amp; Campuses</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Switch between campuses, manage institutional profiles, and monitor cross-school operations.
                </flux:text>
            </div>
            
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <x-export-buttons base-route="institution.export" module="institution" :params="[]" />
                @hasrole('Director')
                    <flux:button href="{{ route('institution.create') }}" wire:navigate.hover icon="plus" variant="primary" class="font-semibold shadow-sm px-3.5 sm:px-4 text-xs sm:text-sm min-h-[38px]">
                        Add Institution
                    </flux:button>
                @endhasrole
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <flux:icon icon="check-circle" class="size-5 text-emerald-600 shrink-0" />
                    <span class="text-sm font-semibold text-emerald-900">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Top Multi-Campus KPI Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
            {{-- Card 1: Registered Campuses --}}
            <div class="bg-white dark:bg-zinc-900 rounded-2xl p-4 sm:p-5 border border-zinc-200/80 dark:border-zinc-800 shadow-sm hover:border-zinc-300 transition duration-150">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Registered Campuses</span>
                    <div class="size-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-700 dark:text-zinc-300">
                        <flux:icon icon="building-office-2" class="size-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white tracking-tight">
                    <span x-data="{ count: 0, target: {{ $totalCampuses }}, init() { let s=null, d=800; let step=(t)=>{ if(!s)s=t; let p=Math.min((t-s)/d,1); this.count=Math.floor((1-Math.pow(1-p,3))*this.target); if(p<1) requestAnimationFrame(step); else this.count=this.target; }; requestAnimationFrame(step); } }" x-text="count">{{ $totalCampuses }}</span> Campuses
                </div>
                <div class="text-xs text-zinc-500 mt-2 font-medium">
                    Full administrative ownership
                </div>
            </div>

            {{-- Card 2: Active Workspace --}}
            <a href="{{ route('dashboard') }}" wire:navigate.hover class="bg-white dark:bg-zinc-900 rounded-2xl p-4 sm:p-5 border border-zinc-200/80 dark:border-zinc-800 shadow-sm hover:border-emerald-300 hover:shadow-md transition duration-150 block group">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Active Workspace</span>
                    <div class="size-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:scale-105 transition-transform">
                        <flux:icon icon="check-badge" class="size-4" />
                    </div>
                </div>
                <div class="text-xl font-black text-zinc-900 dark:text-white tracking-tight truncate group-hover:text-emerald-700 dark:group-hover:text-emerald-400 transition">
                    {{ $activeCampus?->name ?? 'None Selected' }}
                </div>
                <div class="text-xs text-emerald-700 dark:text-emerald-400 mt-2 flex items-center gap-1.5 font-semibold">
                    <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Currently Live &bull; Go to Dashboard &rarr;</span>
                </div>
            </a>

            {{-- Card 3: Total Combined Learners --}}
            <a href="{{ route('student.index') }}" wire:navigate.hover class="bg-white dark:bg-zinc-900 rounded-2xl p-4 sm:p-5 border border-zinc-200/80 dark:border-zinc-800 shadow-sm hover:border-zinc-300 hover:shadow-md transition duration-150 block group sm:col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Total Enrollment</span>
                    <div class="size-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-700 dark:text-zinc-300 group-hover:scale-105 transition-transform">
                        <flux:icon icon="academic-cap" class="size-4" />
                    </div>
                </div>
                <div class="text-2xl font-black text-zinc-900 dark:text-white tracking-tight group-hover:text-blue-600 transition">
                    <span x-data="animatedCounter({{ $totalStudentsCombined }})" x-text="displayCount">{{ number_format($totalStudentsCombined) }}</span> Students
                </div>
                <div class="text-xs text-zinc-500 mt-2 font-medium flex items-center justify-between">
                    <span><strong class="text-zinc-800 dark:text-zinc-200 font-semibold" x-data="animatedCounter({{ $totalTeachersCombined }})" x-text="displayCount">{{ $totalTeachersCombined }}</strong> Faculty Members</span>
                    <span class="text-blue-600 font-semibold group-hover:underline">View Directory &rarr;</span>
                </div>
            </a>
        </div>

        {{-- Filter, Search & View Switcher Bar --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl p-3.5 sm:p-4 border border-zinc-200/80 dark:border-zinc-800 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 sm:gap-4">
            {{-- Search Bar --}}
            <div class="relative w-full md:w-80">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-400">
                    <flux:icon icon="magnifying-glass" class="size-4" />
                </div>
                <input type="text" x-model="search" placeholder="Search campus name, code, email, city..." style="width: 100%; padding-left: 2.75rem !important; padding-right: 1rem; padding-top: 0.5rem; padding-bottom: 0.5rem;" class="bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-xs placeholder-zinc-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
            </div>

            {{-- Status Filter Chips & View Mode --}}
            <div class="flex flex-wrap items-center justify-between sm:justify-end w-full md:w-auto gap-2.5 sm:gap-3">
                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                    <button type="button" @click="statusFilter = 'all'"
                        :class="statusFilter === 'all' ? 'bg-zinc-900 text-white shadow-sm font-semibold' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200'"
                        class="px-3 sm:px-3.5 py-1.5 rounded-xl text-xs font-medium transition duration-150 min-h-[34px]">
                        All ({{ $totalCampuses }})
                    </button>
                    <button type="button" @click="statusFilter = 'approved'"
                        :class="statusFilter === 'approved' ? 'bg-emerald-600 text-white shadow-sm font-semibold' : 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100'"
                        class="px-3 sm:px-3.5 py-1.5 rounded-xl text-xs font-medium transition duration-150 min-h-[34px]">
                        Approved
                    </button>
                </div>

                {{-- Grid / List View Toggle --}}
                <div class="flex items-center p-1 bg-zinc-100 dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shrink-0">
                    <button type="button" @click="viewMode = 'grid'" 
                        :class="viewMode === 'grid' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm font-bold' : 'text-zinc-500 hover:text-zinc-900'"
                        class="p-1.5 rounded-lg transition min-w-[32px] min-h-[32px] flex items-center justify-center" title="Grid View" aria-label="Grid View">
                        <flux:icon icon="squares-2x2" class="size-4" />
                    </button>
                    <button type="button" @click="viewMode = 'list'" 
                        :class="viewMode === 'list' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm font-bold' : 'text-zinc-500 hover:text-zinc-900'"
                        class="p-1.5 rounded-lg transition min-w-[32px] min-h-[32px] flex items-center justify-center" title="List View" aria-label="List View">
                        <flux:icon icon="list-bullet" class="size-4" />
                    </button>
                </div>
            </div>
        </div>

        {{-- 1. Executive Campus Grid View (Default) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" x-show="viewMode === 'grid'">
            @forelse ($institution as $institute)
                @php
                    $isCurrent = ((int) auth()->user()->active_institution_id === (int) $institute->id);
                    $initial = strtoupper(substr($institute->name, 0, 1));
                    $studentCount = $institute->student_details_count ?? $institute->studentDetails()->count() ?? 0;
                    $teacherCount = $institute->teacher_details_count ?? $institute->teacherDetails()->count() ?? 0;
                    $classCount = $institute->classes_count ?? $institute->classes()->count() ?? 0;
                @endphp
                <div class="bg-white dark:bg-zinc-900 rounded-2xl sm:rounded-3xl p-4 sm:p-6 border transition-all duration-150 flex flex-col justify-between
                    {{ $isCurrent ? 'border-emerald-300 ring-2 ring-emerald-500/20 shadow-sm' : 'border-zinc-200/80 dark:border-zinc-800 hover:border-zinc-300 shadow-sm' }}"
                    x-show="matches({
                        name: '{{ addslashes($institute->name) }}',
                        code: '{{ addslashes($institute->code ?? '') }}',
                        email: '{{ addslashes($institute->email ?? '') }}',
                        city: '{{ addslashes($institute->city ?? '') }}',
                        is_current: {{ $isCurrent ? 'true' : 'false' }},
                        is_approved: {{ $institute->is_approved ? 'true' : 'false' }}
                    })">

                    <div>
                        {{-- Card Header: Logo, Title, Code & Badge --}}
                        <div class="flex items-start justify-between gap-3 sm:gap-4 mb-4">
                            <div class="flex items-center gap-3 sm:gap-3.5 min-w-0">
                                @if($institute->logo)
                                    <img src="{{ Storage::url($institute->logo) }}" class="size-10 sm:size-12 rounded-xl sm:rounded-2xl border border-zinc-200 bg-white object-contain p-1 shadow-sm shrink-0">
                                @else
                                    <div class="size-10 sm:size-12 rounded-xl sm:rounded-2xl bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 font-black text-base sm:text-lg flex items-center justify-center border border-zinc-200 dark:border-zinc-700 shadow-sm shrink-0">
                                        {{ $initial }}
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <h3 class="text-sm sm:text-base font-bold text-zinc-900 dark:text-white truncate">
                                        <span translate="no">{{ $institute->name }}</span>
                                    </h3>
                                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mt-0.5">
                                        <span class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 font-mono font-semibold text-[10px] sm:text-[11px] uppercase border border-zinc-200 dark:border-zinc-700">
                                            {{ $institute->code ?? 'CAMPUS' }}
                                        </span>
                                        <span class="text-xs text-zinc-400">&bull;</span>
                                        <span class="text-xs text-zinc-500 font-medium truncate">{{ $institute->city ?? $institute->county ?? 'Kenya' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Active Workspace Badge --}}
                            <div class="shrink-0">
                                @if ($isCurrent)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/80">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                                        Standby
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Campus Stats Pill Strip (Interactive Deep Links if Active) --}}
                        <div class="grid grid-cols-3 gap-2 sm:gap-2.5 my-3.5 sm:my-4">
                            @if ($isCurrent)
                                <a href="{{ route('student.index') }}" wire:navigate.hover class="p-2 sm:p-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 text-center hover:bg-zinc-100 transition group/tile" title="View Student Directory">
                                    <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-zinc-400 block group-hover/tile:text-blue-600 transition truncate">Students &rarr;</span>
                                    <span class="text-xs sm:text-sm font-black text-zinc-900 dark:text-white mt-0.5 block" x-data="animatedCounter({{ $studentCount }})" x-text="displayCount">{{ number_format($studentCount) }}</span>
                                </a>
                                <a href="{{ route('teacher.index') }}" wire:navigate.hover class="p-2 sm:p-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 text-center hover:bg-zinc-100 transition group/tile" title="View Faculty List">
                                    <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-zinc-400 block group-hover/tile:text-purple-600 transition truncate">Teachers &rarr;</span>
                                    <span class="text-xs sm:text-sm font-black text-zinc-900 dark:text-white mt-0.5 block" x-data="animatedCounter({{ $teacherCount }})" x-text="displayCount">{{ number_format($teacherCount) }}</span>
                                </a>
                                <a href="{{ route('classes.index') }}" wire:navigate.hover class="p-2 sm:p-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 text-center hover:bg-zinc-100 transition group/tile" title="View Class Streams">
                                    <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-zinc-400 block group-hover/tile:text-emerald-600 transition truncate">Streams &rarr;</span>
                                    <span class="text-xs sm:text-sm font-black text-zinc-900 dark:text-white mt-0.5 block" x-data="animatedCounter({{ $classCount }})" x-text="displayCount">{{ number_format($classCount) }}</span>
                                </a>
                            @else
                                <div class="p-2 sm:p-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 text-center">
                                    <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-zinc-400 block truncate">Students</span>
                                    <span class="text-xs sm:text-sm font-black text-zinc-900 dark:text-white mt-0.5 block" x-data="animatedCounter({{ $studentCount }})" x-text="displayCount">{{ number_format($studentCount) }}</span>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 text-center">
                                    <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-zinc-400 block truncate">Teachers</span>
                                    <span class="text-xs sm:text-sm font-black text-zinc-900 dark:text-white mt-0.5 block" x-data="animatedCounter({{ $teacherCount }})" x-text="displayCount">{{ number_format($teacherCount) }}</span>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-xl bg-zinc-50 dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 text-center">
                                    <span class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-zinc-400 block truncate">Streams</span>
                                    <span class="text-xs sm:text-sm font-black text-zinc-900 dark:text-white mt-0.5 block" x-data="animatedCounter({{ $classCount }})" x-text="displayCount">{{ number_format($classCount) }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Contact Details --}}
                        <div class="space-y-1 text-xs text-zinc-500 py-2 border-t border-zinc-100 dark:border-zinc-800">
                            @if($institute->email)
                                <div class="flex items-center gap-2">
                                    <flux:icon icon="envelope" class="size-3.5 text-zinc-400 shrink-0" />
                                    <a href="mailto:{{ $institute->email }}" class="text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 truncate transition font-medium"><span translate="no">{{ $institute->email }}</span></a>
                                </div>
                            @endif
                            @if($institute->phone)
                                <div class="flex items-center gap-2">
                                    <flux:icon icon="phone" class="size-3.5 text-zinc-400 shrink-0" />
                                    <span class="text-zinc-600 dark:text-zinc-400 font-medium truncate"><span translate="no">{{ $institute->phone }}</span></span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Action Footer --}}
                    <div class="pt-3 mt-1 border-t border-zinc-100 dark:border-zinc-800 flex flex-wrap items-center justify-between gap-2.5">
                        <div class="flex items-center">
                            @if ($isCurrent)
                                <a href="{{ route('dashboard') }}" wire:navigate.hover class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold shadow-sm transition min-h-[36px]">
                                    <flux:icon icon="arrow-top-right-on-square" class="size-3.5" />
                                    <span>Open Dashboard</span>
                                </a>
                            @elseif (institutionOwner($institute->owner->id))
                                <form action="{{ route('institution.choose', $institute->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 sm:px-3.5 py-1.5 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 text-xs font-bold transition min-h-[36px]">
                                        <flux:icon icon="arrow-path" class="size-3.5" />
                                        <span>Switch Campus</span>
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5 ml-auto">
                            <flux:button href="{{ route('institution.show', $institute->id) }}" wire:navigate.hover size="sm" icon="eye" variant="subtle" tooltip="View Profile" class="min-h-[36px] min-w-[36px]" />
                            @if (institutionOwner($institute->owner->id))
                                <flux:button href="{{ route('institution.edit', $institute->id) }}" wire:navigate.hover size="sm" icon="pencil-square" variant="subtle" tooltip="Settings" class="min-h-[36px] min-w-[36px]" />
                            @endif
                        </div>
                    </div>

                </div>
            @empty
                <div class="col-span-2 py-16 text-center bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800">
                    <flux:icon icon="building-office-2" class="size-12 mx-auto text-zinc-300 mb-3" />
                    <h4 class="text-base font-bold text-zinc-900 dark:text-white">No Institutions Found</h4>
                    <p class="text-xs text-zinc-500 mt-1 mb-4">You have not registered any school campuses yet.</p>
                    @hasrole('Director')
                        <flux:button href="{{ route('institution.create') }}" wire:navigate.hover variant="primary" size="sm">
                            Add Institution
                        </flux:button>
                    @endhasrole
                </div>
            @endforelse
        </div>

        {{-- 2. Clean List Table View (Alternative) --}}
        <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-sm border border-zinc-200/80 dark:border-zinc-800 overflow-hidden flex flex-col" x-show="viewMode === 'list'">
            <div class="responsive-table-container">
                <table class="w-full text-left text-sm min-w-[720px]">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-xs font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3.5 px-4 sm:px-5 whitespace-nowrap">Institution</th>
                            <th class="py-3.5 px-4 sm:px-5 whitespace-nowrap">Campus Contact</th>
                            <th class="py-3.5 px-4 sm:px-5 whitespace-nowrap">Enrollment</th>
                            <th class="py-3.5 px-4 sm:px-5 whitespace-nowrap">Status</th>
                            <th class="py-3.5 px-4 sm:px-5 whitespace-nowrap">Workspace</th>
                            <th class="py-3.5 px-4 sm:px-5 text-right whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($institution as $institute)
                            @php
                                $isCurrent = ((int) auth()->user()->active_institution_id === (int) $institute->id);
                                $studentCount = $institute->student_details_count ?? $institute->studentDetails()->count() ?? 0;
                            @endphp
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition-colors">
                                <td class="py-4 px-4 sm:px-5 min-w-[180px]">
                                    <div class="flex items-center gap-3">
                                        @if($institute->logo)
                                            <img src="{{ Storage::url($institute->logo) }}" class="size-9 rounded-xl border border-zinc-200 bg-white object-contain p-0.5 shrink-0">
                                        @else
                                            <div class="size-9 rounded-xl bg-zinc-100 dark:bg-zinc-800 font-bold text-xs flex items-center justify-center text-zinc-700 dark:text-zinc-300 shrink-0">
                                                <span translate="no">{{ substr($institute->name, 0, 1) }}</span>
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="font-bold text-zinc-900 dark:text-white truncate"><span translate="no">{{ $institute->name }}</span></div>
                                            <div class="text-xs text-zinc-400 font-mono">{{ $institute->code ?? '—' }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-4 px-4 sm:px-5 text-xs text-zinc-600 dark:text-zinc-400 min-w-[160px]">
                                    <div class="truncate"><span translate="no">{{ $institute->email ?? '—' }}</span></div>
                                    <div class="text-zinc-400 mt-0.5 truncate"><span translate="no">{{ $institute->phone ?? '' }}</span></div>
                                </td>

                                <td class="py-4 px-4 sm:px-5 text-xs font-semibold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                    <span x-data="animatedCounter({{ $studentCount }})" x-text="displayCount">{{ number_format($studentCount) }}</span> Learners
                                </td>

                                <td class="py-4 px-4 sm:px-5 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $institute->is_approved ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200' }}">
                                            {{ $institute->is_approved ? 'Approved' : 'Pending' }}
                                        </span>
                                        @if (isAdmin())
                                            @if ($institute->is_approved)
                                                <flux:modal.trigger name="confirm-disapprove-institution">
                                                    <flux:button type="button" size="sm" variant="ghost"
                                                        wire:click="$set('disapprovingId', {{ $institute->id }})"
                                                        class="text-[11px] text-rose-600 hover:text-rose-700 min-h-[30px]">
                                                        Revoke
                                                    </flux:button>
                                                </flux:modal.trigger>
                                            @else
                                                <flux:button type="button" size="sm" variant="ghost"
                                                    wire:click="approve({{ $institute->id }})"
                                                    class="text-[11px] text-emerald-600 hover:text-emerald-700 min-h-[30px]">
                                                    Approve
                                                </flux:button>
                                            @endif
                                        @endif
                                    </div>
                                </td>

                                <td class="py-4 px-4 sm:px-5 whitespace-nowrap">
                                    @if ($isCurrent)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    @elseif (institutionOwner($institute->owner->id))
                                        <form action="{{ route('institution.choose', $institute->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-xl bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 text-xs font-bold transition min-h-[32px]">
                                                Switch
                                            </button>
                                        </form>
                                    @endif
                                </td>

                                <td class="py-4 px-4 sm:px-5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <flux:button href="{{ route('institution.show', $institute->id) }}" wire:navigate.hover size="sm" icon="eye" variant="subtle" class="min-h-[34px] min-w-[34px]" />
                                        @if (institutionOwner($institute->owner->id))
                                            <flux:button href="{{ route('institution.edit', $institute->id) }}" wire:navigate.hover size="sm" icon="pencil-square" variant="subtle" class="min-h-[34px] min-w-[34px]" />
                                        @endif
                                        @if (isAdmin())
                                            <flux:modal.trigger name="confirm-delete-institution">
                                                <flux:button type="button" size="sm" icon="trash" variant="subtle"
                                                    wire:click="$set('deletingId', {{ $institute->id }})"
                                                    class="text-rose-600 hover:text-rose-700 min-h-[34px] min-w-[34px]"
                                                    tooltip="Delete institution and all its data" />
                                            </flux:modal.trigger>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if (isAdmin())
            <flux:modal name="confirm-disapprove-institution" class="max-w-md" wire:close="$set('disapprovingId', null)">
                <div class="space-y-5">
                    <div>
                        <flux:heading size="lg">Revoke this institution's approval?</flux:heading>
                        <flux:subheading>Its Director is locked out of the dashboard immediately, until an Admin approves it again.</flux:subheading>
                    </div>
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="filled" class="w-full sm:w-auto">Cancel</flux:button>
                        </flux:modal.close>
                        <flux:button type="button" variant="danger" wire:click="disapprove" class="w-full sm:w-auto">Revoke Approval</flux:button>
                    </div>
                </div>
            </flux:modal>

            <flux:modal name="confirm-delete-institution" class="max-w-md" wire:close="$set('deletingId', null)">
                <div class="space-y-5">
                    <div>
                        <flux:heading size="lg">
                            Permanently delete
                            @if ($this->deletingInstitution)
                                "{{ $this->deletingInstitution->name }}"?
                            @else
                                this institution?
                            @endif
                        </flux:heading>
                        <flux:subheading>
                            @if ($this->deletingInstitution)
                                This removes the school and <strong>everything under it</strong> — classes, subjects,
                                fees, results, attendance, timetables, report cards, payroll, payments and devices —
                                across every module. It also deletes
                                <strong>{{ number_format($this->deletingInstitution->student_details_count) }}</strong>
                                student and
                                <strong>{{ number_format($this->deletingInstitution->teacher_details_count) }}</strong>
                                teacher account(s), plus its accountants and its Director, for anyone with no other
                                school. This cannot be undone.
                            @else
                                This permanently removes the school and all of its data. This cannot be undone.
                            @endif
                        </flux:subheading>
                    </div>
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <flux:modal.close>
                            <flux:button type="button" variant="filled" class="w-full sm:w-auto">Cancel</flux:button>
                        </flux:modal.close>
                        <flux:button type="button" variant="danger" icon="trash" wire:click="delete"
                            wire:loading.attr="disabled" class="w-full sm:w-auto">
                            Delete everything
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    </div>
