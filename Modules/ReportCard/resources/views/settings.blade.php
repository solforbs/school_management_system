<x-layouts::app :title="__('Report Card Settings')">
    <div class="flex flex-col gap-8 w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8" x-data="{
        editingBand: null,
        openEdit(band) {
            this.editingBand = JSON.parse(JSON.stringify(band));
        },
        closeEdit() {
            this.editingBand = null;
        }
    }">

        {{-- Top Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <a href="{{ route('reportcard.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-zinc-500 hover:text-zinc-900 transition">
                        <flux:icon icon="arrow-left" class="size-3.5" />
                        <span>Back to Report Cards</span>
                    </a>
                    <span class="text-xs text-zinc-300">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">Evaluation Configuration</span>
                </div>
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900">Report Card Settings</flux:heading>
                <flux:text class="text-zinc-500 text-sm mt-0.5">
                    Configure institutional grading percentage scales and customize terminal report templates.
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                <flux:button href="{{ route('reportcard.index') }}" variant="subtle" icon="arrow-left">
                    Back to Report Cards
                </flux:button>
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

        @if (! $institution)
            <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-6 flex items-start gap-4">
                <flux:icon icon="exclamation-triangle" class="size-6 text-amber-600 mt-0.5 shrink-0" />
                <div>
                    <h4 class="text-sm font-bold text-amber-900">No Active Institution Selected</h4>
                    <p class="text-xs text-amber-700 mt-1">Please select an active institution before modifying report card settings.</p>
                </div>
            </div>
        @else
            <x-stepper.layout>

                {{-- Section 1: Grading Scale --}}
                <x-stepper.section id="grading" title="Grading Scale" description="Define the percentage bands used to compute each student's grade and performance remark.">
                    
                    @if ($gradingBands->isNotEmpty())
                        <div class="bg-white border border-zinc-200/80 rounded-2xl overflow-hidden shadow-sm mb-6">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-zinc-50 text-zinc-500 border-b border-zinc-200/80 text-xs font-bold uppercase tracking-wider">
                                        <tr>
                                            <th class="py-3.5 px-5">Percentage Range</th>
                                            <th class="py-3.5 px-5">Grade</th>
                                            <th class="py-3.5 px-5">Evaluation Remark</th>
                                            <th class="py-3.5 px-5 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100">
                                        @foreach ($gradingBands as $band)
                                            <tr class="hover:bg-zinc-50/60 transition-colors">
                                                <td class="py-3.5 px-5">
                                                    <span class="font-mono text-xs font-bold text-zinc-900">
                                                        {{ number_format($band->min_percentage, 1) }}% &ndash; {{ number_format($band->max_percentage, 1) }}%
                                                    </span>
                                                </td>
                                                <td class="py-3.5 px-5">
                                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold font-mono
                                                        {{ in_array($band->grade, ['A', 'A-']) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                                           (in_array($band->grade, ['B+', 'B', 'B-']) ? 'bg-blue-50 text-blue-700 border border-blue-200' :
                                                           (in_array($band->grade, ['C+', 'C', 'C-']) ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-zinc-100 text-zinc-700 border border-zinc-200')) }}">
                                                        {{ $band->grade }}
                                                    </span>
                                                </td>
                                                <td class="py-3.5 px-5 text-xs text-zinc-600 italic">
                                                    {{ $band->remark ?? '—' }}
                                                </td>
                                                <td class="py-3.5 px-5 text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <form action="{{ route('reportcard.gradingbands.destroy', $band->id) }}" method="POST" class="inline" onsubmit="return confirm('Remove this grading band ({{ $band->grade }})?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <flux:button type="submit" size="sm" icon="trash" variant="subtle" class="text-rose-500 hover:text-rose-600 hover:bg-rose-50" tooltip="Delete Band" />
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-12 mb-6 bg-zinc-50 border border-zinc-200 rounded-2xl text-center">
                            <flux:icon icon="chart-bar" class="size-8 text-zinc-400 mb-2" />
                            <h3 class="text-sm font-bold text-zinc-900">No Grading Bands Configured</h3>
                            <p class="text-zinc-500 text-xs mt-1">Add your institutional grading bands below.</p>
                        </div>
                    @endif

                    {{-- Add New Band Form --}}
                    <div class="bg-zinc-50 border border-zinc-200/80 rounded-2xl p-5">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-zinc-700 mb-4 flex items-center gap-2">
                            <flux:icon icon="plus-circle" class="size-4 text-zinc-900" />
                            <span>Add Grading Band</span>
                        </h4>
                        <form action="{{ route('reportcard.gradingbands.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                            @csrf
                            <input type="hidden" name="institution_id" value="{{ $institution->id }}">

                            <div>
                                <label class="text-[11px] font-bold text-zinc-600 block mb-1">Min %</label>
                                <input type="number" step="0.01" name="min_percentage" min="0" max="100" placeholder="0.00" required
                                    class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-xl text-xs font-mono focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                            </div>

                            <div>
                                <label class="text-[11px] font-bold text-zinc-600 block mb-1">Max %</label>
                                <input type="number" step="0.01" name="max_percentage" min="0" max="100" placeholder="100.00" required
                                    class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-xl text-xs font-mono focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                            </div>

                            <div>
                                <label class="text-[11px] font-bold text-zinc-600 block mb-1">Grade</label>
                                <input type="text" name="grade" maxlength="10" placeholder="e.g. A, B+" required
                                    class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                            </div>

                            <div>
                                <label class="text-[11px] font-bold text-zinc-600 block mb-1">Remark</label>
                                <input type="text" name="remark" placeholder="e.g. Exceeds Expectations"
                                    class="w-full px-3 py-2 bg-white border border-zinc-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-zinc-900 transition">
                            </div>
                            
                            <flux:button type="submit" variant="primary" icon="plus" class="w-full text-xs font-bold py-2 shadow-sm">
                                Add Band
                            </flux:button>
                        </form>
                    </div>
                </x-stepper.section>

                {{-- Section 2: Report Template --}}
                <x-stepper.section id="template" title="Report Card Template" description="Customize opening statements, closing remarks, and signatory headers printed on PDF report cards.">
                    
                    <div class="bg-zinc-50 border border-zinc-200/80 rounded-2xl p-4 mb-6">
                        <div class="flex items-start gap-3">
                            <flux:icon icon="information-circle" class="size-4 text-zinc-600 mt-0.5 shrink-0" />
                            <div class="text-xs text-zinc-600">
                                <p class="font-bold text-zinc-900 mb-1.5">Dynamic Document Placeholders</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <code class="px-2 py-0.5 bg-white rounded-md border border-zinc-200 font-mono text-[11px] font-bold text-zinc-800">@{{student_name}}</code>
                                    <code class="px-2 py-0.5 bg-white rounded-md border border-zinc-200 font-mono text-[11px] font-bold text-zinc-800">@{{institution_name}}</code>
                                    <code class="px-2 py-0.5 bg-white rounded-md border border-zinc-200 font-mono text-[11px] font-bold text-zinc-800">@{{class_name}}</code>
                                    <code class="px-2 py-0.5 bg-white rounded-md border border-zinc-200 font-mono text-[11px] font-bold text-zinc-800">@{{term}}</code>
                                    <code class="px-2 py-0.5 bg-white rounded-md border border-zinc-200 font-mono text-[11px] font-bold text-zinc-800">@{{mean_percentage}}</code>
                                    <code class="px-2 py-0.5 bg-white rounded-md border border-zinc-200 font-mono text-[11px] font-bold text-zinc-800">@{{mean_grade}}</code>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('reportcard.settings.template') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="institution_id" value="{{ $institution->id }}">

                        <div class="md:col-span-2">
                            <flux:textarea name="opening_text" rows="3" label="Opening Certification Text" placeholder="e.g. This is to certify that @{{student_name}} has completed academic evaluation for @{{term}}...">{{ old('opening_text', $template->opening_text) }}</flux:textarea>
                        </div>

                        <div class="md:col-span-2">
                            <flux:textarea name="closing_text" rows="3" label="Principal / Closing Remarks" placeholder="e.g. Congratulations on a commendable performance in @{{term}}! Keep up the diligence.">{{ old('closing_text', $template->closing_text) }}</flux:textarea>
                        </div>

                        <flux:input type="text" name="signatory_name" label="Authorized Signatory Name" value="{{ old('signatory_name', $template->signatory_name) }}" placeholder="e.g. Dr. Jane Doe" />
                        <flux:input type="text" name="signatory_title" label="Signatory Title" value="{{ old('signatory_title', $template->signatory_title) }}" placeholder="e.g. Principal / Head of School" />

                        <div class="md:col-span-2 flex justify-end mt-2">
                            <flux:button type="submit" variant="primary" icon="check" class="px-6 font-bold shadow-sm">
                                Save Report Card Template
                            </flux:button>
                        </div>
                    </form>
                </x-stepper.section>

            </x-stepper.layout>
        @endif
    </div>
</x-layouts::app>