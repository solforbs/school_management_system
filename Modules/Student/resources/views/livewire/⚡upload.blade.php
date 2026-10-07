<?php

use App\Models\Devices;
use App\Models\User;
use App\Services\StudentCacheService;
use App\Services\ZKTecoUserSyncService;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Classes\Models\SchoolClass;
use Modules\Student\Actions\ImportStudents;
use Modules\Student\Actions\SaveStudent;
use Modules\Student\Models\StudentDetails;

new #[Title('Upload Student Records')] class extends Component
{
    use WithFileUploads;

    public $file = null;

    /** @var array<int, array<string, string>> Grid rows keyed by ImportStudents::COLUMNS. */
    public array $rows = [];

    /** @var array<int, string> Per-row save failures, keyed by row index. */
    public array $rowErrors = [];

    public int $created = 0;

    public ?int $selectedClassId = null;

    public ?int $pendingClassId = null;

    public string $activeTab = 'grid'; // 'grid' | 'excel'

    public bool $showEnrolledRoster = true;

    public bool $showExtraColumns = false;

    public string $rosterSearch = '';

    public bool $syncToBiometrics = true;

    public bool $isEditingRoster = false;

    /** @var array<string|int, array<string, mixed>> */
    public array $rosterEdits = [];

    /** @var array<string|int, array<string, string>> */
    public array $rosterErrors = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('create student'), 403);

        $this->rows = array_fill(0, 10, $this->blankRow());

        // Auto-select first class if one exists
        $firstClass = $this->classes->first();
        if ($firstClass) {
            $this->selectedClassId = $firstClass->id;
            $this->applyDefaultClassToRows($firstClass->name);
        }
    }

    public function updatedSelectedClassId($value): void
    {
        $class = $this->classes->firstWhere('id', (int) $value);
        if ($class) {
            $this->applyDefaultClassToRows($class->name);
        }
    }

    public function onClassSelectChange($newClassId): void
    {
        $newClassId = ($newClassId === '' || $newClassId === null) ? null : (int) $newClassId;

        if ($newClassId === $this->selectedClassId) {
            return;
        }

        if ($this->hasUnsavedChanges()) {
            $this->pendingClassId = $newClassId;
            Flux::modal('confirm-switch-class')->show();

            return;
        }

        $this->switchClass($newClassId);
    }

    public function cancelSwitchClass(): void
    {
        $this->pendingClassId = null;
        Flux::modal('confirm-switch-class')->close();
    }

    public function saveAndSwitchClass(): void
    {
        $targetClassId = $this->pendingClassId;
        $this->save();

        if ($this->rowErrors === []) {
            $this->switchClass($targetClassId);
            Flux::modal('confirm-switch-class')->close();
        }
    }

    public function discardAndSwitchClass(): void
    {
        $targetClassId = $this->pendingClassId;
        $targetClass = $this->classes->firstWhere('id', (int) $targetClassId);
        $defaultClassName = $targetClass?->name ?? '';

        $this->rows = array_map(function () use ($defaultClassName) {
            $row = array_fill_keys(ImportStudents::COLUMNS, '');
            $row['class_name'] = $defaultClassName;

            return $row;
        }, array_fill(0, 10, null));

        $this->rowErrors = [];
        $this->dispatch('clear-bulk-draft');
        $this->switchClass($targetClassId);
        Flux::modal('confirm-switch-class')->close();
        Flux::toast(text: 'Unsaved rows cleared. Switched to '.($targetClass?->name ?? 'class').'.', variant: 'info');
    }

    public function switchClass(?int $classId): void
    {
        $this->selectedClassId = $classId;
        $this->pendingClassId = null;
        $this->isEditingRoster = false;
        $this->rosterEdits = [];
        $this->rosterErrors = [];

        $class = $this->classes->firstWhere('id', (int) $classId);
        if ($class) {
            $this->applyDefaultClassToRows($class->name);
        }
    }

    public function hasUnsavedChanges(): bool
    {
        return count($this->filledRows()) > 0;
    }

    private function applyDefaultClassToRows(string $className): void
    {
        foreach ($this->rows as $i => $row) {
            $isFilled = trim($row['name'] ?? '') !== '' || trim($row['admission_number'] ?? '') !== '';
            if (! $isFilled || empty($row['class_name'])) {
                $this->rows[$i]['class_name'] = $className;
            }
        }
    }

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

        $this->rows = array_values(array_merge($this->filledRows(), $parsed));
        $this->rowErrors = [];
        $this->activeTab = 'grid'; // switch back to grid to review loaded rows

        Flux::toast(text: count($parsed).' row(s) loaded from spreadsheet. Review them below, then press Save.', variant: 'success');
    }

    public function addRows(int $count = 5): void
    {
        $newRows = array_fill(0, $count, $this->blankRow());
        $this->rows = array_merge($this->rows, $newRows);
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index], $this->rowErrors[$index]);
        $this->rows = array_values($this->rows);
        $this->rowErrors = [];
    }

    public function restoreDraft(array $draftRows): void
    {
        if (! empty($draftRows)) {
            $normalised = [];
            foreach ($draftRows as $draft) {
                if (! is_array($draft)) {
                    continue;
                }

                $row = $this->blankRow();
                foreach (ImportStudents::COLUMNS as $col) {
                    if (isset($draft[$col]) && $draft[$col] !== null) {
                        $row[$col] = (string) $draft[$col];
                    }
                }

                if (trim($row['name']) !== '' || trim($row['admission_number']) !== '' || trim($row['student_number']) !== '') {
                    $normalised[] = $row;
                }
            }

            if (! empty($normalised)) {
                $count = count($normalised);
                while (count($normalised) < 10) {
                    $normalised[] = $this->blankRow();
                }

                $this->rows = $normalised;
                $this->rowErrors = [];
                $this->activeTab = 'grid';
                Flux::toast(text: "{$count} draft student row(s) restored successfully.", variant: 'success');

                return;
            }
        }

        Flux::toast(text: 'No restorable student data was found in the session draft.', variant: 'warning');
    }

    public function discardDraft(): void
    {
        $this->rows = array_fill(0, 10, $this->blankRow());
        $this->rowErrors = [];
        $this->dispatch('clear-bulk-draft');
        Flux::toast(text: 'Session draft discarded.', variant: 'info');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('create student'), 403);

        $institutionId = currentInstitutionId();
        abort_unless($institutionId, 422, 'No institution selected.');

        if ($this->filledRows() === []) {
            Flux::toast(text: 'Nothing to save yet - fill in at least one student name.', variant: 'warning');

            return;
        }

        $result = app(ImportStudents::class)->import($this->rows, $institutionId);

        $this->created = $result['created'];
        $this->rowErrors = $result['errors'];

        $biometricSummary = '';
        if ($this->syncToBiometrics && ! empty($result['created_ids']) && $this->activeDevices->isNotEmpty()) {
            $syncResult = app(ZKTecoUserSyncService::class)->bulkSyncStudents($result['created_ids'], $institutionId);
            if ($syncResult['synced'] > 0) {
                $biometricSummary = " and queued {$syncResult['synced']} student(s) to {$syncResult['devices_count']} biometric device(s)";
            }
        }

        $failed = array_intersect_key($this->rows, $result['errors']);
        $this->rows = $failed === [] ? array_fill(0, 5, $this->blankRow()) : array_values($failed);
        $this->rowErrors = array_values($result['errors']);
        $this->file = null;

        if ($result['errors'] === []) {
            session()->flash('success', $result['created'].' student record(s) created successfully'.$biometricSummary.'.');
            $this->dispatch('clear-bulk-draft');
            $this->redirectRoute('student.index', navigate: true);

            return;
        }

        Flux::toast(text: $result['created'].' saved'.$biometricSummary.', '.count($result['errors']).' row(s) need attention below.', variant: $result['created'] > 0 ? 'warning' : 'danger');
    }

    #[Computed]
    public function activeDevices()
    {
        return Devices::where('institution_id', currentInstitutionId() ?? 0)
            ->where('is_active', true)
            ->get();
    }

    #[Computed]
    public function classes()
    {
        $institutionId = currentInstitutionId();
        if (! $institutionId) {
            return collect();
        }

        return app(StudentCacheService::class)->getClasses($institutionId, isAdmin());
    }

    #[Computed]
    public function selectedClass(): ?SchoolClass
    {
        if (! $this->selectedClassId) {
            return null;
        }

        return $this->classes->firstWhere('id', (int) $this->selectedClassId);
    }

    #[Computed]
    public function pendingClass(): ?SchoolClass
    {
        if (! $this->pendingClassId) {
            return null;
        }

        return $this->classes->firstWhere('id', (int) $this->pendingClassId);
    }

    #[Computed]
    public function classStats(): array
    {
        $emptyStats = [
            'total' => 0,
            'male' => 0,
            'female' => 0,
            'latest_admission' => 'None',
            'school_latest_admission' => 'None',
        ];

        $institutionId = currentInstitutionId();
        if (! $institutionId) {
            return $emptyStats;
        }

        if (! $this->selectedClassId) {
            return app(StudentCacheService::class)->getInstitutionStats($institutionId);
        }


        return app(StudentCacheService::class)->getClassStats($institutionId, (int) $this->selectedClassId);
    }

    #[Computed]
    public function classRoster()
    {
        $institutionId = currentInstitutionId();
        if (! $institutionId) {
            return collect();
        }

        if ($this->rosterSearch !== '') {
            $term = '%'.$this->rosterSearch.'%';

            return StudentDetails::query()
                ->where('institution_id', $institutionId)
                ->when($this->selectedClassId, fn ($q, $classId) => $q->where('class_id', $classId))
                ->with('student')
                ->where(function ($sub) use ($term) {
                    $sub->where('admission_number', 'like', $term)
                        ->orWhere('student_number', 'like', $term)
                        ->orWhereHas('student', fn ($u) => $u->where('name', 'like', $term));
                })
                ->orderBy('admission_number')
                ->get();
        }

        if (! $this->selectedClassId) {
            return app(StudentCacheService::class)->getInstitutionRoster($institutionId);
        }

        $cacheKey = "inst_{$institutionId}_class_{$this->selectedClassId}_roster";

        $cached = Cache::get($cacheKey);
        if ($cached instanceof \Illuminate\Support\Collection) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget($cacheKey);
        }

        $roster = StudentDetails::query()
            ->where('institution_id', $institutionId)
            ->where('class_id', $this->selectedClassId)
            ->with('student')
            ->orderBy('admission_number')
            ->get();

        Cache::put($cacheKey, $roster, 1800);

        return $roster;
    }

    private function filledRows(): array
    {
        return array_values(array_filter($this->rows, function ($row) {
            return trim($row['name'] ?? '') !== '' || trim($row['admission_number'] ?? '') !== '' || trim($row['student_number'] ?? '') !== '';
        }));
    }

    public function updateEnrolledField(int $studentDetailsId, string $field, ?string $value): void
    {
        abort_unless(auth()->user()->can('edit student') || auth()->user()->can('create student'), 403);

        $institutionId = currentInstitutionId();
        $student = StudentDetails::where('id', $studentDetailsId)
            ->where('institution_id', $institutionId)
            ->with('student')
            ->first();

        if (! $student) {
            return;
        }

        $value = trim($value ?? '');

        if ($field === 'name') {
            if ($value === '') {
                Flux::toast(text: 'Student name cannot be empty.', variant: 'danger');

                return;
            }
            $student->student?->update(['name' => $value]);
        } elseif ($field === 'admission_number') {
            if ($value === '') {
                Flux::toast(text: 'Admission number cannot be empty. Previous value kept.', variant: 'danger');
                $this->js('$wire.$refresh()');

                return;
            }

            $exists = StudentDetails::withTrashed()
                ->where('admission_number', $value)
                ->where('id', '!=', $student->id)
                ->exists();

            if ($exists) {
                Flux::toast(text: "Admission number '{$value}' is already used by another student.", variant: 'danger');
                $this->js('$wire.$refresh()');

                return;
            }
            $student->update(['admission_number' => $value]);
        } elseif ($field === 'student_number') {
            if ($value !== '') {
                $exists = StudentDetails::where('institution_id', $institutionId)
                    ->where('student_number', $value)
                    ->where('id', '!=', $student->id)
                    ->exists();

                if ($exists) {
                    Flux::toast(text: "Student ID '{$value}' is already used by another student.", variant: 'danger');

                    return;
                }
            }
            $student->update(['student_number' => $value ?: null]);
        } elseif ($field === 'gender') {
            $genderVal = in_array(strtolower($value), ['male', 'female', 'other'], true) ? strtolower($value) : null;
            $student->update(['gender' => $genderVal]);
        } elseif ($field === 'guardian_name') {
            $student->update(['guardian_name' => $value ?: null]);
            app(SaveStudent::class)->syncParentFromGuardian($student->refresh());
        } elseif ($field === 'guardian_phone') {
            $student->update(['guardian_phone' => $value ?: null]);
            app(SaveStudent::class)->syncParentFromGuardian($student->refresh());
        }

        if ($this->syncToBiometrics && $student->student_id && in_array($field, ['name', 'admission_number', 'student_number'], true)) {
            app(ZKTecoUserSyncService::class)->bulkSyncStudents(
                [$student->student_id],
                $institutionId,
                force: true
            );
        }

        app(StudentCacheService::class)->invalidateClass($institutionId, $student->class_id ? (int) $student->class_id : null);

        Flux::toast(text: 'Updated '.$student->student?->name.'.', variant: 'success');
    }

    public function startRosterEditMode(?string $focusRowId = null): void
    {
        abort_unless(auth()->user()->can('edit student') || auth()->user()->can('create student'), 403);

        $this->showEnrolledRoster = true;
        $this->isEditingRoster = true;
        $this->rosterErrors = [];
        $this->rosterEdits = [];

        foreach ($this->classRoster as $student) {
            $this->rosterEdits[$student->id] = [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'is_new' => false,
                'name' => $student->student?->name ?? '',
                'admission_number' => $student->admission_number ?? '',
                'student_number' => $student->student_number ?? '',
                'gender' => $student->gender ?? '',
                'guardian_name' => $student->guardian_name ?? '',
                'guardian_phone' => $student->guardian_phone ?? '',
                'enrollment_status' => $student->enrollment_status ?? ($student->is_active ? 'active' : 'inactive'),
            ];
        }
    }

    public function cancelRosterEditMode(): void
    {
        $this->isEditingRoster = false;
        $this->rosterEdits = [];
        $this->rosterErrors = [];
    }

    public function addRosterEditRow(): void
    {
        if (! $this->isEditingRoster) {
            $this->startRosterEditMode();
        }

        $tempKey = 'new_'.Str::random(8);
        $this->rosterEdits[$tempKey] = [
            'id' => null,
            'student_id' => null,
            'is_new' => true,
            'name' => '',
            'admission_number' => '',
            'student_number' => '',
            'gender' => '',
            'guardian_name' => '',
            'guardian_phone' => '',
            'enrollment_status' => 'active',
        ];
    }

    public function removeRosterEditRow(string $key): void
    {
        unset($this->rosterEdits[$key], $this->rosterErrors[$key]);
    }

    public function resetRosterRow(int $detailsId): void
    {
        $original = StudentDetails::with('student')->find($detailsId);
        if ($original) {
            $this->rosterEdits[$detailsId] = [
                'id' => $original->id,
                'student_id' => $original->student_id,
                'is_new' => false,
                'name' => $original->student?->name ?? '',
                'admission_number' => $original->admission_number ?? '',
                'student_number' => $original->student_number ?? '',
                'gender' => $original->gender ?? '',
                'guardian_name' => $original->guardian_name ?? '',
                'guardian_phone' => $original->guardian_phone ?? '',
                'enrollment_status' => $original->enrollment_status ?? ($original->is_active ? 'active' : 'inactive'),
            ];
            unset($this->rosterErrors[$detailsId]);
        }
    }

    public function saveRosterEdits(): void
    {
        abort_unless(auth()->user()->can('edit student') || auth()->user()->can('create student'), 403);

        $institutionId = currentInstitutionId();
        abort_unless($institutionId, 422, 'No institution selected.');

        if (empty($this->rosterEdits)) {
            $this->cancelRosterEditMode();

            return;
        }

        $this->rosterErrors = [];
        $seenAdmissions = [];
        $seenStudentNos = [];

        // Validation pass
        foreach ($this->rosterEdits as $key => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $adm = trim((string) ($row['admission_number'] ?? ''));
            $stuNo = trim((string) ($row['student_number'] ?? ''));
            $isNew = ! empty($row['is_new']);

            // Skip completely empty new rows
            if ($isNew && $name === '' && $adm === '' && $stuNo === '') {
                continue;
            }

            if ($name === '') {
                $this->rosterErrors[$key]['name'] = 'Student name is required.';
            }

            // Check admission number uniqueness
            if ($adm !== '') {
                if (isset($seenAdmissions[$adm])) {
                    $this->rosterErrors[$key]['admission_number'] = "Duplicate admission number '{$adm}' in this form.";
                } else {
                    $seenAdmissions[$adm] = $key;
                }

                $query = StudentDetails::withTrashed()
                    ->where('admission_number', $adm);
                if (! $isNew && ! empty($row['id'])) {
                    $query->where('id', '!=', (int) $row['id']);
                }

                if ($query->exists()) {
                    $this->rosterErrors[$key]['admission_number'] = "Admission number '{$adm}' already belongs to another student.";
                }
            }

            // Check student number uniqueness
            if ($stuNo !== '') {
                if (isset($seenStudentNos[$stuNo])) {
                    $this->rosterErrors[$key]['student_number'] = "Duplicate student ID '{$stuNo}' in this form.";
                } else {
                    $seenStudentNos[$stuNo] = $key;
                }

                $query = StudentDetails::where('institution_id', $institutionId)
                    ->where('student_number', $stuNo);
                if (! $isNew && ! empty($row['id'])) {
                    $query->where('id', '!=', (int) $row['id']);
                }

                if ($query->exists()) {
                    $this->rosterErrors[$key]['student_number'] = "Student ID '{$stuNo}' already in use.";
                }
            }
        }

        if (! empty($this->rosterErrors)) {
            Flux::toast(text: 'Please correct the highlighted validation errors.', variant: 'danger');

            return;
        }

        $updatedCount = 0;
        $createdCount = 0;
        $syncedStudentUserIds = [];

        DB::transaction(function () use (&$updatedCount, &$createdCount, &$syncedStudentUserIds, $institutionId) {
            foreach ($this->rosterEdits as $key => $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $adm = trim((string) ($row['admission_number'] ?? ''));
                $stuNo = trim((string) ($row['student_number'] ?? ''));
                $gender = trim((string) ($row['gender'] ?? ''));
                $genderVal = in_array(strtolower($gender), ['male', 'female', 'other'], true) ? strtolower($gender) : null;
                $guardianName = trim((string) ($row['guardian_name'] ?? ''));
                $guardianPhone = trim((string) ($row['guardian_phone'] ?? ''));
                $isNew = ! empty($row['is_new']);

                if ($isNew) {
                    if ($name === '' && $adm === '' && $stuNo === '') {
                        continue;
                    }

                    $admToUse = $adm !== '' ? $adm : null;

                    $newStudent = app(SaveStudent::class)->create([
                        'name' => $name,
                        'email' => strtolower(Str::slug($name)).'_'.uniqid().'@students.generated',
                        'password' => Str::random(16),
                        'admission_number' => $admToUse,
                        'student_number' => $stuNo ?: null,
                        'class_id' => $this->selectedClassId,
                        'gender' => $genderVal,
                        'guardian_name' => $guardianName ?: null,
                        'guardian_phone' => $guardianPhone ?: null,
                        'parent_name' => $guardianName ?: null,
                        'parent_phone' => $guardianPhone ?: null,
                        'enrollment_status' => 'active',
                        'is_active' => true,
                    ], $institutionId);

                    $createdCount++;
                    $syncedStudentUserIds[] = $newStudent->id;

                    continue;
                }

                $details = StudentDetails::where('institution_id', $institutionId)->find($row['id']);
                if (! $details) {
                    continue;
                }

                if ($details->student_id) {
                    $user = User::find($details->student_id);
                    if ($user && $user->name !== $name) {
                        $user->update(['name' => $name]);
                    }
                    $syncedStudentUserIds[] = $details->student_id;
                }

                $details->update([
                    'admission_number' => $adm !== '' ? $adm : $details->admission_number,
                    'student_number' => $stuNo ?: null,
                    'gender' => $genderVal,
                    'guardian_name' => $guardianName ?: null,
                    'guardian_phone' => $guardianPhone ?: null,
                ]);

                app(SaveStudent::class)->syncParentFromGuardian($details->refresh());

                $updatedCount++;
            }
        });

        // Trigger biometric sync if enabled
        $biometricNote = '';
        if ($this->syncToBiometrics && ! empty($syncedStudentUserIds) && $this->activeDevices->isNotEmpty()) {
            $syncRes = app(ZKTecoUserSyncService::class)->bulkSyncStudents($syncedStudentUserIds, $institutionId, force: true);
            if ($syncRes['synced'] > 0) {
                $biometricNote = " (and queued to {$syncRes['devices_count']} biometric device".($syncRes['devices_count'] > 1 ? 's' : '').')';
            }
        }

        $this->isEditingRoster = false;
        $this->rosterEdits = [];
        $this->rosterErrors = [];
        app(StudentCacheService::class)->invalidateClass($institutionId, $this->selectedClassId);
        unset($this->classRoster, $this->classStats);

        $msgParts = [];
        if ($updatedCount > 0) {
            $msgParts[] = "updated {$updatedCount} student(s)";
        }
        if ($createdCount > 0) {
            $msgParts[] = "enrolled {$createdCount} new student(s)";
        }
        $msg = ! empty($msgParts) ? 'Successfully '.implode(' and ', $msgParts).$biometricNote.'.' : 'Roster changes saved.';

        Flux::toast(text: $msg, variant: 'success');
    }

    private function blankRow(): array
    {
        $row = array_fill_keys(ImportStudents::COLUMNS, '');
        if ($this->selectedClass) {
            $row['class_name'] = $this->selectedClass->name;
        }

        return $row;
    }
}; ?>

<div x-data="{
    isDirty: false,
    hasDraft: false,
    draftKey: 'sms_bulk_draft_{{ currentInstitutionId() }}',
    _draftTimer: null,

    init() {
        this.checkDraft();

        window.addEventListener('beforeunload', (e) => {
            if (this.isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        window.addEventListener('clear-bulk-draft', () => {
            clearTimeout(this._draftTimer);
            try {
                localStorage.removeItem(this.draftKey);
            } catch (e) {}
            this.isDirty = false;
            this.hasDraft = false;
        });
    },

    onInput(e) {
        if (e && e.target && typeof e.target.value === 'string' && e.target.value.trim() !== '') {
            this.isDirty = true;
        }
        this.saveDraftDebounced();
    },

    saveDraftDebounced() {
        clearTimeout(this._draftTimer);
        this._draftTimer = setTimeout(() => {
            try {
                const inputs = document.querySelectorAll('[data-grid-input]');
                if (inputs.length > 0) {
                    const rowData = {};
                    inputs.forEach(el => {
                        const row = el.dataset.row;
                        const col = el.dataset.col;
                        if (row !== undefined && col) {
                            if (!rowData[row]) rowData[row] = {};
                            rowData[row][col] = el.value;
                        }
                    });
                    const rows = Object.values(rowData);
                    const hasContent = rows.some(r => (r.name || '').trim() !== '' || (r.admission_number || '').trim() !== '');
                    if (hasContent) {
                        localStorage.setItem(this.draftKey, JSON.stringify(rows));
                        this.isDirty = true;
                    }
                }
            } catch (e) {}
        }, 600);
    },

    checkDraft() {
        try {
            const saved = localStorage.getItem(this.draftKey);
            if (saved) {
                const parsed = JSON.parse(saved);
                const hasData = Array.isArray(parsed) && parsed.some(r => (r.name || '').trim() !== '');
                if (hasData) {
                    this.hasDraft = true;
                }
            }
        } catch (e) {}
    },

    restoreLocalDraft() {
        try {
            const saved = localStorage.getItem(this.draftKey);
            if (saved) {
                const parsed = JSON.parse(saved);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    $wire.restoreDraft(parsed);
                    this.hasDraft = false;
                    this.isDirty = true;
                    return;
                }
            }
        } catch (e) {
            console.error('Failed to restore draft:', e);
        }
        this.dismissDraft();
    },

    dismissDraft() {
        clearTimeout(this._draftTimer);
        try {
            localStorage.removeItem(this.draftKey);
        } catch (e) {}
        this.hasDraft = false;
        this.isDirty = false;
        $wire.discardDraft();
    }
}" class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8">

    {{-- Local Draft Recovery Banner --}}
    <div x-show="hasDraft" x-cloak
        class="rounded-2xl border border-sky-200 bg-sky-50/90 dark:bg-sky-950/40 dark:border-sky-800 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm transition-all">
        <div class="flex items-center gap-3">
            <div class="size-8 rounded-lg bg-sky-100 dark:bg-sky-900/60 flex items-center justify-center shrink-0">
                <flux:icon icon="clock" class="size-4 text-sky-600 dark:text-sky-400" />
            </div>
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-sky-900 dark:text-sky-200">Unsaved Session Draft Found</h4>
                <p class="text-xs text-sky-700 dark:text-sky-300 mt-0.5">
                    We found unsaved student rows from your previous session. Would you like to restore them?
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" @click="restoreLocalDraft()"
                class="px-3.5 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold text-xs shadow-sm transition-colors cursor-pointer">
                Restore Draft
            </button>
            <button type="button" @click="dismissDraft()"
                class="px-3 py-1.5 rounded-lg text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 font-semibold text-xs transition-colors cursor-pointer">
                Discard
            </button>
        </div>
    </div>

    {{-- First-time school with 0 classes --}}
    @if ($this->classes->isEmpty())
        <div class="rounded-2xl border border-amber-300 bg-amber-50/90 dark:bg-amber-950/40 dark:border-amber-800 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="size-9 rounded-xl bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center shrink-0">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200">No School Classes Found</h4>
                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                        Before enrolling students, create at least one school class (e.g. Grade 1, Form 1) so students can be properly assigned.
                    </p>
                </div>
            </div>
            <flux:button href="{{ route('classes.create') }}" wire:navigate.hover icon="plus" variant="primary" class="font-semibold text-xs shrink-0 shadow-sm">
                Create First Class
            </flux:button>
        </div>
    @endif

    {{-- Header with Navigation Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Student Enrollment & Upload</flux:heading>
                <template x-if="isDirty">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200/70 dark:border-amber-800">
                        <span class="size-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        Unsaved Entries
                    </span>
                </template>
            </div>
            <flux:text class="text-zinc-500 text-sm mt-1">
                Fast student enrollment: type directly into the spreadsheet grid below or upload an Excel file.
            </flux:text>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="{{ route('student.create') }}"
                @click="if (isDirty && !confirm('You have unsaved student entries. Are you sure you want to leave without saving?')) { $event.preventDefault(); return false; }"
                wire:navigate.hover
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold text-xs shadow-sm transition-colors">
                <flux:icon icon="user-plus" class="size-3.5 text-zinc-500" />
                Single Student Form
            </a>
            <a href="{{ route('student.index') }}"
                @click="if (isDirty && !confirm('You have unsaved student entries. Are you sure you want to leave without saving?')) { $event.preventDefault(); return false; }"
                wire:navigate.hover
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold text-xs shadow-sm transition-colors">
                <flux:icon icon="arrow-left" class="size-3.5 text-zinc-500" />
                Back to Directory
            </a>
            @if ($activeTab === 'grid')
                @if ($this->activeDevices->isNotEmpty())
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs text-zinc-700 dark:text-zinc-300 font-medium bg-zinc-100/90 dark:bg-zinc-800/90 hover:bg-zinc-200/80 dark:hover:bg-zinc-700/80 px-3 py-2 rounded-xl border border-zinc-200 dark:border-zinc-700 transition shadow-2xs">
                        <input type="checkbox" wire:model.live="syncToBiometrics" class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 size-4 cursor-pointer">
                        <span class="flex items-center gap-1.5">
                            <flux:icon icon="finger-print" class="size-3.5 text-blue-600 dark:text-blue-400" />
                            <span>Sync to {{ $this->activeDevices->count() }} Biometric Device{{ $this->activeDevices->count() > 1 ? 's' : '' }}</span>
                        </span>
                    </label>
                @endif
                <flux:button wire:click="save" wire:loading.attr="disabled" icon="check" variant="primary"
                    class="font-semibold shadow-sm px-6">
                    <span wire:loading.remove wire:target="save">Save All Students</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Plan Limit Banner --}}
    <x-student-limit-banner />

    {{-- Target Class Selector & Live Headcount Hub --}}
    <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-4 sm:p-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            {{-- Class Picker --}}
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 flex items-center justify-center shrink-0 border border-sky-200/60 dark:border-sky-800">
                    <flux:icon icon="academic-cap" class="size-5 text-sky-600 dark:text-sky-400" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <label for="targetClass" class="block text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Target Class</label>
                        <span wire:loading wire:target="onClassSelectChange, switchClass" class="inline-flex items-center gap-1 text-[11px] text-sky-600 dark:text-sky-400 font-semibold">
                            <flux:icon icon="arrow-path" class="size-3 animate-spin" />
                            Loading...
                        </span>
                    </div>
                    <select id="targetClass" wire:change="onClassSelectChange($event.target.value)"
                        wire:loading.attr="disabled" wire:target="onClassSelectChange, switchClass"
                        class="mt-1 text-sm font-bold rounded-xl border border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-900 dark:text-white py-1.5 px-3 focus:ring-2 focus:ring-sky-500 cursor-pointer transition-colors min-w-[220px]">
                        <option value="" @selected($selectedClassId === null)>-- All / Mixed Classes --</option>
                        @foreach ($this->classes as $c)
                            <option value="{{ $c->id }}" @selected($selectedClassId === $c->id)>{{ $c->name }} ({{ $c->level ?? 'Class' }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Live Class Headcount & Next Admission Helper --}}
            @if ($this->selectedClass || $this->classStats['total'] > 0)
                @php $stats = $this->classStats; @endphp
                <div wire:loading.class="opacity-60" wire:target="onClassSelectChange, switchClass" class="flex flex-wrap items-center gap-2 sm:gap-2.5 text-xs transition-opacity">
                    <div class="px-3 py-1.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 font-semibold text-zinc-700 dark:text-zinc-200 border border-zinc-200/70 dark:border-zinc-700 shadow-2xs">
                        {{ $this->selectedClass ? 'Class Size' : 'All Students' }}: <strong class="text-zinc-900 dark:text-white ml-0.5">{{ $stats['total'] }}</strong>
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg bg-blue-50/90 dark:bg-blue-950/40 font-semibold text-blue-700 dark:text-blue-300 border border-blue-200/70 dark:border-blue-800 shadow-2xs">
                        Boys: <strong class="ml-0.5">{{ $stats['male'] }}</strong>
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg bg-pink-50/90 dark:bg-pink-950/40 font-semibold text-pink-700 dark:text-pink-300 border border-pink-200/70 dark:border-pink-800 shadow-2xs">
                        Girls: <strong class="ml-0.5">{{ $stats['female'] }}</strong>
                    </div>
                    @if ($stats['school_latest_admission'] !== 'None')
                        <div class="px-2.5 py-1.5 rounded-lg bg-emerald-50/90 dark:bg-emerald-950/40 font-semibold text-emerald-800 dark:text-emerald-300 border border-emerald-200/70 dark:border-emerald-800 font-mono shadow-2xs"
                            title="Highest admission number across all classes in this school">
                            School Last Adm: <strong class="ml-0.5">{{ $stats['school_latest_admission'] }}</strong>
                        </div>
                    @endif
                    @if ($stats['latest_admission'] !== 'None' && $stats['latest_admission'] !== $stats['school_latest_admission'])
                        <div class="px-2.5 py-1.5 rounded-lg bg-amber-50/90 dark:bg-amber-950/40 font-semibold text-amber-800 dark:text-amber-300 border border-amber-200/70 dark:border-amber-800 font-mono shadow-2xs"
                            title="Highest admission number recorded in this specific class">
                            Class Last Adm: <strong class="ml-0.5">{{ $stats['latest_admission'] }}</strong>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Mode Selector Tabs: Keyboard Grid vs Excel File Upload --}}
    <div class="flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-3">
        <button type="button" wire:click="$set('activeTab', 'grid')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-2xs {{ $activeTab === 'grid' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-sm' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300' }}">
            <flux:icon icon="table-cells" class="size-4" />
            Keyboard Entry Grid
            <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold {{ $activeTab === 'grid' ? 'bg-zinc-700 text-zinc-200 dark:bg-zinc-200 dark:text-zinc-800' : 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">Fastest</span>
        </button>
        <button type="button" wire:click="$set('activeTab', 'excel')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-2xs {{ $activeTab === 'excel' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 shadow-sm' : 'bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-300' }}">
            <flux:icon icon="arrow-up-tray" class="size-4" />
            Upload Spreadsheet File (.xlsx / .csv)
        </button>
    </div>

    {{-- TAB 2: Upload Excel File Card --}}
    @if ($activeTab === 'excel')
        <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl p-6 sm:p-8 space-y-6">
            <div class="max-w-2xl">
                <flux:heading size="lg" class="text-zinc-900 dark:text-white font-bold">Import from Excel or CSV Spreadsheet</flux:heading>
                <flux:subheading class="mt-1 text-zinc-500 text-sm">
                    Upload an existing student list. The system will automatically map columns, validate duplicate admission numbers, and load them into the review grid.
                </flux:subheading>
            </div>

            <div class="p-6 rounded-2xl border-2 border-dashed border-zinc-300 dark:border-zinc-700 hover:border-sky-500 dark:hover:border-sky-500 bg-zinc-50/50 dark:bg-zinc-800/40 text-center transition-colors">
                <div class="size-12 rounded-2xl bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 mx-auto flex items-center justify-center mb-3">
                    <flux:icon icon="arrow-up-tray" class="size-6" />
                </div>
                <h4 class="text-sm font-bold text-zinc-900 dark:text-white">Choose your spreadsheet file</h4>
                <p class="text-xs text-zinc-500 mt-1 max-w-md mx-auto">
                    Supported formats: <strong class="text-zinc-700 dark:text-zinc-300">.xlsx, .xls, .csv</strong> (Max size: 10MB)
                </p>

                <div class="mt-4 flex justify-center">
                    <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" id="excelFileUpload"
                        class="block w-full max-w-sm text-xs text-zinc-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-sky-600 file:text-white hover:file:bg-sky-700 border border-zinc-200 dark:border-zinc-700 rounded-xl cursor-pointer bg-white dark:bg-zinc-800 transition-colors" />
                </div>

                <div wire:loading wire:target="file" class="mt-3 flex items-center justify-center gap-2 text-xs text-sky-600 font-semibold">
                    <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                    Reading and validating spreadsheet rows...
                </div>

                @error('file')
                    <p class="text-xs font-semibold text-rose-600 mt-3">{{ $message }}</p>
                @enderror
            </div>

            {{-- Expected Format Guide --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/40 p-4">
                <h5 class="text-xs font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-2">Expected Column Layout</h5>
                <p class="text-xs text-zinc-500 mb-3">Your spreadsheet header row should include these columns in any order:</p>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="px-2.5 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">Student Name *</span>
                    <span class="px-2.5 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">Admission No</span>
                    <span class="px-2.5 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">Class</span>
                    <span class="px-2.5 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">Gender</span>
                    <span class="px-2.5 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">Guardian Name</span>
                    <span class="px-2.5 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">Guardian Phone</span>
                    <span class="px-2.5 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">Student No</span>
                    <span class="px-2.5 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">Status</span>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 1: Keyboard Entry Grid View --}}
    @if ($activeTab === 'grid')

        {{-- Enrolled Roster Accordion (Open by default, hides only on user click) --}}
        {{-- Enrolled Roster Accordion (Open by default, hides only on user click) --}}
        @if ($this->classRoster->isNotEmpty() || $rosterSearch !== '')
            @php $stats = $this->classStats; @endphp
            <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden transition-all">
                <div class="w-full px-5 py-3.5 flex items-center justify-between text-left bg-zinc-50/70 dark:bg-zinc-950/40 border-b border-zinc-200/80 dark:border-zinc-800 flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <div class="size-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-200/60 dark:border-emerald-800">
                            <flux:icon icon="users" class="size-4" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h4 class="text-sm font-bold text-zinc-900 dark:text-white">{{ $this->selectedClass?->name ?? 'All Classes' }} Enrolled Roster</h4>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800">
                                    {{ $stats['total'] }} Students Enrolled
                                </span>
                                @if ($stats['latest_admission'] !== 'None')
                                    <span class="inline-flex px-2 py-0.5 rounded-md text-xs font-mono font-medium bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200/70 dark:border-amber-800">
                                        Last Adm: {{ $stats['latest_admission'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-zinc-500 mt-0.5">
                                Click any field below to edit or add parent, phone, admission number, or student ID.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" wire:click="$toggle('showEnrolledRoster')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-xs font-semibold text-zinc-700 dark:text-zinc-200 shadow-2xs transition-colors cursor-pointer">
                            <span>{{ $showEnrolledRoster ? 'Hide Roster' : 'Show Roster' }}</span>
                            <flux:icon icon="{{ $showEnrolledRoster ? 'chevron-up' : 'chevron-down' }}" class="size-3.5 text-zinc-400" />
                        </button>
                    </div>
                </div>

                @if ($showEnrolledRoster)
                    <div class="p-4 bg-zinc-50/30 dark:bg-zinc-950/20">
                        <div class="mb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <input type="text" wire:model.live.debounce.300ms="rosterSearch"
                                placeholder="Search enrolled students by name, admission no, or student ID..."
                                class="w-full max-w-sm text-xs rounded-xl border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-2 px-3 text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:ring-2 focus:ring-sky-500" />
                            <span class="text-xs text-zinc-500">Showing {{ $this->classRoster->count() }} enrolled student(s)</span>
                        </div>

                        <div class="overflow-auto max-h-72 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-2xs">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-bold uppercase sticky top-0 shadow-2xs z-10">
                                    <tr class="divide-x divide-zinc-200 dark:divide-zinc-700">
                                        <th class="py-2.5 px-3 w-12 text-center text-zinc-400">#</th>
                                        <th class="py-2.5 px-3 min-w-[180px]">Student Name</th>
                                        <th class="py-2.5 px-3 min-w-[140px]">Admission No</th>
                                        <th class="py-2.5 px-3 min-w-[120px]">Student ID</th>
                                        <th class="py-2.5 px-3 min-w-[100px]">Gender</th>
                                        <th class="py-2.5 px-3 min-w-[170px]">Parent / Guardian</th>
                                        <th class="py-2.5 px-3 min-w-[140px]">Guardian Phone</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 text-zinc-600 dark:text-zinc-400">
                                    @forelse ($this->classRoster as $idx => $student)
                                        <tr wire:key="enrolled-student-{{ $student->id }}" class="divide-x divide-zinc-100 dark:divide-zinc-800 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 {{ $student->admission_number === $stats['latest_admission'] ? 'bg-amber-50/50 dark:bg-amber-950/20' : '' }}">
                                            <td class="py-2 px-2 text-center font-mono text-zinc-400">{{ $idx + 1 }}</td>

                                            {{-- Student Name --}}
                                            <td class="p-0">
                                                <input type="text"
                                                    value="{{ $student->student?->name }}"
                                                    wire:change="updateEnrolledField({{ $student->id }}, 'name', $event.target.value)"
                                                    placeholder="Student Name"
                                                    class="w-full bg-transparent hover:bg-zinc-50 dark:hover:bg-zinc-800/60 focus:bg-white dark:focus:bg-zinc-800 border-0 focus:ring-1 focus:ring-sky-500 rounded px-3 py-2 text-xs font-semibold text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-300 transition-colors" />
                                            </td>

                                            {{-- Admission No --}}
                                            <td class="p-0">
                                                <input type="text"
                                                    value="{{ $student->admission_number }}"
                                                    wire:change="updateEnrolledField({{ $student->id }}, 'admission_number', $event.target.value)"
                                                    placeholder="Admission No"
                                                    class="w-full bg-transparent hover:bg-zinc-50 dark:hover:bg-zinc-800/60 focus:bg-white dark:focus:bg-zinc-800 border-0 focus:ring-1 focus:ring-sky-500 rounded px-3 py-2 text-xs font-mono font-bold text-zinc-800 dark:text-zinc-200 placeholder:text-zinc-300 transition-colors" />
                                            </td>

                                            {{-- Student ID --}}
                                            <td class="p-0">
                                                <input type="text"
                                                    value="{{ $student->student_number }}"
                                                    wire:change="updateEnrolledField({{ $student->id }}, 'student_number', $event.target.value)"
                                                    placeholder="UPI / ID"
                                                    class="w-full bg-transparent hover:bg-zinc-50 dark:hover:bg-zinc-800/60 focus:bg-white dark:focus:bg-zinc-800 border-0 focus:ring-1 focus:ring-sky-500 rounded px-3 py-2 text-xs font-mono text-zinc-600 dark:text-zinc-400 placeholder:text-zinc-300 transition-colors" />
                                            </td>

                                            {{-- Gender --}}
                                            <td class="p-0">
                                                <select wire:change="updateEnrolledField({{ $student->id }}, 'gender', $event.target.value)"
                                                    class="w-full bg-transparent hover:bg-zinc-50 dark:hover:bg-zinc-800/60 focus:bg-white dark:focus:bg-zinc-800 border-0 focus:ring-1 focus:ring-sky-500 rounded px-3 py-2 text-xs text-zinc-700 dark:text-zinc-300 cursor-pointer capitalize">
                                                    <option value="">-</option>
                                                    <option value="male" @selected(strtolower($student->gender ?? '') === 'male')>Male</option>
                                                    <option value="female" @selected(strtolower($student->gender ?? '') === 'female')>Female</option>
                                                    <option value="other" @selected(strtolower($student->gender ?? '') === 'other')>Other</option>
                                                </select>
                                            </td>

                                            {{-- Guardian --}}
                                            <td class="p-0">
                                                <input type="text"
                                                    value="{{ $student->guardian_name }}"
                                                    wire:change="updateEnrolledField({{ $student->id }}, 'guardian_name', $event.target.value)"
                                                    placeholder="Parent or Guardian"
                                                    class="w-full bg-transparent hover:bg-zinc-50 dark:hover:bg-zinc-800/60 focus:bg-white dark:focus:bg-zinc-800 border-0 focus:ring-1 focus:ring-sky-500 rounded px-3 py-2 text-xs text-zinc-700 dark:text-zinc-300 placeholder:text-zinc-300 transition-colors" />
                                            </td>

                                            {{-- Guardian Phone --}}
                                            <td class="p-0">
                                                <input type="text"
                                                    value="{{ $student->guardian_phone }}"
                                                    wire:change="updateEnrolledField({{ $student->id }}, 'guardian_phone', $event.target.value)"
                                                    placeholder="Phone (07...)"
                                                    class="w-full bg-transparent hover:bg-zinc-50 dark:hover:bg-zinc-800/60 focus:bg-white dark:focus:bg-zinc-800 border-0 focus:ring-1 focus:ring-sky-500 rounded px-3 py-2 text-xs font-mono text-zinc-700 dark:text-zinc-300 placeholder:text-zinc-300 transition-colors" />
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-4 px-4 text-center text-zinc-500">No matching enrolled students found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Streamlined Zero-Scroll Entry Grid --}}
        <div class="bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden flex flex-col"
            @input="onInput($event)">

            {{-- Table Section Header --}}
            <div class="px-5 py-3.5 border-b border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-950/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-sky-100 dark:bg-sky-900/60 text-sky-800 dark:text-sky-300 text-xs font-bold tracking-wide">
                        <flux:icon icon="user-plus" class="size-3.5 text-sky-600 dark:text-sky-400" />
                        Quick Student Entry
                    </span>
                    <span class="text-xs text-zinc-600 dark:text-zinc-400 font-medium">
                        Assigning to <strong class="text-zinc-900 dark:text-white">{{ $this->selectedClass ? $this->selectedClass->name : 'Selected Classes' }}</strong>
                        &bull; ({{ count($this->filledRows()) }} ready to save)
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" wire:click="$toggle('showExtraColumns')"
                        class="text-xs font-semibold text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 transition-colors">
                        {{ $showExtraColumns ? 'Hide Optional Fields' : '+ Show Student ID & Status' }}
                    </button>
                    <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                    <button type="button" wire:click="addRows(5)"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:text-sky-700 dark:text-sky-400 transition-colors">
                        <flux:icon icon="plus" class="size-3.5" />
                        Add 5 Rows
                    </button>
                </div>
            </div>

            {{-- Responsive Table without Overflow --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 text-xs font-bold uppercase tracking-wider shadow-sm">
                        <tr class="divide-x divide-zinc-200 dark:divide-zinc-700">
                            <th class="py-2.5 px-2 w-12 text-center text-zinc-400">#</th>
                            <th class="py-2.5 px-3 min-w-[200px]">Student Full Name <span class="text-rose-500">*</span></th>
                            <th class="py-2.5 px-3 w-36">Admission No</th>
                            @if ($showExtraColumns)
                                <th class="py-2.5 px-3 w-32">Student ID</th>
                            @endif
                            @if (! $this->selectedClass)
                                <th class="py-2.5 px-3 w-36">Class</th>
                            @endif
                            <th class="py-2.5 px-3 w-28">Gender</th>
                            <th class="py-2.5 px-3 w-44">Guardian Name</th>
                            <th class="py-2.5 px-3 w-36">Guardian Phone</th>
                            @if ($showExtraColumns)
                                <th class="py-2.5 px-3 w-28">Status</th>
                            @endif
                            <th class="py-2.5 px-2 w-10 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @php
                            $baseIndex = $this->selectedClass ? $this->classStats['total'] : 0;
                            $cell = 'w-full bg-transparent border-0 focus:ring-2 focus:ring-sky-500 rounded-none px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-300 dark:placeholder:text-zinc-600';
                        @endphp

                        @foreach ($rows as $index => $row)
                            <tr wire:key="new-row-{{ $index }}"
                                class="divide-x divide-zinc-100 dark:divide-zinc-800 {{ isset($rowErrors[$index]) ? 'bg-rose-50/70 dark:bg-rose-900/20' : 'hover:bg-sky-50/30 dark:hover:bg-zinc-800/40' }}">
                                <td class="py-0 px-2 text-center text-xs font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50/40 dark:bg-sky-950/20 select-none">
                                    {{ $baseIndex + $index + 1 }}
                                </td>
                                <td class="p-0">
                                    <input type="text" data-grid-input data-row="{{ $index }}" data-col="name"
                                        wire:model.blur="rows.{{ $index }}.name"
                                        placeholder="Type full student name..." class="{{ $cell }} font-medium" />
                                    @if (isset($rowErrors[$index]))
                                        <p class="text-xs font-semibold text-rose-600 px-3 pb-1.5">
                                            {{ $rowErrors[$index] }}
                                        </p>
                                    @endif
                                </td>
                                <td class="p-0">
                                    <input type="text" data-grid-input data-row="{{ $index }}" data-col="admission_number"
                                        wire:model.blur="rows.{{ $index }}.admission_number"
                                        placeholder="e.g. ADM-001" class="{{ $cell }} font-mono" />
                                </td>
                                @if ($showExtraColumns)
                                    <td class="p-0">
                                        <input type="text" data-grid-input data-row="{{ $index }}" data-col="student_number"
                                            wire:model.blur="rows.{{ $index }}.student_number"
                                            placeholder="STU-001" class="{{ $cell }} font-mono" />
                                    </td>
                                @endif
                                @if (! $this->selectedClass)
                                    <td class="p-0">
                                        <select data-grid-input data-row="{{ $index }}" data-col="class_name"
                                            wire:model.blur="rows.{{ $index }}.class_name" class="{{ $cell }} cursor-pointer">
                                            <option value="">Select Class</option>
                                            @foreach ($this->classes as $schoolClass)
                                                <option value="{{ $schoolClass->name }}" @selected(strcasecmp($row['class_name'] ?? '', $schoolClass->name) === 0)>
                                                    {{ $schoolClass->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endif
                                <td class="p-0">
                                    <select data-grid-input data-row="{{ $index }}" data-col="gender"
                                        wire:model.blur="rows.{{ $index }}.gender" class="{{ $cell }} cursor-pointer">
                                        <option value="">-</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </td>
                                <td class="p-0">
                                    <input type="text" data-grid-input data-row="{{ $index }}" data-col="guardian_name"
                                        wire:model.blur="rows.{{ $index }}.guardian_name"
                                        placeholder="Parent/Guardian" class="{{ $cell }}" />
                                </td>
                                <td class="p-0">
                                    <input type="text" data-grid-input data-row="{{ $index }}" data-col="guardian_phone"
                                        wire:model.blur="rows.{{ $index }}.guardian_phone"
                                        placeholder="0700 000 000" class="{{ $cell }} font-mono" />
                                </td>
                                @if ($showExtraColumns)
                                    <td class="p-0">
                                        <select data-grid-input data-row="{{ $index }}" data-col="enrollment_status"
                                            wire:model.blur="rows.{{ $index }}.enrollment_status" class="{{ $cell }} cursor-pointer">
                                            <option value="active">Active</option>
                                            <option value="graduated">Graduated</option>
                                            <option value="suspended">Suspended</option>
                                            <option value="transferred">Transferred</option>
                                            <option value="withdrawn">Withdrawn</option>
                                            <option value="dropped">Dropped</option>
                                        </select>
                                    </td>
                                @endif
                                <td class="p-0 text-center">
                                    <button type="button" wire:click="removeRow({{ $index }})"
                                        title="Remove row" class="p-2 text-zinc-300 hover:text-rose-500 transition-colors">
                                        <flux:icon icon="x-mark" class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Table Bottom Bar with Actions --}}
            <div class="border-t border-zinc-200/80 dark:border-zinc-800 px-5 py-4 bg-zinc-50/50 dark:bg-zinc-950/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-zinc-500">
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="addRows(5)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 font-semibold transition-colors shadow-2xs">
                        <flux:icon icon="plus" class="size-3.5 text-zinc-500" />
                        Add 5 More Rows
                    </button>
                    <span>Empty rows are automatically ignored. Logins and passwords are created automatically.</span>
                </div>

                <div class="flex items-center gap-3">
                    @if ($this->activeDevices->isNotEmpty())
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs text-zinc-700 dark:text-zinc-300 font-medium bg-white dark:bg-zinc-800 px-3 py-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 transition shadow-2xs">
                            <input type="checkbox" wire:model.live="syncToBiometrics" class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 size-4 cursor-pointer">
                            <span class="flex items-center gap-1.5">
                                <flux:icon icon="finger-print" class="size-3.5 text-blue-600 dark:text-blue-400" />
                                <span>Sync to {{ $this->activeDevices->count() }} Device{{ $this->activeDevices->count() > 1 ? 's' : '' }}</span>
                            </span>
                        </label>
                    @endif
                    <flux:button wire:click="save" wire:loading.attr="disabled" icon="check" variant="primary"
                        class="font-semibold shadow-sm px-6">
                        <span wire:loading.remove wire:target="save">Save All Students</span>
                        <span wire:loading wire:target="save">Saving...</span>
                    </flux:button>
                </div>
            </div>
        </div>

    @endif

    {{-- Data Loss Guard Modal: Confirm Switch Class --}}
    <flux:modal name="confirm-switch-class" class="max-w-lg">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="size-11 rounded-2xl bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center shrink-0 border border-amber-200 dark:border-amber-800">
                    <flux:icon icon="exclamation-triangle" class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <flux:heading size="lg" class="text-zinc-900 dark:text-white font-bold">Unsaved Student Entries</flux:heading>
                    <flux:subheading class="mt-1 text-zinc-600 dark:text-zinc-300 text-sm">
                        You have <strong class="text-zinc-900 dark:text-white">{{ count($this->filledRows()) }} unsaved student record(s)</strong> entered for
                        <strong class="text-zinc-900 dark:text-white">{{ $this->selectedClass?->name ?? 'the current class' }}</strong>.
                        Switching to <strong class="text-zinc-900 dark:text-white">{{ $this->pendingClass?->name ?? 'another class' }}</strong> will discard these rows unless you save them first.
                    </flux:subheading>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-600 dark:text-zinc-300 space-y-1">
                <p class="font-semibold text-zinc-900 dark:text-white">What would you like to do?</p>
                <p>&bull; <strong>Save & Switch:</strong> Saves your current students to {{ $this->selectedClass?->name }} and opens {{ $this->pendingClass?->name }}.</p>
                <p>&bull; <strong>Discard & Switch:</strong> Clears your typed rows and opens {{ $this->pendingClass?->name }}.</p>
                <p>&bull; <strong>Stay Here:</strong> Keeps your typed students and remains on {{ $this->selectedClass?->name }}.</p>
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button variant="filled" wire:click="cancelSwitchClass" class="w-full sm:w-auto font-medium">
                    Stay on {{ $this->selectedClass?->name ?? 'Current Class' }}
                </flux:button>
                <flux:button variant="danger" wire:click="discardAndSwitchClass" icon="trash" class="w-full sm:w-auto font-semibold">
                    Discard & Switch
                </flux:button>
                <flux:button variant="primary" wire:click="saveAndSwitchClass" icon="check" class="w-full sm:w-auto font-bold shadow-sm">
                    Save & Switch
                </flux:button>
            </div>
        </div>
    </flux:modal>

</div>

