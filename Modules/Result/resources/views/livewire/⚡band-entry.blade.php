<?php

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultGrader;
use Modules\Student\Models\StudentDetails;

new #[Title('Band Entry')] class extends Component
{
    #[Url]
    public int $examinationId = 0;

    #[Url]
    public ?int $classId = null;

    public string $scaleMode = 'sub_levels'; // 'sub_levels' (EE1-BE2) or 'bands' (EE-BE)

    public ?Examination $examination = null;

    public function mount(int $examination_id = 0): void
    {
        abort_unless(auth()->user()->can('create result'), 403);

        $this->examinationId = $examination_id ?: (int) request()->query('examination_id', $this->examinationId);
        $this->classId ??= request()->query('class_id') ? (int) request()->query('class_id') : null;

        $query = Examination::with(['sitting', 'subject', 'schoolClass']);

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitutionId() ?? 0);
        }

        if (! $this->examinationId) {
            $first = (clone $query)->whereHas('sitting', fn ($q) => $q->whereIn('assessment_category', ['classroom_assessment', 'sba']))->latest()->first();
            if ($first) {
                $this->examinationId = $first->id;
            } else {
                $this->redirect(route('examinations.index'), navigate: true);

                return;
            }
        }

        $this->examination = $query->findOrFail($this->examinationId);

        abort_unless(
            $this->examination->sitting && $this->examination->sitting->isFormative(),
            403,
            'This sheet is only for formative / SBA assessments.'
        );

        $this->classId ??= $this->examination->class_id;

        $className = strtolower($this->examination->schoolClass?->name ?? '');
        if (preg_match('/\b(pp\s*[12]|grade\s*[1-6]|primary)\b/i', $className)) {
            $this->scaleMode = 'bands';
        }
    }

    #[Computed]
    public function students(): Collection
    {
        if (! $this->classId) {
            return collect();
        }

        return StudentDetails::with('student')
            ->where('class_id', $this->classId)
            ->get()
            ->sortBy(fn ($d) => [$d->admission_number, $d->student?->name])
            ->values();
    }

    #[Computed]
    public function existingBands(): Collection
    {
        return Result::where('examination_id', $this->examinationId)
            ->when($this->examination?->subject_id, fn ($q) => $q->where('subject_id', $this->examination->subject_id))
            ->get()
            ->keyBy('student_id');
    }

    public function evaluations(): array
    {
        return $this->scaleMode === 'sub_levels'
            ? array_keys(ResultGrader::CBC_SUB_LEVELS)
            : ResultGrader::CBC_BANDS;
    }

    public function resolveSelectedVal(?string $rawVal): string
    {
        if (! $rawVal) {
            return '';
        }

        $rawVal = strtoupper(trim($rawVal));

        if ($this->scaleMode === 'bands') {
            return ResultGrader::parentBand($rawVal) ?? $rawVal;
        }

        return $rawVal;
    }

    public function initialBands(): array
    {
        $existing = $this->existingBands;
        $result = [];

        foreach ($this->students as $details) {
            $sId = (int) $details->student_id;
            $rawVal = $existing->get($sId)?->cbc_band ?? $existing->get($sId)?->grade;
            $result[$sId] = $this->resolveSelectedVal($rawVal);
        }

        return $result;
    }

    public function initialRemarks(): array
    {
        $existing = $this->existingBands;
        $result = [];

        foreach ($this->students as $details) {
            $sId = (int) $details->student_id;
            $result[$sId] = (string) ($existing->get($sId)?->remarks ?? '');
        }

        return $result;
    }

    public function labels(): array
    {
        return collect(ResultGrader::CBC_SUB_LEVELS)
            ->mapWithKeys(fn ($v, $k) => [$k => $v['label']])
            ->merge([
                'EE' => 'Exceeding',
                'ME' => 'Meeting',
                'AE' => 'Approaching',
                'BE' => 'Below',
            ])
            ->all();
    }
}; ?>
<div>
    <div class="flex flex-col gap-6 w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">
        <div>
            <div class="flex items-center gap-2 text-xs text-zinc-500 mb-1">
                <a href="{{ route('dashboard') }}" wire:navigate.hover class="hover:text-zinc-800">Dashboard</a>
                <span>/</span>
                <a href="{{ route('examinations.index') }}" wire:navigate.hover class="hover:text-zinc-800">Assessments</a>
                <span>/</span>
                <span class="text-zinc-700 font-medium">Band Entry</span>
            </div>
            <flux:heading size="xl" class="font-black tracking-tight">{{ $examination->title }}</flux:heading>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                @if ($examination->sitting)
                    <flux:badge size="sm" color="blue">{{ $examination->sitting->assessmentCategoryLabel() }}</flux:badge>
                @endif
                @if ($examination->subject)
                    <flux:badge size="sm" color="zinc">{{ $examination->subject->name }}</flux:badge>
                @endif
                <flux:badge size="sm" color="zinc">{{ $examination->class_name ?? $examination->schoolClass?->name }}</flux:badge>
                <span class="text-xs text-zinc-400">{{ $examination->exam_date?->format('d M Y') }}</span>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 flex items-center gap-3">
                <flux:icon icon="check-circle" class="size-5 text-emerald-500 shrink-0" />
                <p class="text-sm text-emerald-800 font-medium">{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 flex items-center gap-3">
                <flux:icon icon="exclamation-circle" class="size-5 text-rose-500 shrink-0" />
                <p class="text-sm text-rose-800 font-medium">{{ session('error') }}</p>
            </div>
        @endif

        {{-- Scale mode switcher & key --}}
        <div class="p-4 rounded-2xl bg-zinc-50 border border-zinc-200/80 space-y-3">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-1.5 p-1 bg-zinc-200/70 rounded-xl">
                    <button type="button" wire:click="$set('scaleMode', 'sub_levels')"
                        class="px-3 py-1 text-xs font-bold rounded-lg transition {{ $scaleMode === 'sub_levels' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-600 hover:text-zinc-900' }}">
                        8 Sub-Levels (EE1–BE2) · JSS
                    </button>
                    <button type="button" wire:click="$set('scaleMode', 'bands')"
                        class="px-3 py-1 text-xs font-bold rounded-lg transition {{ $scaleMode === 'bands' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-600 hover:text-zinc-900' }}">
                        4 Broad Bands (EE–BE) · Primary
                    </button>
                </div>
                <span class="text-xs font-medium text-zinc-500">
                    {{ $scaleMode === 'sub_levels' ? 'KJSEA 8-Level Rubric (Levels 8 to 1)' : '4-Band Rubric' }}
                </span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                <div class="p-2 rounded-xl bg-emerald-100/70 border border-emerald-200 text-emerald-900 font-bold">
                    EE (8–7 pts) · Exceeding
                </div>
                <div class="p-2 rounded-xl bg-blue-100/70 border border-blue-200 text-blue-900 font-bold">
                    ME (6–5 pts) · Meeting
                </div>
                <div class="p-2 rounded-xl bg-amber-100/70 border border-amber-200 text-amber-900 font-bold">
                    AE (4–3 pts) · Approaching
                </div>
                <div class="p-2 rounded-xl bg-rose-100/70 border border-rose-200 text-rose-900 font-bold">
                    BE (2–1 pts) · Below
                </div>
            </div>
        </div>

        @if ($this->students->isEmpty())
            <x-empty-state icon="users" title="No students in this class" description="Enroll students before recording bands." />
        @else
            @php
                $initialBands = $this->initialBands();
                $initialRemarks = $this->initialRemarks();
                $labels = $this->labels();
                $totalLearners = $this->students->count();
                $initialRecordedCount = count(array_filter($initialBands));
                $initialPendingCount = max(0, $totalLearners - $initialRecordedCount);
            @endphp

            <div class="bg-white rounded-3xl border border-zinc-200/80 shadow-sm overflow-hidden"
                x-data="{
                    bands: @js($initialBands),
                    remarks: @js($initialRemarks),
                    labels: @js($labels),
                    totalCount: {{ $totalLearners }},
                    setBand(id, val) {
                        this.bands[id] = (this.bands[id] === val ? '' : val);
                    },
                    clearBand(id) {
                        this.bands[id] = '';
                    },
                    get recordedCount() {
                        return Object.values(this.bands).filter(v => v && String(v).trim().length > 0).length;
                    },
                    get pendingCount() {
                        return Math.max(0, this.totalCount - this.recordedCount);
                    }
                }">
                <form action="{{ route('assessment.band.entry.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="examination_id" value="{{ $examination->id }}">
                    <input type="hidden" name="class_id" value="{{ $this->classId }}">
                    @if ($examination->subject_id)
                        <input type="hidden" name="subject_id" value="{{ $examination->subject_id }}">
                    @endif

                    {{-- Single authoritative source of form inputs (prevent desktop/mobile duplication) --}}
                    @foreach ($this->students as $details)
                        @php $sId = (int) $details->student_id; @endphp
                        <input type="hidden" name="bands[{{ $sId }}]" :value="bands[{{ $sId }}] ?? ''" value="{{ $initialBands[$sId] ?? '' }}">
                        <input type="hidden" name="remarks[{{ $sId }}]" :value="remarks[{{ $sId }}] ?? ''" value="{{ $initialRemarks[$sId] ?? '' }}">
                    @endforeach

                    {{-- Header status bar with live counters --}}
                    <div class="px-5 py-3.5 bg-zinc-50/80 border-b border-zinc-200/80 flex items-center justify-between flex-wrap gap-3">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-zinc-900">Learner Evaluation Marksheet</span>
                                <span class="text-xs text-zinc-400 font-medium">({{ $totalLearners }} learners)</span>
                            </div>
                            <p class="text-xs text-zinc-500">
                                Click a band to grade. Click the active band again (or the <span class="font-bold">×</span> button) to uncheck/clear.
                            </p>
                        </div>
                        <div class="flex items-center gap-4 text-xs font-semibold">
                            <span class="inline-flex items-center gap-1.5 text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                                <span class="size-2 rounded-full bg-emerald-500"></span>
                                Recorded: <span class="font-black" x-text="recordedCount">{{ $initialRecordedCount }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-zinc-600 bg-zinc-100 px-2.5 py-1 rounded-lg border border-zinc-200">
                                <span class="size-2 rounded-full" :class="pendingCount > 0 ? 'bg-amber-400' : 'bg-zinc-400'"></span>
                                Pending: <span class="font-black" x-text="pendingCount">{{ $initialPendingCount }}</span>
                            </span>
                        </div>
                    </div>

                    {{-- Desktop table --}}
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50/50 border-b border-zinc-200">
                                <tr>
                                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase text-zinc-500 w-12">#</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase text-zinc-500">Student</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold uppercase text-zinc-500">Adm No.</th>
                                    <th class="text-center px-3 py-3 text-xs font-semibold uppercase text-zinc-500 w-44">Current Evaluation</th>
                                    <th class="text-center px-4 py-3 text-xs font-semibold uppercase text-zinc-500">Evaluation Band</th>
                                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase text-zinc-500 w-48">Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @foreach ($this->students as $i => $details)
                                    @php
                                        $sId = (int) $details->student_id;
                                        $currentVal = $initialBands[$sId] ?? '';
                                    @endphp
                                    <tr class="hover:bg-zinc-50/50 transition">
                                        <td class="px-4 py-3 text-xs text-zinc-400 font-mono">{{ $i + 1 }}</td>
                                        <td class="px-4 py-3 font-semibold text-zinc-900">{{ $details->student?->name ?? '—' }}</td>
                                        <td class="px-3 py-3 text-xs text-zinc-500 font-mono whitespace-nowrap">{{ $details->admission_number ?? '—' }}</td>
                                        <td class="px-3 py-3 text-center whitespace-nowrap">
                                            <div x-show="bands[{{ $sId }}]" x-cloak>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold border transition shadow-xs"
                                                    :class="{
                                                        'bg-emerald-50 text-emerald-800 border-emerald-300': bands[{{ $sId }}]?.startsWith('EE'),
                                                        'bg-blue-50 text-blue-800 border-blue-300': bands[{{ $sId }}]?.startsWith('ME'),
                                                        'bg-amber-50 text-amber-800 border-amber-300': bands[{{ $sId }}]?.startsWith('AE'),
                                                        'bg-rose-50 text-rose-800 border-rose-300': bands[{{ $sId }}]?.startsWith('BE')
                                                    }">
                                                    <span class="size-1.5 rounded-full"
                                                        :class="{
                                                            'bg-emerald-500': bands[{{ $sId }}]?.startsWith('EE'),
                                                            'bg-blue-500': bands[{{ $sId }}]?.startsWith('ME'),
                                                            'bg-amber-500': bands[{{ $sId }}]?.startsWith('AE'),
                                                            'bg-rose-500': bands[{{ $sId }}]?.startsWith('BE')
                                                        }"></span>
                                                    <span class="font-black" x-text="bands[{{ $sId }}]">{{ $currentVal }}</span>
                                                    <span class="text-[11px] opacity-75 font-normal" x-show="labels[bands[{{ $sId }}]]" x-text="'· ' + labels[bands[{{ $sId }}]]"></span>
                                                    <button type="button" @click="clearBand({{ $sId }})" title="Clear / uncheck this evaluation"
                                                        class="ml-1 text-zinc-400 hover:text-zinc-700 p-0.5 rounded cursor-pointer transition">
                                                        <flux:icon icon="x-mark" class="size-3" />
                                                    </button>
                                                </span>
                                            </div>
                                            <div x-show="!bands[{{ $sId }}]">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium text-zinc-400 bg-zinc-100">
                                                    Pending
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                                @foreach ($this->evaluations() as $eval)
                                                    @php
                                                        $parent = ResultGrader::parentBand($eval) ?? $eval;
                                                        $activeStyles = match ($parent) {
                                                            'EE' => 'bg-emerald-600 text-white border-emerald-600 shadow-sm font-black ring-2 ring-emerald-400 ring-offset-1',
                                                            'ME' => 'bg-blue-600 text-white border-blue-600 shadow-sm font-black ring-2 ring-blue-400 ring-offset-1',
                                                            'AE' => 'bg-amber-500 text-white border-amber-500 shadow-sm font-black ring-2 ring-amber-400 ring-offset-1',
                                                            'BE' => 'bg-rose-600 text-white border-rose-600 shadow-sm font-black ring-2 ring-rose-400 ring-offset-1',
                                                            default => 'bg-zinc-800 text-white border-zinc-800 font-black',
                                                        };
                                                        $isCurrent = ($currentVal === $eval);
                                                    @endphp
                                                    <button type="button"
                                                        @click="setBand({{ $sId }}, '{{ $eval }}')"
                                                        :class="bands[{{ $sId }}] === '{{ $eval }}'
                                                            ? '{{ $activeStyles }}'
                                                            : 'bg-white text-zinc-700 border-zinc-200 hover:border-zinc-400 hover:bg-zinc-50 font-semibold'"
                                                        class="px-2.5 py-1 rounded-lg border text-xs transition-all shadow-xs cursor-pointer select-none {{ $isCurrent ? $activeStyles : 'bg-white text-zinc-700 border-zinc-200 font-semibold' }}">
                                                        {{ $eval }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">
                                            <input type="text" x-model="remarks[{{ $sId }}]"
                                                placeholder="Remarks…" class="w-full text-xs rounded-lg border border-zinc-200 px-2.5 py-1.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-900/10 focus:border-zinc-900">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile stacked cards --}}
                    <div class="sm:hidden divide-y divide-zinc-100">
                        @foreach ($this->students as $details)
                            @php
                                $sId = (int) $details->student_id;
                                $currentVal = $initialBands[$sId] ?? '';
                            @endphp
                            <div class="p-4 space-y-2.5">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-xs font-bold text-zinc-900">{{ $details->student?->name ?? '—' }}</p>
                                        <p class="text-[11px] text-zinc-400 font-mono">{{ $details->admission_number ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <div x-show="bands[{{ $sId }}]" x-cloak>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-bold border transition shadow-xs"
                                                :class="{
                                                    'bg-emerald-50 text-emerald-800 border-emerald-300': bands[{{ $sId }}]?.startsWith('EE'),
                                                    'bg-blue-50 text-blue-800 border-blue-300': bands[{{ $sId }}]?.startsWith('ME'),
                                                    'bg-amber-50 text-amber-800 border-amber-300': bands[{{ $sId }}]?.startsWith('AE'),
                                                    'bg-rose-50 text-rose-800 border-rose-300': bands[{{ $sId }}]?.startsWith('BE')
                                                }">
                                                <span class="size-1.5 rounded-full"
                                                    :class="{
                                                        'bg-emerald-500': bands[{{ $sId }}]?.startsWith('EE'),
                                                        'bg-blue-500': bands[{{ $sId }}]?.startsWith('ME'),
                                                        'bg-amber-500': bands[{{ $sId }}]?.startsWith('AE'),
                                                        'bg-rose-500': bands[{{ $sId }}]?.startsWith('BE')
                                                    }"></span>
                                                <span class="font-black" x-text="bands[{{ $sId }}]">{{ $currentVal }}</span>
                                                <button type="button" @click="clearBand({{ $sId }})" title="Clear / uncheck this evaluation"
                                                    class="ml-1 text-zinc-400 hover:text-zinc-700">
                                                    <flux:icon icon="x-mark" class="size-3" />
                                                </button>
                                            </span>
                                        </div>
                                        <div x-show="!bands[{{ $sId }}]">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium text-zinc-400 bg-zinc-100">
                                                Pending
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-4 gap-1.5">
                                    @foreach ($this->evaluations() as $eval)
                                        @php
                                            $parent = ResultGrader::parentBand($eval) ?? $eval;
                                            $activeStyles = match ($parent) {
                                                'EE' => 'bg-emerald-600 text-white border-emerald-600 shadow-sm font-black ring-2 ring-emerald-400 ring-offset-1',
                                                'ME' => 'bg-blue-600 text-white border-blue-600 shadow-sm font-black ring-2 ring-blue-400 ring-offset-1',
                                                'AE' => 'bg-amber-500 text-white border-amber-500 shadow-sm font-black ring-2 ring-amber-400 ring-offset-1',
                                                'BE' => 'bg-rose-600 text-white border-rose-600 shadow-sm font-black ring-2 ring-rose-400 ring-offset-1',
                                                default => 'bg-zinc-800 text-white border-zinc-800 font-black',
                                            };
                                            $isCurrent = ($currentVal === $eval);
                                        @endphp
                                        <button type="button"
                                            @click="setBand({{ $sId }}, '{{ $eval }}')"
                                            :class="bands[{{ $sId }}] === '{{ $eval }}'
                                                ? '{{ $activeStyles }}'
                                                : 'bg-white text-zinc-700 border-zinc-200 hover:border-zinc-400 hover:bg-zinc-50 font-semibold'"
                                            class="text-center py-2 rounded-lg border text-xs transition-all shadow-xs cursor-pointer select-none {{ $isCurrent ? $activeStyles : 'bg-white text-zinc-700 border-zinc-200 font-semibold' }}">
                                            {{ $eval }}
                                        </button>
                                    @endforeach
                                </div>
                                <input type="text" x-model="remarks[{{ $sId }}]"
                                    placeholder="Remarks (optional)…" class="w-full text-xs rounded-lg border border-zinc-200 px-2.5 py-1.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-900/10 focus:border-zinc-900">
                            </div>
                        @endforeach
                    </div>

                    <div class="px-5 py-4 bg-zinc-50 border-t border-zinc-200 flex items-center justify-between gap-3">
                        <span class="text-xs text-zinc-500 font-medium">
                            <span class="font-bold text-zinc-800" x-text="recordedCount">{{ $initialRecordedCount }}</span> of {{ $totalLearners }} evaluated
                        </span>
                        <flux:button type="submit" variant="primary" class="font-bold px-6">
                            Save Evaluations
                        </flux:button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
