<x-layouts::app :title="__('Report Card')">
    <div class="flex flex-col gap-6 w-full max-w-full mx-auto p-4 sm:p-6 lg:p-8">

        {{-- Top Navigation & Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('reportcard.index') }}" wire:navigate.hover
                    class="p-2 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-600 transition">
                    <flux:icon icon="arrow-left" class="size-5" />
                </a>
                <div>
                    <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
                        {{ $reportCard->student?->name }}</flux:heading>
                    <flux:text class="text-zinc-500 text-sm">
                        {{ $reportCard->schoolClass?->name }} &middot; {{ $reportCard->term }}
                        {{ $reportCard->academic_year }}
                    </flux:text>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <flux:button href="{{ route('reportcard.pdf', $reportCard->id) }}" target="_blank" variant="primary"
                    icon="printer" class="font-semibold shadow-sm px-4">
                    Print / View PDF
                </flux:button>
                <a href="{{ route('reportcard.index') }}" wire:navigate.hover
                    class="px-4 py-2 bg-white border border-zinc-300 rounded-lg text-sm font-medium text-zinc-700 hover:bg-zinc-50 transition">
                    Back to List
                </a>
            </div>
        </div>

        {{-- Main Report Card Summary Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-zinc-200 overflow-hidden">
            <div
                class="border-b border-zinc-100 px-6 py-5 bg-gradient-to-r from-zinc-50 to-white flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Academic Assessment</span>
                    <h3 class="text-base font-semibold text-zinc-900 mt-0.5">{{ $reportCard->term }} Final Performance
                        Report</h3>
                </div>
                <flux:badge :color="$reportCard->isSent() ? 'emerald' : 'amber'" size="md" class="font-bold">
                    {{ $reportCard->isSent() ? 'Sent to Parent' : 'Ready for Printing' }}
                </flux:badge>
            </div>

            <div class="p-6 grid grid-cols-2 sm:grid-cols-4 gap-6 border-b border-zinc-100">
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
            </div>

            {{-- Student Results Details Table --}}
            <div class="p-6">
                <h4 class="text-sm font-bold uppercase tracking-wider text-zinc-700 mb-4">Subject Breakdown & Marks</h4>

                @php
                    $results = \Modules\Result\Models\Result::where('student_id', $reportCard->student_id)
                        ->whereHas('examination', function ($q) use ($reportCard) {
                            $q->where('term', $reportCard->term);
                            if ($reportCard->academic_year) {
                                $q->where(function ($yq) use ($reportCard) {
                                    $yq->where('academic_year', $reportCard->academic_year)->orWhereNull(
                                        'academic_year',
                                    );
                                });
                            }
                        })
                        ->with(['subject', 'examination.subject'])
                        ->get();
                @endphp

                <div class="border border-zinc-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="bg-zinc-50 text-zinc-600 border-b border-zinc-200 text-xs font-semibold uppercase">
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
                                            {{ $res->grade ?? '—' }}</flux:badge>
                                    </td>
                                    <td class="py-3 px-4 text-xs text-zinc-600">
                                        {{ $res->remarks ?? '—' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-6 text-zinc-400">No recorded examination
                                        results.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Footer Action Bar --}}
            <div class="border-t border-zinc-100 px-6 py-4 bg-zinc-50 flex items-center justify-between">
                <span
                    class="text-xs text-zinc-400">{{ $reportCard->institution?->name ?? (currentInstitution()?->name ?? config('app.name', 'Institution')) }}
                    &middot; Academic Management</span>
                <div class="flex items-center gap-3">
                    <flux:button href="{{ route('reportcard.pdf', $reportCard->id) }}" target="_blank"
                        variant="primary" icon="printer" class="font-semibold shadow-sm">
                        Print / View Full PDF
                    </flux:button>
                </div>
            </div>
        </div>

    </div>
</x-layouts::app>
