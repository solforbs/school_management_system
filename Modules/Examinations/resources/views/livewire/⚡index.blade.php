<?php

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Subject\Models\Subject;

new #[Title('Examinations')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $class_id = '';

    #[Url]
    public string $subject_id = '';

    #[Url]
    public string $status = '';

    #[Url(as: 'type')]
    public string $examType = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view examination') || auth()->user()->can('view examinations'), 403);
    }

    #[Computed]
    public function examinations(): LengthAwarePaginator
    {
        $today = now()->toDateString();

        return Examination::with(['schoolClass', 'subject'])
            ->when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->when($this->search !== '', fn ($q) => $q->where(function ($sq) {
                $sq->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('subject_name', 'like', '%'.$this->search.'%')
                    ->orWhereHas('subject', fn ($subQ) => $subQ->where('name', 'like', '%'.$this->search.'%'));
            }))
            ->when($this->class_id !== '', fn ($q) => $q->where('class_id', $this->class_id))
            ->when($this->subject_id !== '', fn ($q) => $q->where('subject_id', $this->subject_id))
            ->when($this->examType !== '', fn ($q) => $q->where('exam_type', $this->examType))
            ->when($this->status === 'upcoming', fn ($q) => $q->where('exam_date', '>', $today))
            ->when($this->status === 'completed', fn ($q) => $q->where('exam_date', '<', $today))
            ->when($this->status === 'today', fn ($q) => $q->where('exam_date', $today))
            ->orderBy('exam_date', 'desc')
            ->paginate(15);
    }

    #[Computed]
    public function classes(): Collection
    {
        return SchoolClass::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function subjects(): Collection
    {
        return Subject::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
            ->orderBy('name')
            ->get();
    }
}; ?>

@php
    $examinations = $this->examinations;
    $classes = $this->classes;
    $subjects = $this->subjects;

    $totalExams = $examinations->total();
    $upcomingExams = Examination::when(! isAdmin(), fn ($q) => $q->where('institution_id', currentInstitutionId()))
        ->where('exam_date', '>', now()->toDateString())
        ->count();
    $activeClasses = $classes->count();
    $totalSubjects = $subjects->count();

    $canViewTimetable = Route::has('examinations.timetable') && auth()->user()->can('view examination');
    $canViewResults = Route::has('result.index') && (auth()->user()->can('view result') || auth()->user()->can('view results'));
    $pdfFilters = array_filter(['exam_type' => $this->examType, 'class_id' => $this->class_id]);
@endphp

    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Examinations</flux:heading>
                    <flux:badge size="sm" color="amber" class="font-semibold">
                        {{ $totalExams }} Scheduled
                    </flux:badge>
                </div>
                <flux:text class="text-zinc-500 text-sm mt-1">
                    Manage assessment timetables, grading periods, and subject schedules.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-3">
                @if(Route::has('examinations.timetable.pdf'))
                    <flux:button href="{{ route('examinations.timetable.pdf', $pdfFilters) }}" icon="arrow-down-tray" variant="subtle">
                        Print Timetable
                    </flux:button>
                @endif
                @if($canViewTimetable)
                    <flux:button href="{{ route('examinations.timetable') }}" wire:navigate.hover icon="calendar" variant="subtle">
                        Timetable Grid
                    </flux:button>
                @endif
                @can('create examination')
                    <flux:button href="{{ route('examinations.create') }}" wire:navigate.hover icon="plus" variant="primary" class="shadow-sm hover:shadow-md transition-all">
                        Create Exam
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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-card 
                title="Total Exams" 
                icon="document-text" 
                color="amber" 
                :count="$totalExams" 
                subtext="registered assessments" 
                :href="route('examinations.index')" 
            />
            <x-kpi-card 
                title="Upcoming Tests" 
                icon="clock" 
                color="sky" 
                :count="$upcomingExams" 
                subtext="pending assessment dates" 
                :href="route('examinations.index', ['status' => 'upcoming'])" 
            />
            <x-kpi-card 
                title="Active Classes" 
                icon="user-group" 
                color="emerald" 
                :count="$activeClasses" 
                subtext="participating streams" 
                :href="route('classes.index')" 
            />
            <x-kpi-card 
                title="Tested Subjects" 
                icon="book-open" 
                color="indigo" 
                :count="$totalSubjects" 
                subtext="curriculum subjects" 
                :href="route('subject.index')" 
            />
        </div>

        {{-- Filter and Search Toolbar --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Search exams..." icon="magnifying-glass" class="w-full sm:w-64" />
                
                <flux:select wire:model.live="class_id" placeholder="All Classes" class="w-full sm:w-44">
                    <flux:select.option value="">All Classes</flux:select.option>
                    @foreach ($this->classes as $class)
                        <flux:select.option value="{{ $class->id }}">{{ $class->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="subject_id" placeholder="All Subjects" class="w-full sm:w-44">
                    <flux:select.option value="">All Subjects</flux:select.option>
                    @foreach ($this->subjects as $subject)
                        <flux:select.option value="{{ $subject->id }}">{{ $subject->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="examType" placeholder="All Sittings" class="w-full sm:w-36">
                    <flux:select.option value="">All Sittings</flux:select.option>
                    @foreach (\Modules\Examinations\Models\Examination::EXAM_TYPES as $typeKey => $typeLabel)
                        <flux:select.option value="{{ $typeKey }}">{{ $typeLabel }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="status" placeholder="All Status" class="w-full sm:w-36">
                    <flux:select.option value="">All Status</flux:select.option>
                    <flux:select.option value="upcoming">Upcoming</flux:select.option>
                    <flux:select.option value="today">Today</flux:select.option>
                    <flux:select.option value="completed">Completed</flux:select.option>
                </flux:select>
            </div>
        </div>

        {{-- Examinations Table Card --}}
        <div class="bg-white dark:bg-zinc-900 shadow-[0_1px_3px_0_rgba(0,0,0,0.05)] border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden flex flex-col">
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Assessment Details</flux:table.column>
                        <flux:table.column>Subject &amp; Stream</flux:table.column>
                        <flux:table.column>Exam Schedule</flux:table.column>
                        <flux:table.column>Time &amp; Venue</flux:table.column>
                        <flux:table.column align="end">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($examinations as $examination)
                            @php
                                $examDate = $examination->exam_date ? Carbon::parse($examination->exam_date) : null;
                                $isToday = $examDate && $examDate->isToday();
                                $isPast = $examDate && $examDate->isPast() && !$isToday;
                                $isUpcoming = $examDate && $examDate->isFuture() && !$isToday;
                            @endphp
                            <flux:table.row class="transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 group">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <div class="size-8 rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400 ring-1 ring-inset ring-amber-500/20 flex items-center justify-center font-bold text-xs shrink-0 transition-transform group-hover:scale-105">
                                            <flux:icon icon="document-text" class="size-4" />
                                        </div>
                                        <div>
                                            <a href="{{ route('examinations.show', $examination->id) }}" wire:navigate.hover class="font-semibold text-sm text-zinc-900 dark:text-white hover:text-amber-600 dark:hover:text-amber-400 transition-colors">
                                                {{ $examination->title }}
                                            </a>
                                            <div class="text-xs text-zinc-400 font-mono">
                                                {{ $examination->term ?? 'Term 1' }} @if($examination->exam_type)&bull; {{ $examination->examTypeLabel() }}@endif &bull; Max: {{ $examination->total_marks ?? 100 }} Marks
                                            </div>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-medium text-xs text-zinc-900 dark:text-zinc-100">
                                            {{ $examination->subject?->name ?? 'Subject' }}
                                        </span>
                                        <div class="flex items-center gap-1 text-[11px] text-zinc-500">
                                            <flux:icon icon="user-group" class="size-3 text-zinc-400 shrink-0" />
                                            <span>{{ $examination->schoolClass?->name ?? 'Class' }}</span>
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex flex-col gap-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $examDate?->format('d M Y') ?? 'TBA' }}
                                            </span>
                                            @if ($isToday)
                                                <flux:badge color="emerald" size="sm" class="font-semibold text-[10px]">
                                                    <span class="size-1.5 rounded-full bg-emerald-500 mr-1 animate-pulse"></span>
                                                    Today
                                                </flux:badge>
                                            @elseif ($isUpcoming)
                                                <flux:badge color="sky" size="sm" class="font-medium text-[10px]">
                                                    Upcoming
                                                </flux:badge>
                                            @elseif ($isPast)
                                                <flux:badge color="zinc" size="sm" class="font-medium text-[10px]">
                                                    Completed
                                                </flux:badge>
                                            @endif
                                        </div>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell>
                                    <div class="flex flex-col gap-0.5 text-xs text-zinc-600 dark:text-zinc-400">
                                        <span>{{ $examination->start_time ? Carbon::parse($examination->start_time)->format('h:i A') : '09:00 AM' }}</span>
                                        <span class="text-[11px] text-zinc-400">{{ $examination->room ?? 'Hall A' }}</span>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell align="end">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button href="{{ route('examinations.show', $examination->id) }}" wire:navigate.hover icon="eye" size="sm" variant="subtle" tooltip="View Exam Details" />
                                        @can('edit examination')
                                            <flux:button href="{{ route('examinations.edit', $examination->id) }}" wire:navigate.hover icon="pencil" size="sm" variant="subtle" tooltip="Edit Exam" />
                                        @endcan
                                        @can('delete examination')
                                            <form action="{{ route('examinations.destroy', $examination->id) }}" method="POST" class="inline" onsubmit="return confirm('Delete this examination schedule?');">
                                                @csrf
                                                @method('DELETE')
                                                <flux:button type="submit" icon="trash" size="sm" variant="subtle" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10" tooltip="Delete Exam" />
                                            </form>
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5">
                                    <x-empty-state icon="document-text" color="amber" title="No Examinations Scheduled" description="Create examination terms and assess student performance.">
                                        @can('create examination')
                                            <flux:button href="{{ route('examinations.create') }}" wire:navigate.hover icon="plus" variant="primary">
                                                Schedule Exam
                                            </flux:button>
                                        @endcan
                                    </x-empty-state>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            @if ($examinations->hasPages())
                <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                    {{ $examinations->links() }}
                </div>
            @endif
        </div>
    </div>
