@php
    $exams = $subject->examinations;
    $examCount = $exams->count();
    $code = $subject->code ?: 'SUB-' . str_pad($subject->id, 3, '0', STR_PAD_LEFT);
@endphp

<x-layouts::app :title="__('Subject Details - ' . $subject->name)">
    <div class="flex flex-col gap-6 w-full max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Navigation & Breadcrumbs --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('subject.index') }}" wire:navigate.hover class="inline-flex items-center gap-1 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Subjects</span>
                    </a>
                    <span class="text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">Syllabus Overview</span>
                </div>
                <h1 class="text-2xl font-black tracking-tight text-zinc-900">{{ $subject->name }}</h1>
                <p class="text-zinc-500 text-xs mt-0.5">
                    Course specifications, curriculum classification, and examination papers ledger.
                </p>
            </div>

            <div class="flex items-center gap-2">
                @can('edit subject')
                    <a href="{{ route('subject.edit', $subject->id) }}" wire:navigate.hover class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold transition shadow-xs">
                        <flux:icon icon="pencil-square" class="size-3.5 text-white" />
                        <span>Edit Subject</span>
                    </a>
                @endcan
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3.5 flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2.5">
                    <flux:icon icon="check-circle" class="size-4 text-emerald-600 shrink-0" />
                    <span class="text-xs font-semibold text-emerald-900">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Executive Identity Banner --}}
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-zinc-200/80 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="size-14 rounded-2xl bg-zinc-100 border border-zinc-200/80 text-zinc-700 flex items-center justify-center shrink-0">
                    <flux:icon icon="book-open" class="size-7" />
                </div>

                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h2 class="text-lg font-black text-zinc-900 tracking-tight">{{ $subject->name }}</h2>
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-bold
                            {{ $subject->is_compulsory ? 'bg-zinc-100 text-zinc-800 border border-zinc-200' : 'bg-zinc-50 text-zinc-500 border border-zinc-200/60' }}">
                            {{ $subject->is_compulsory ? 'Compulsory Core' : 'Optional / Elective' }}
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold
                            {{ $subject->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-zinc-100 text-zinc-600 border border-zinc-200' }}">
                            {{ $subject->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 mt-1 font-mono">
                        <span class="px-2 py-0.5 rounded bg-zinc-100 text-zinc-800 font-bold border border-zinc-200/80 text-[11px]">{{ $code }}</span>
                        <span class="text-zinc-300">&bull;</span>
                        <span class="font-sans text-xs text-zinc-600">{{ $subject->institution?->name ?? 'Main Campus' }}</span>
                    </div>
                </div>
            </div>

            <div class="flex sm:flex-col items-end justify-between w-full sm:w-auto pt-3 sm:pt-0 border-t sm:border-t-0 border-zinc-100 text-xs">
                <span class="text-zinc-400">Total Evaluations</span>
                <span class="font-bold text-zinc-900 text-sm mt-0.5 font-mono">{{ $examCount }} Linked Exams</span>
            </div>
        </div>

        {{-- 2-Column Responsive Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left Column (Broad: Examination Papers Ledger) --}}
            <div class="lg:col-span-2 flex flex-col gap-6">

                {{-- Examinations Ledger Card --}}
                <div class="bg-white rounded-3xl p-6 border border-zinc-200/80 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="document-text" class="size-4 text-zinc-500" />
                            <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Linked Examination Papers ({{ $examCount }})</h3>
                        </div>
                        @can('create examination')
                            <flux:button href="{{ route('examinations.create', ['subject_id' => $subject->id]) }}" size="sm" icon="plus" variant="subtle">
                                Create Exam
                            </flux:button>
                        @endcan
                    </div>

                    @if ($exams->isEmpty())
                        <div class="text-center py-10">
                            <div class="size-10 rounded-xl bg-zinc-100 flex items-center justify-center text-zinc-400 mx-auto mb-2">
                                <flux:icon icon="document-text" class="size-5" />
                            </div>
                            <p class="text-xs font-bold text-zinc-800">No Examinations Registered</p>
                            <p class="text-[11px] text-zinc-400 mt-0.5">There are no examination papers linked to this subject yet.</p>
                        </div>
                    @else
                        <div class="border border-zinc-200/80 rounded-2xl overflow-x-auto">
                            <table class="w-full text-left text-xs table-fixed min-w-[480px]">
                                <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 font-bold uppercase tracking-wider text-[10px]">
                                    <tr>
                                        <th class="py-3 px-4 w-5/12">Assessment Title & Class</th>
                                        <th class="py-3 px-4 w-3/12">Term & Year</th>
                                        <th class="py-3 px-4 w-2/12">Date</th>
                                        <th class="py-3 px-4 w-2/12 text-right">Max Marks</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100">
                                    @foreach ($exams as $exam)
                                        <tr class="hover:bg-zinc-50/50 transition">
                                            <td class="py-3 px-4">
                                                <div class="font-bold text-zinc-900">{{ $exam->title }}</div>
                                                <div class="text-[11px] text-zinc-400 mt-0.5 font-medium">{{ $exam->class_name ?? 'All Classes' }}</div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-0.5 rounded bg-zinc-100 text-zinc-700 font-semibold text-[10px] border border-zinc-200/60">
                                                    {{ $exam->term ?: 'Term 1' }} &bull; {{ $exam->academic_year ?: date('Y') }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 font-mono text-zinc-600">
                                                {{ $exam->exam_date ? $exam->exam_date->format('M d, Y') : '—' }}
                                            </td>
                                            <td class="py-3 px-4 text-right font-mono font-bold text-zinc-900">
                                                {{ $exam->total_marks ?? 100 }} pts
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

            </div>

            {{-- Right Column (Narrow: Specifications & Actions) --}}
            <div class="flex flex-col gap-6">

                {{-- Subject Specifications Card --}}
                <div class="bg-white rounded-3xl p-6 border border-zinc-200/80 shadow-xs space-y-4">
                    <div class="flex items-center gap-2 pb-3 border-b border-zinc-100">
                        <flux:icon icon="academic-cap" class="size-4 text-zinc-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-700">Course Specifications</h3>
                    </div>

                    <div class="space-y-3.5 text-xs">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Course Code</span>
                            <span class="font-mono font-bold text-zinc-900 text-xs bg-zinc-50 px-2.5 py-1 rounded-lg border border-zinc-200 inline-block">{{ $code }}</span>
                        </div>

                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Curriculum Requirement</span>
                            <strong class="text-zinc-900 font-bold">
                                {{ $subject->is_compulsory ? 'Compulsory for All Learners' : 'Elective / Optional Choice' }}
                            </strong>
                        </div>

                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-0.5">Enrolled Institution</span>
                            <strong class="text-zinc-900 font-bold">{{ $subject->institution?->name ?? 'Main Campus' }}</strong>
                        </div>
                    </div>
                </div>

                {{-- Quick Navigation & Management Card --}}
                <div class="bg-zinc-50 rounded-3xl p-5 border border-zinc-200/80 flex flex-col gap-2 text-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-1">Subject Actions</span>
                    <flux:button href="{{ route('subject.index') }}" variant="subtle" icon="arrow-left" class="justify-start">
                        Back to Directory
                    </flux:button>
                    @can('edit subject')
                        <flux:button href="{{ route('subject.edit', $subject->id) }}" variant="primary" icon="pencil-square" class="justify-start">
                            Edit Subject Details
                        </flux:button>
                    @endcan
                    @can('delete subject')
                        <x-confirm-form
                            :action="route('subject.destroy', $subject->id)"
                            title="Remove {{ $subject->name }} from the curriculum?"
                            message="This permanently deletes the subject. Recorded results are kept."
                            confirm="Remove Subject"
                            label="Remove Subject"
                            variant="subtle"
                            trigger-class="text-rose-600 hover:bg-rose-50 w-full justify-start"
                            class="w-full" />
                    @endcan
                </div>

            </div>

        </div>

    </div>
</x-layouts::app>