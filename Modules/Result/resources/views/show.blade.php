@php
    $student = $result->student;
    $initials = $student ? strtoupper(substr($student->name, 0, 2)) : 'ST';
    $admNo = $student?->studentUserDetails?->admission_number ?? 'ADM-' . $result->id;
    $marks = (float) $result->marks_obtained;
    $totalMarks = (float) ($result->examination?->total_marks ?? 100);
    $pct = $totalMarks > 0 ? round(($marks / $totalMarks) * 100, 1) : 0;
    $grade = $result->grade ?? '—';
    $subjectName = $result->subject?->name ?? $result->examination?->subject?->name ?? $result->examination?->subject_name ?? 'Subject';
@endphp

<x-layouts::app :title="__('Assessment Result')">
    <div class="flex flex-col gap-8 w-full max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <a href="{{ route('result.index') }}" wire:navigate.hover class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Results Ledger</span>
                    </a>
                    <span class="text-xs text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">Evaluation Analysis</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Assessment Score Details</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Verified performance metrics, syllabus mastery, and evaluator notes.
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                @can('edit result')
                    <flux:button href="{{ route('result.edit', $result->id) }}" wire:navigate.hover icon="pencil-square" variant="primary" class="font-semibold shadow-sm">
                        Edit Score
                    </flux:button>
                @endcan
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

        {{-- Student Profile & Score Overview Card --}}
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-zinc-200/80 shadow-sm relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6">
                <div class="flex items-start gap-4">
                    <div class="size-16 rounded-2xl bg-zinc-100 border border-zinc-200 text-zinc-800 font-black text-xl flex items-center justify-center shadow-sm shrink-0">
                        {{ $initials }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            @if($student)
                                <a href="{{ route('student.show', $student->id) }}" wire:navigate.hover class="text-xl font-black text-zinc-900 hover:text-blue-600 transition">
                                    {{ $student->name }}
                                </a>
                            @else
                                <span class="text-xl font-black text-zinc-900">Student</span>
                            @endif
                            <span class="px-2.5 py-0.5 rounded-lg bg-zinc-100 text-zinc-700 font-medium text-xs border border-zinc-200/60">
                                {{ $result->schoolClass?->name ?? 'Class Assigned' }}
                            </span>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-3 mt-1.5 text-xs text-zinc-500 font-medium">
                            <span class="font-mono">{{ $admNo }}</span>
                            <span class="text-zinc-300">&bull;</span>
                            <span>{{ $result->institution?->name ?? currentInstitution()?->name ?? __('Institution') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Performance Score & Grade Badge --}}
                <div class="flex flex-col sm:items-end">
                    <div class="flex items-center gap-2">
                        <span class="text-3xl font-black text-zinc-900 tracking-tight font-mono">{{ $result->marks_obtained }}</span>
                        <span class="text-zinc-400 font-bold text-sm">/ {{ $result->examination?->total_marks ?? 100 }}</span>
                    </div>
                    <div class="mt-1">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold font-mono
                            {{ in_array($grade, ['A', 'A-']) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                               (in_array($grade, ['B+', 'B', 'B-']) ? 'bg-blue-50 text-blue-700 border border-blue-200' :
                               (in_array($grade, ['C+', 'C', 'C-']) ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-zinc-100 text-zinc-700 border border-zinc-200')) }}">
                            <span class="size-1.5 rounded-full {{ in_array($grade, ['A', 'A-']) ? 'bg-emerald-500' : (in_array($grade, ['B+', 'B', 'B-']) ? 'bg-blue-500' : 'bg-amber-500') }}"></span>
                            Grade {{ $grade }} &bull; {{ $pct }}%
                        </span>
                    </div>
                </div>
            </div>

            {{-- Assessment Detail Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 pt-6 border-t border-zinc-100">
                <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Subject</span>
                    <span class="text-sm font-black text-zinc-900">{{ $subjectName }}</span>
                </div>
                <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Assessment Title</span>
                    <span class="text-sm font-black text-zinc-900 truncate block" title="{{ $result->examination?->title }}">
                        {{ $result->examination?->title ?? 'Assessment' }}
                    </span>
                </div>
                <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-100">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Term & Academic Year</span>
                    <span class="text-sm font-black text-zinc-900">{{ $result->examination?->term ?? 'Term 1' }} &bull; 2026</span>
                </div>
            </div>

            {{-- Remarks & Teacher Note --}}
            @if ($result->remarks)
                <div class="mt-6 p-4 rounded-2xl bg-zinc-50 border border-zinc-100 text-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Evaluator Observations</span>
                    <p class="text-zinc-800 font-medium leading-relaxed italic whitespace-pre-line">&ldquo;{{ $result->remarks }}&rdquo;</p>
                </div>
            @endif

            {{-- Evaluator Attribution --}}
            <div class="mt-6 pt-4 border-t border-zinc-100 flex items-center justify-between text-xs text-zinc-500">
                <div class="flex items-center gap-2">
                    <span class="text-zinc-400">Recorded By:</span>
                    <strong class="text-zinc-800 font-semibold">{{ $result->recordedBy?->name ?? 'System Administrator' }}</strong>
                </div>
                <div class="font-mono text-zinc-400 text-[11px]">
                    Evaluation ID #{{ $result->id }}
                </div>
            </div>
        </div>

        {{-- Footer Controls --}}
        <div class="bg-zinc-50 rounded-2xl p-4 border border-zinc-200/80 flex items-center justify-between">
            <flux:button href="{{ route('result.index') }}" variant="subtle" icon="arrow-left">
                Back to Results
            </flux:button>

            <div class="flex items-center gap-2">
                @can('edit result')
                    <flux:button href="{{ route('result.edit', $result->id) }}" icon="pencil-square" variant="primary">
                        Edit Score
                    </flux:button>
                @endcan

                @can('delete result')
                    <form action="{{ route('result.destroy', $result->id) }}" method="POST"
                        onsubmit="return confirm('Remove this assessment score?');">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" variant="subtle" class="text-rose-600 hover:bg-rose-50" icon="trash">
                            Delete
                        </flux:button>
                    </form>
                @endcan
            </div>
        </div>

    </div>
</x-layouts::app>