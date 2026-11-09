<x-layouts::app :title="__('Report Card')">
    @php
        $curriculum = $reportCard->schoolClass?->curriculum;
        $isCbc = $curriculum?->isCbc() ?? str_starts_with($reportCard->schoolClass?->name ?? '', 'Grade');

        // Helper for band color styling
        $bandColorClasses = [
            'EE' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
            'ME' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
            'AE' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
            'BE' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
        ];

        $meanCode = $reportCard->mean_grade;
        $subLevelInfo = \Modules\Result\Services\ResultGrader::CBC_SUB_LEVELS[$meanCode] ?? null;
        $broadBand = $subLevelInfo['band'] ?? ($meanCode ? substr($meanCode, 0, 2) : 'ME');

        // Calculate average points across rows if available
        $validPointsRows = isset($rows) ? $rows->filter(fn($r) => $r->points() !== null) : collect();
        $calcMeanPoints = $validPointsRows->isNotEmpty() ? round($validPointsRows->avg(fn($r) => $r->points()), 1) : null;
        $pointsDisplay = $calcMeanPoints !== null ? $calcMeanPoints : ($subLevelInfo['points'] ?? ($reportCard->mean_percentage ? round($reportCard->mean_percentage * 0.08, 1) : null));
    @endphp

    <div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Navigation & Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('reportcard.index') }}" wire:navigate.hover
                    class="p-2 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-600 transition">
                    <flux:icon icon="arrow-left" class="size-5" />
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
                            <span translate="no">{{ $reportCard->student?->name }}</span>
                        </flux:heading>
                        <flux:badge :color="$isCbc ? 'emerald' : 'blue'" size="sm" class="font-bold">
                            {{ $isCbc ? 'CBC Competency Report' : '8-4-4 Report' }}
                        </flux:badge>
                    </div>
                    <flux:text class="text-zinc-500 text-sm">
                        <span translate="no">{{ $reportCard->schoolClass?->name }}</span> &middot; {{ $reportCard->term }}
                        {{ $reportCard->academic_year }}
                    </flux:text>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <flux:button href="{{ route('reportcard.pdf', $reportCard->id) }}" target="_blank" variant="primary"
                    icon="printer" class="font-semibold shadow-sm px-4">
                    Print / View Official PDF
                </flux:button>
                <a href="{{ route('reportcard.index') }}" wire:navigate.hover
                    class="px-4 py-2 bg-white border border-zinc-300 rounded-lg text-sm font-medium text-zinc-700 hover:bg-zinc-50 transition">
                    Back to List
                </a>
            </div>
        </div>

        {{-- Main Report Card Summary Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-zinc-200 overflow-hidden">
            <div class="border-b border-zinc-100 px-6 py-5 bg-gradient-to-r from-zinc-50 to-white flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                        {{ $isCbc ? 'Competency-Based Curriculum Assessment' : 'Academic Assessment' }}
                    </span>
                    <h3 class="text-base font-semibold text-zinc-900 mt-0.5">
                        {{ $reportCard->term }} {{ $isCbc ? 'Competency Evaluation Dossier' : 'Final Performance Report' }}
                    </h3>
                </div>
                <flux:badge :color="$reportCard->isSent() ? 'emerald' : 'amber'" size="md" class="font-bold">
                    {{ $reportCard->isSent() ? 'Sent to Parent' : 'Ready for Printing' }}
                </flux:badge>
            </div>

            <div class="p-6 grid grid-cols-2 sm:grid-cols-4 gap-6 border-b border-zinc-100">
                @if ($isCbc)
                    <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-100">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Predominant Band</p>
                        <div class="flex items-center gap-2">
                            <span class="text-2xl font-black {{ $broadBand === 'EE' ? 'text-emerald-600' : ($broadBand === 'ME' ? 'text-blue-600' : ($broadBand === 'AE' ? 'text-amber-600' : 'text-rose-600')) }}">
                                {{ $meanCode ?? $broadBand }}
                            </span>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $bandColorClasses[$broadBand] ?? 'bg-zinc-100 text-zinc-700' }}">
                                {{ $broadBand === 'EE' ? 'Exceeding' : ($broadBand === 'ME' ? 'Meeting' : ($broadBand === 'AE' ? 'Approaching' : 'Below')) }}
                            </span>
                        </div>
                    </div>
                    <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-100">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Performance Profile</p>
                        <p class="text-2xl font-black text-indigo-600">
                            {{ $pointsDisplay !== null ? $pointsDisplay . ' / 8.0' : '—' }} <span class="text-xs font-semibold text-zinc-400">pts</span>
                        </p>
                    </div>
                    <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-100">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Learning Areas</p>
                        <p class="text-2xl font-black text-zinc-800">
                            {{ isset($rows) && $rows->isNotEmpty() ? $rows->count() : 'All' }} <span class="text-xs font-semibold text-zinc-400">assessed</span>
                        </p>
                    </div>
                    <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-100">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Grading System</p>
                        <p class="text-sm font-bold text-zinc-800 mt-1">
                            KNEC CBC 8-Point Rubric
                        </p>
                        <p class="text-[11px] text-zinc-400">Criterion-Referenced</p>
                    </div>
                @else
                    <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-100">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Mean Score</p>
                        <p class="text-2xl font-black text-indigo-600">
                            {{ $reportCard->mean_percentage !== null ? number_format($reportCard->mean_percentage, 1) . '%' : '—' }}
                        </p>
                    </div>
                    <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-100">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Mean Grade</p>
                        <p class="text-2xl font-black text-emerald-600">
                            {{ $reportCard->mean_grade ?? '—' }}
                        </p>
                    </div>
                    <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-100">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Became Ready</p>
                        <p class="text-sm font-semibold text-zinc-800 mt-1">
                            {{ $reportCard->completed_at ? $reportCard->completed_at->format('d M Y') : '—' }}
                        </p>
                    </div>
                    <div class="p-4 rounded-xl bg-zinc-50 border border-zinc-100">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-1">Parent Delivery</p>
                        <p class="text-sm font-semibold text-zinc-800 mt-1">
                            {{ $reportCard->sent_at ? $reportCard->sent_at->format('d M Y') : 'Not sent yet' }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- Assessment Results Details Table --}}
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="text-sm font-bold uppercase tracking-wider text-zinc-800">
                            {{ $isCbc ? 'Competency-Based Assessment Breakdown (Rubric Bands)' : 'Subject Breakdown & Marks' }}
                        </h4>
                        @if ($isCbc)
                            <p class="text-xs text-zinc-500">
                                Official CBC performance levels evaluated against continuous classroom assessments and KNEC learning standards.
                            </p>
                        @endif
                    </div>
                </div>

                @if ($isCbc && isset($rows) && $rows->isNotEmpty())
                    {{-- Pure CBC Table --}}
                    <div class="border border-zinc-200 rounded-xl overflow-x-auto">
                        <table class="w-full text-left text-sm min-w-[640px]">
                            <thead class="bg-zinc-50 text-zinc-600 border-b border-zinc-200 text-xs font-semibold uppercase">
                                <tr>
                                    <th class="py-3 px-4">Learning Area / Subject</th>
                                    <th class="py-3 px-4">Strand &amp; Sub-Strand</th>
                                    <th class="py-3 px-4">Expectation Band</th>
                                    <th class="py-3 px-4">Points</th>
                                    <th class="py-3 px-4">Teacher Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @forelse($rows as $row)
                                    @php
                                        $band = $row->expectationBand() ?? $row->grade();
                                        $prefix = $band ? substr($band, 0, 2) : 'ME';
                                        $rowInfo = \Modules\Result\Services\ResultGrader::CBC_SUB_LEVELS[$band] ?? null;
                                    @endphp
                                    <tr class="hover:bg-zinc-50/50">
                                        <td class="py-3.5 px-4 font-bold text-zinc-900">
                                            <span translate="no">{{ $row->name }}</span>
                                        </td>
                                        <td class="py-3.5 px-4 text-xs text-zinc-500">
                                            {{ $row->strand ?? ($row->learningArea ?? 'Core Learning Area') }}
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold {{ $bandColorClasses[$prefix] ?? 'bg-zinc-100 text-zinc-800' }}">
                                                <span class="size-1.5 rounded-full {{ $prefix === 'EE' ? 'bg-emerald-500' : ($prefix === 'ME' ? 'bg-blue-500' : ($prefix === 'AE' ? 'bg-amber-500' : 'bg-rose-500')) }}"></span>
                                                {{ $band ?? 'ME1' }}
                                                <span class="font-normal opacity-80 text-[11px]">&middot; {{ $prefix === 'EE' ? 'Exceeding' : ($prefix === 'ME' ? 'Meeting' : ($prefix === 'AE' ? 'Approaching' : 'Below')) }}</span>
                                            </span>
                                            @if ($row->marks !== null)
                                                <span class="block text-[10.5px] text-zinc-400 mt-0.5">
                                                    Raw score: {{ $row->marks }}/{{ $row->outOf }} ({{ number_format($row->percentage, 0) }}%)
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3.5 px-4 font-mono font-bold text-xs text-zinc-800">
                                            {{ $row->points() !== null ? $row->points() . ' / 8' : '—' }}
                                        </td>
                                        <td class="py-3.5 px-4 text-xs text-zinc-600">
                                            {{ $row->remark() ?? 'Demonstrates expected competency and learning autonomy.' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-6 text-zinc-400">No learning areas assessed yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    {{-- Fallback / 8-4-4 Table --}}
                    @php
                        $results = \Modules\Result\Models\Result::where('student_id', $reportCard->student_id)
                            ->whereHas('examination', function ($q) use ($reportCard) {
                                $q->where('term', $reportCard->term);
                                if ($reportCard->academic_year) {
                                    $q->where(function ($yq) use ($reportCard) {
                                        $yq->where('academic_year', $reportCard->academic_year)->orWhereNull('academic_year');
                                    });
                                }
                            })
                            ->with(['subject', 'examination.subject'])
                            ->get();
                    @endphp

                    <div class="border border-zinc-200 rounded-xl overflow-x-auto">
                        <table class="w-full text-left text-sm min-w-[560px]">
                            <thead class="bg-zinc-50 text-zinc-600 border-b border-zinc-200 text-xs font-semibold uppercase">
                                <tr>
                                    <th class="py-3 px-4">Subject</th>
                                    <th class="py-3 px-4">Score</th>
                                    <th class="py-3 px-4">Max Marks</th>
                                    <th class="py-3 px-4">Grade</th>
                                    <th class="py-3 px-4">Teacher Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @forelse($results as $res)
                                    <tr class="hover:bg-zinc-50/50">
                                        <td class="py-3 px-4 font-semibold text-zinc-900">
                                            {{ $res->subject?->name ?? ($res->examination?->subject?->name ?? ($res->examination?->subject_name ?? 'Subject')) }}
                                        </td>
                                        <td class="py-3 px-4 font-bold text-indigo-600">
                                            {{ $res->marks_obtained }}
                                        </td>
                                        <td class="py-3 px-4 text-zinc-500">
                                            {{ $res->examination?->total_marks ?? 100 }}
                                        </td>
                                        <td class="py-3 px-4">
                                            <flux:badge color="blue" size="sm" class="font-bold">
                                                {{ $res->grade ?? '—' }}
                                            </flux:badge>
                                        </td>
                                        <td class="py-3 px-4 text-xs text-zinc-600">
                                            {{ $res->remarks ?? '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-6 text-zinc-400">No recorded examination results.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Footer Action Bar --}}
            <div class="border-t border-zinc-100 px-6 py-4 bg-zinc-50 flex items-center justify-between">
                <span class="text-xs text-zinc-400">
                    <span translate="no">{{ $reportCard->institution?->name ?? (currentInstitution()?->name ?? config('app.name', 'Institution')) }}</span>
                    &middot; Academic Management
                </span>
                <div class="flex items-center gap-3">
                    <flux:button href="{{ route('reportcard.pdf', $reportCard->id) }}" target="_blank"
                        variant="primary" icon="printer" class="font-semibold shadow-sm">
                        Print / View Official PDF
                    </flux:button>
                </div>
            </div>
        </div>

    </div>
</x-layouts::app>
