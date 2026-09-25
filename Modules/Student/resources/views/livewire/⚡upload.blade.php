<?php

use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Classes\Models\SchoolClass;
use Modules\Student\Actions\ImportStudents;

new #[Title('Upload Student Records')] class extends Component {
    use WithFileUploads;

    public $file = null;

    /** @var array<int, array<string, string>> Grid rows keyed by ImportStudents::COLUMNS. */
    public array $rows = [];

    /** @var array<int, string> Per-row save failures, keyed by row index. */
    public array $rowErrors = [];

    public int $created = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('create student'), 403);

        $this->rows = array_fill(0, 10, $this->blankRow());
    }

    /**
     * An uploaded xlsx/csv fills the grid for review; nothing is saved
     * until the user hits Save.
     */
    public function updatedFile(): void
    {
        $this->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240'], [], ['file' => 'spreadsheet']);

        try {
            $parsed = app(ImportStudents::class)->parse($this->file->getRealPath());
        } catch (Throwable) {
            $this->addError('file', 'That file could not be read as a spreadsheet. Please upload an .xlsx or .csv file.');

            return;
        }

        if ($parsed === []) {
            $this->addError('file', 'No student rows were found in that file.');

            return;
        }

        // Parsed rows replace untouched blanks but never typed-in ones.
        $this->rows = array_values(array_merge($this->filledRows(), $parsed));
        $this->rowErrors = [];

        Flux::toast(text: count($parsed) . ' row(s) loaded from the file. Review them below, then press Save.', variant: 'success');
    }

    public function addRows(int $count = 5): void
    {
        $this->rows = array_merge($this->rows, array_fill(0, $count, $this->blankRow()));
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index], $this->rowErrors[$index]);
        $this->rows = array_values($this->rows);
        $this->rowErrors = [];
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('create student'), 403);

        $institutionId = currentInstitutionId();
        abort_unless($institutionId, 422, 'No institution selected.');

        if ($this->filledRows() === []) {
            Flux::toast(text: 'Nothing to save yet - fill in at least one student row.', variant: 'warning');

            return;
        }

        $result = app(ImportStudents::class)->import($this->rows, $institutionId);

        $this->created = $result['created'];
        $this->rowErrors = $result['errors'];

        // Saved rows leave the grid; failed ones stay put with their error
        // (both keep the original row order, so re-indexing keeps them aligned).
        $failed = array_intersect_key($this->rows, $result['errors']);
        $this->rows = $failed === [] ? array_fill(0, 10, $this->blankRow()) : array_values($failed);
        $this->rowErrors = array_values($result['errors']);
        $this->file = null;

        if ($result['errors'] === []) {
            session()->flash('success', $result['created'] . ' student record(s) created successfully.');
            $this->redirectRoute('student.index', navigate: true);

            return;
        }

        Flux::toast(text: $result['created'] . ' saved, ' . count($result['errors']) . ' row(s) need attention below.', variant: $result['created'] > 0 ? 'warning' : 'danger');
    }

    #[Computed]
    public function classes()
    {
        return SchoolClass::when(!isAdmin(), fn($q) => $q->where('institution_id', currentInstitutionId()))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function filledRows(): array
    {
        return array_values(array_filter($this->rows, fn($row) => trim(implode('', $row)) !== ''));
    }

    /**
     * @return array<string, string>
     */
    private function blankRow(): array
    {
        return array_fill_keys(ImportStudents::COLUMNS, '');
    }
}; ?>

<div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

    {{-- Header with the Save action kept at the top, spreadsheet-style --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Upload Student
                Records</flux:heading>
            <flux:text class="text-zinc-500 text-sm mt-1">
                Upload an .xlsx or .csv file, or type students straight into the sheet below, then press Save.
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            <flux:button href="{{ route('student.index') }}" wire:navigate.hover icon="arrow-left" variant="subtle"
                class="font-semibold shadow-sm">
                Back to Directory
            </flux:button>
            <flux:button wire:click="save" wire:loading.attr="disabled" icon="check" variant="primary"
                class="font-semibold shadow-sm px-6">
                <span wire:loading.remove wire:target="save">Save All Students</span>
                <span wire:loading wire:target="save">Saving...</span>
            </flux:button>
        </div>
    </div>

    {{-- How many students the plan allows before the upload hits the ceiling. --}}
    <x-student-limit-banner />

    {{-- Step 1: the file drop zone --}}
    <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-5">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex items-center gap-3 flex-1">
                <div
                    class="size-11 rounded-xl bg-sky-50 dark:bg-sky-900/30 border border-sky-200/60 dark:border-sky-800 flex items-center justify-center shrink-0">
                    <flux:icon icon="arrow-up-tray" class="size-5 text-sky-600" />
                </div>
                <div>
                    <h4 class="text-sm font-bold text-zinc-900 dark:text-white">Import from a spreadsheet</h4>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        Columns: <span class="font-mono">Student Name, Admission No, Student No, Class, Gender,
                            Guardian, Guardian Phone, Status</span>
                        - the same layout the directory export produces.
                    </p>
                </div>
            </div>
            <div class="shrink-0">
                <input type="file" wire:model="file" accept=".xlsx,.xls,.csv"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 border border-zinc-200 dark:border-zinc-700 rounded-lg focus:outline-none cursor-pointer bg-white dark:bg-zinc-800 transition-colors" />
                <div wire:loading wire:target="file" class="text-xs text-sky-600 font-semibold mt-1">Reading file...
                </div>
            </div>
        </div>
        @error('file')
            <p class="text-xs font-semibold text-rose-600 mt-3">{{ $message }}</p>
        @enderror
    </div>

    {{-- Step 2: the editable sheet --}}
    <div
        class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col">
        <div
            class="px-5 py-3 border-b border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-950/40 flex items-center justify-between">
            <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">
                Student Sheet
                <span
                    class="ml-2 font-medium normal-case tracking-normal text-zinc-400">{{ count(array_filter($this->rows, fn($r) => trim(implode('', $r)) !== '')) }}
                    filled / {{ count($this->rows) }} rows</span>
            </div>
            <flux:button wire:click="addRows" size="sm" icon="plus" variant="subtle" class="font-semibold">
                Add 5 Rows
            </flux:button>
        </div>

        <div class="overflow-auto max-h-[60vh]">
            <table class="w-full min-w-[1050px] text-left text-sm border-collapse">
                <thead
                    class="sticky top-0 z-10 bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 text-[11px] font-bold uppercase tracking-wider shadow-sm">
                    <tr class="divide-x divide-zinc-200 dark:divide-zinc-700">
                        <th class="py-2.5 px-3 w-10 text-center text-zinc-400">#</th>
                        <th class="py-2.5 px-3 min-w-[200px]">Student Name <span class="text-rose-500">*</span></th>
                        <th class="py-2.5 px-3 min-w-[130px]">Admission No</th>
                        <th class="py-2.5 px-3 min-w-[130px]">Student No</th>
                        <th class="py-2.5 px-3 min-w-[150px]">Class</th>
                        <th class="py-2.5 px-3 min-w-[110px]">Gender</th>
                        <th class="py-2.5 px-3 min-w-[170px]">Guardian</th>
                        <th class="py-2.5 px-3 min-w-[140px]">Guardian Phone</th>
                        <th class="py-2.5 px-3 min-w-[130px]">Status</th>
                        <th class="py-2.5 px-3 w-12"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($rows as $index => $row)
                        @php $cell = 'w-full bg-transparent border-0 focus:ring-2 focus:ring-sky-500 rounded-none px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-300 dark:placeholder:text-zinc-600'; @endphp
                        <tr wire:key="row-{{ $index }}"
                            class="divide-x divide-zinc-100 dark:divide-zinc-800 {{ isset($rowErrors[$index]) ? 'bg-rose-50/60 dark:bg-rose-900/10' : 'hover:bg-sky-50/40 dark:hover:bg-zinc-800/40' }}">
                            <td
                                class="py-0 px-2 text-center text-xs font-mono text-zinc-400 bg-zinc-50/80 dark:bg-zinc-950/30 select-none">
                                {{ $index + 1 }}</td>
                            <td class="p-0">
                                <input type="text" wire:model="rows.{{ $index }}.name"
                                    placeholder="e.g. Student name" class="{{ $cell }}" />
                                @if (isset($rowErrors[$index]))
                                    <p class="text-[11px] font-semibold text-rose-600 px-3 pb-1.5">
                                        {{ $rowErrors[$index] }}</p>
                                @endif
                            </td>
                            <td class="p-0"><input type="text"
                                    wire:model="rows.{{ $index }}.admission_number" placeholder="fhn-1-2025"
                                    class="{{ $cell }}" /></td>
                            <td class="p-0"><input type="text"
                                    wire:model="rows.{{ $index }}.student_number" placeholder="STU-001"
                                    class="{{ $cell }}" /></td>
                            <td class="p-0">
                                <select wire:model="rows.{{ $index }}.class_name"
                                    class="{{ $cell }} cursor-pointer">
                                    <option value="">Unassigned</option>
                                    @foreach ($this->classes as $schoolClass)
                                        <option value="{{ $schoolClass->name }}" @selected(strcasecmp($row['class_name'] ?? '', $schoolClass->name) === 0)>
                                            {{ $schoolClass->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="p-0">
                                <select wire:model="rows.{{ $index }}.gender"
                                    class="{{ $cell }} cursor-pointer">
                                    <option value="">-</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </td>
                            <td class="p-0"><input type="text"
                                    wire:model="rows.{{ $index }}.guardian_name" placeholder="Guardian name"
                                    class="{{ $cell }}" /></td>
                            <td class="p-0"><input type="text"
                                    wire:model="rows.{{ $index }}.guardian_phone" placeholder="0700 000 000"
                                    class="{{ $cell }}" /></td>
                            <td class="p-0">
                                <select wire:model="rows.{{ $index }}.enrollment_status"
                                    class="{{ $cell }} cursor-pointer">
                                    <option value="">Active (default)</option>
                                    <option value="active">Active</option>
                                    <option value="graduated">Graduated</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="transferred">Transferred</option>
                                    <option value="withdrawn">Withdrawn</option>
                                    <option value="dropped">Dropped</option>
                                </select>
                            </td>
                            <td class="p-0 text-center">
                                <button type="button" wire:click="removeRow({{ $index }})"
                                    title="Remove row"
                                    class="p-2 text-zinc-300 hover:text-rose-500 transition-colors">
                                    <flux:icon icon="x-mark" class="size-4" />
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div
            class="border-t border-zinc-200/80 dark:border-zinc-800 px-5 py-3 bg-zinc-50/50 dark:bg-zinc-950/40 flex items-center justify-between text-xs text-zinc-500">
            <span>Empty rows are ignored. A login email and password are generated automatically for each
                student.</span>
            <flux:button wire:click="addRows" size="sm" icon="plus" variant="ghost" class="font-semibold">
                Add 5 Rows
            </flux:button>
        </div>
    </div>
</div>
