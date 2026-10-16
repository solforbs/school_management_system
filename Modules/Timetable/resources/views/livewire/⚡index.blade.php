<?php

use App\Models\TeacherSubjects;
use App\Models\User;
use App\Services\StudentCacheService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Classes\Models\SchoolClass;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Actions\SaveTimetableEntry;
use Modules\Timetable\Models\SubjectAllocation;
use Modules\Timetable\Models\TimetableDraft;
use Modules\Timetable\Models\TimetableDraftEntry;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Models\TimetableStructure;
use Modules\Timetable\Services\TimetableGeneratorService;
use Modules\Timetable\Services\TimetablePreflightService;
use Modules\Timetable\Services\TimetablePublishService;
use Modules\Timetable\Support\PreflightFailedException;
use Modules\Timetable\Support\PreflightReport;

new #[Title('Timetable')] class extends Component
{
    #[Url(as: 'class_id')]
    public string $classId = '';

    #[Url(as: 'draft_id')]
    public ?int $activeDraftId = null;

    // Generator Wizard State
    public bool $showGeneratorModal = false;

    public bool $showPublishConfirmModal = false;

    public int $wizardStep = 1;

    // Explicit Delete Confirmation Modal State
    public bool $showDeleteConfirmModal = false;

    public string $deleteTargetType = ''; // 'live_entry', 'draft_entry', 'clear_class', 'discard_draft'

    public ?int $deleteTargetId = null;

    public string $deleteTargetTitle = '';

    public string $deleteTargetDetails = '';

    // Interactive Edit Mode State
    public bool $isEditMode = false;

    public bool $showEditModePromptModal = false;

    public string $editPromptTarget = ''; // 'general', 'period_time', 'break', 'lesson', 'empty_slot'

    public array $pendingEditAction = [];

    // Period Time Editing Modal State
    public bool $showEditPeriodModal = false;

    public string $editPeriodOriginalTime = '';

    public string $editPeriodName = '';

    public string $editPeriodStart = '08:00';

    public string $editPeriodEnd = '08:40';

    public bool $cascadeLessonTimes = true;

    // Break / Rest Editing Modal State
    public bool $showEditBreakModal = false;

    public string $editBreakOriginalTime = '';

    public string $editBreakName = '';

    public string $editBreakStart = '10:00';

    public string $editBreakEnd = '10:20';

    public int $editBreakDuration = 20;

    public string $editBreakColor = 'amber';

    public bool $isNewBreak = false;

    public int $newBreakAfterPeriod = 2;

    // Quick Lesson Add/Edit Modal State
    public bool $showLessonModal = false;

    public ?int $lessonEntryId = null;

    public string $lessonDay = 'Monday';

    public string $lessonPeriod = '08:00-08:40';

    public string $lessonSubject = '';

    public ?int $lessonTeacherId = null;

    public string $lessonRoom = '';

    public bool $lessonIsDraft = false;

    public bool $lessonIsDouble = false;

    // Step 1: Routine & Bell Schedule
    public string $schoolStartTime = '08:00';

    public int $lessonDuration = 40;

    public int $dailyPeriods = 8;

    public array $activeDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    // Step 2: Custom Breaks Builder
    public bool $hasAssembly = true;

    public int $assemblyDuration = 30;

    public array $breaks = [
        ['name' => 'Short Break', 'after_period' => 2, 'duration' => 10, 'color' => 'sky'],
        ['name' => 'Tea Break', 'after_period' => 4, 'duration' => 30, 'color' => 'amber'],
        ['name' => 'Lunch Break', 'after_period' => 6, 'duration' => 60, 'color' => 'emerald'],
    ];

    public bool $hasGames = false;

    public int $gamesDuration = 45;

    // Step 3: Scope & Pedagogical Preferences
    public string $generatorScope = 'whole_school';

    public string $generatorName = '';

    public string $generatorAcademicYear = '2026';

    public string $generatorTerm = 'Term 1';

    public bool $preferMorningHighCognitive = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view timetable'), 403);

        if ($this->classId === '' && ! $this->isTeacher) {
            $this->classId = (string) ($this->classes->first()?->id ?? '');
        }

        $this->generatorAcademicYear = (string) now()->year;

        $defaultStructure = TimetableStructure::where('institution_id', currentInstitution()?->id ?? 0)
            ->where('is_default', true)
            ->first();

        if ($defaultStructure && ! empty($defaultStructure->periods_configuration)) {
            $teaching = $defaultStructure->getTeachingPeriods();
            if (! empty($teaching)) {
                $this->schoolStartTime = $teaching[0]['start_time'] ?? '08:00';
                $this->dailyPeriods = count($teaching);
            }
            if (! empty($defaultStructure->days_of_week)) {
                $this->activeDays = $defaultStructure->days_of_week;
            }
        }
    }

    public function addBreak(): void
    {
        $after = min($this->dailyPeriods, count($this->breaks) + 2);
        $this->breaks[] = [
            'name' => 'Break '.(count($this->breaks) + 1),
            'after_period' => $after,
            'duration' => 20,
            'color' => 'amber',
        ];
    }

    public function removeBreak(int $index): void
    {
        unset($this->breaks[$index]);
        $this->breaks = array_values($this->breaks);
    }

    public function confirmDelete(
        string $type,
        ?int $id = null,
        string $title = '',
        string $day = '',
        string $period = ''
    ): void {
        abort_unless(auth()->user()->can('delete timetable'), 403);

        $this->deleteTargetType = $type;
        $this->deleteTargetId = $id;

        if ($type === 'live_entry') {
            $this->deleteTargetTitle = "Remove \"{$title}\" lesson?";
            $this->deleteTargetDetails = "Scheduled for {$day} ({$period}). Removing this will free up the slot for this class and teacher.";
        } elseif ($type === 'draft_entry') {
            $this->deleteTargetTitle = "Remove \"{$title}\" from draft?";
            $this->deleteTargetDetails = "This slot on {$day} ({$period}) will be removed from the draft schedule.";
        } elseif ($type === 'clear_class') {
            $className = $this->selectedClass?->name ?? 'this class';
            $this->deleteTargetTitle = "Clear entire timetable for {$className}?";
            $this->deleteTargetDetails = "This will permanently delete all {$this->entries->count()} scheduled lesson slots for {$className}. Historical attendance records will be strictly protected.";
        } elseif ($type === 'discard_draft') {
            $draftName = $this->activeDraft?->name ?? 'this draft';
            $this->deleteTargetTitle = "Discard draft \"{$draftName}\"?";
            $this->deleteTargetDetails = 'All unsaved generated lesson slots in this draft sandbox will be completely deleted.';
        }

        $this->showDeleteConfirmModal = true;
    }

    public function executeDelete(): void
    {
        abort_unless(auth()->user()->can('delete timetable'), 403);

        $type = $this->deleteTargetType;
        $id = $this->deleteTargetId;

        $this->showDeleteConfirmModal = false;

        if ($type === 'live_entry' && $id) {
            $this->deleteEntry($id);
        } elseif ($type === 'draft_entry' && $id) {
            $this->deleteDraftEntry($id);
        } elseif ($type === 'clear_class') {
            $this->clearClassTimetable();
        } elseif ($type === 'discard_draft') {
            $this->discardActiveDraft();
        }

        $this->deleteTargetType = '';
        $this->deleteTargetId = null;
    }

    public function toggleEditMode(): void
    {
        abort_unless(auth()->user()->can('edit timetable'), 403);
        $this->isEditMode = ! $this->isEditMode;
    }

    public function promptEnterEditMode(string $target = 'general', array $payload = []): void
    {
        abort_unless(auth()->user()->can('edit timetable'), 403);

        $this->isEditMode = true;

        if (! empty($target) && $target !== 'general') {
            $this->dispatchDirectEdit($target, $payload);
        }
    }

    public function confirmEnterEditMode(): void
    {
        abort_unless(auth()->user()->can('edit timetable'), 403);

        $this->isEditMode = true;
        $this->showEditModePromptModal = false;

        if (! empty($this->editPromptTarget) && $this->editPromptTarget !== 'general') {
            $this->dispatchDirectEdit($this->editPromptTarget, $this->pendingEditAction);
        }

        $this->editPromptTarget = '';
        $this->pendingEditAction = [];
    }

    public function exitEditMode(): void
    {
        $this->isEditMode = false;
        $this->showEditModePromptModal = false;
        $this->showEditPeriodModal = false;
        $this->showEditBreakModal = false;
        $this->showLessonModal = false;
    }

    protected function dispatchDirectEdit(string $target, array $payload): void
    {
        if ($target === 'period_time') {
            $this->openEditPeriodModal($payload['time'] ?? '08:00-08:40', $payload['name'] ?? 'Period');
        } elseif ($target === 'break') {
            $this->openEditBreakModal($payload['time'] ?? '10:00-10:20', $payload['name'] ?? 'Break', $payload['color'] ?? 'amber');
        } elseif ($target === 'lesson') {
            $this->openLessonModal(
                $payload['day'] ?? 'Monday',
                $payload['period'] ?? '08:00-08:40',
                $payload['id'] ?? null,
                ! empty($payload['is_draft'])
            );
        } elseif ($target === 'empty_slot') {
            $this->openLessonModal($payload['day'] ?? 'Monday', $payload['period'] ?? '08:00-08:40', null, $this->isDraftMode);
        }
    }

    public function openEditPeriodModal(string $timeStr, string $name): void
    {
        abort_unless(auth()->user()->can('edit timetable'), 403);
        $this->resetErrorBag();
        $this->resetValidation();
        $this->editPeriodOriginalTime = $timeStr;
        $this->editPeriodName = $name;
        $parts = explode('-', $timeStr);
        $this->editPeriodStart = $parts[0] ?? '08:00';
        $this->editPeriodEnd = $parts[1] ?? '08:40';
        $this->showEditPeriodModal = true;
    }

    public function savePeriodTime(): void
    {
        abort_unless(auth()->user()->can('edit timetable'), 403);

        $this->validate([
            'editPeriodStart' => ['required', 'date_format:H:i'],
            'editPeriodEnd' => ['required', 'date_format:H:i', 'after:editPeriodStart'],
            'editPeriodName' => ['required', 'string', 'max:50'],
        ]);

        $structure = $this->activeStructure;
        if (! $structure) {
            session()->flash('error', 'Timetable structure not found.');

            return;
        }

        $config = $structure->periods_configuration ?? [];
        $parts = explode('-', $this->editPeriodOriginalTime);
        $oldStart = $parts[0] ?? '';
        $oldEnd = $parts[1] ?? '';

        foreach ($config as &$item) {
            if (empty($item['is_break']) && (($item['start_time'] === $oldStart && $item['end_time'] === $oldEnd) || $item['name'] === $this->editPeriodName)) {
                $item['name'] = $this->editPeriodName;
                $item['start_time'] = $this->editPeriodStart;
                $item['end_time'] = $this->editPeriodEnd;
            }
        }
        unset($item);

        $structure->update(['periods_configuration' => $config]);

        // Cascade time changes to matching lesson entries
        if ($this->cascadeLessonTimes && $oldStart && $oldEnd) {
            if ($this->isDraftMode && $this->activeDraftId) {
                TimetableDraftEntry::where('draft_id', $this->activeDraftId)
                    ->whereTime('start_time', $oldStart)
                    ->whereTime('end_time', $oldEnd)
                    ->update([
                        'start_time' => $this->editPeriodStart,
                        'end_time' => $this->editPeriodEnd,
                    ]);
            } else {
                $institutionId = currentInstitution()?->id ?? auth()->user()->institution_id;
                TimetableEntry::where('institution_id', $institutionId)
                    ->whereTime('start_time', $oldStart)
                    ->whereTime('end_time', $oldEnd)
                    ->update([
                        'start_time' => $this->editPeriodStart,
                        'end_time' => $this->editPeriodEnd,
                    ]);
            }
        }

        $this->showEditPeriodModal = false;
        session()->flash('success', "Updated {$this->editPeriodName} to {$this->editPeriodStart} - {$this->editPeriodEnd}.");
    }

    public function openEditBreakModal(string $timeStr, string $name, string $color = 'amber'): void
    {
        abort_unless(auth()->user()->can('edit timetable'), 403);
        $this->resetErrorBag();
        $this->resetValidation();
        $this->isNewBreak = false;
        $this->editBreakOriginalTime = $timeStr;
        $this->editBreakName = $name;
        $this->editBreakColor = $color;
        $parts = explode('-', $timeStr);
        $this->editBreakStart = $parts[0] ?? '10:00';
        $this->editBreakEnd = $parts[1] ?? '10:20';
        try {
            $this->editBreakDuration = Carbon::parse($this->editBreakStart)->diffInMinutes(Carbon::parse($this->editBreakEnd));
        } catch (Throwable $e) {
            $this->editBreakDuration = 20;
        }
        $this->showEditBreakModal = true;
    }

    public function openNewBreakModal(): void
    {
        abort_unless(auth()->user()->can('edit timetable'), 403);
        $this->resetErrorBag();
        $this->resetValidation();
        $this->isNewBreak = true;
        $this->editBreakName = 'Short Break';
        $this->editBreakDuration = 20;
        $this->editBreakColor = 'amber';
        $this->newBreakAfterPeriod = 2;
        $this->showEditBreakModal = true;
    }

    public function saveBreak(): void
    {
        abort_unless(auth()->user()->can('edit timetable'), 403);

        $this->validate([
            'editBreakName' => ['required', 'string', 'max:60'],
            'editBreakDuration' => ['required', 'integer', 'min:5', 'max:180'],
            'editBreakColor' => ['required', 'in:amber,emerald,sky,indigo,rose'],
        ]);

        $structure = $this->activeStructure;
        if (! $structure) {
            session()->flash('error', 'Timetable structure not found.');

            return;
        }

        $config = $structure->periods_configuration ?? [];

        if ($this->isNewBreak) {
            $newConfig = [];
            $inserted = false;
            foreach ($config as $item) {
                $newConfig[] = $item;
                if (! empty($item['period_number']) && (int) $item['period_number'] === (int) $this->newBreakAfterPeriod && ! $inserted) {
                    $start = Carbon::createFromFormat('H:i', $item['end_time']);
                    $end = $start->copy()->addMinutes($this->editBreakDuration);
                    $newConfig[] = [
                        'period_number' => 0,
                        'name' => $this->editBreakName,
                        'start_time' => $start->format('H:i'),
                        'end_time' => $end->format('H:i'),
                        'is_break' => true,
                        'type' => 'break',
                        'color' => $this->editBreakColor,
                    ];
                    $inserted = true;
                }
            }
            if (! $inserted) {
                $last = end($config);
                $start = $last ? Carbon::createFromFormat('H:i', $last['end_time']) : Carbon::createFromFormat('H:i', '10:00');
                $end = $start->copy()->addMinutes($this->editBreakDuration);
                $newConfig[] = [
                    'period_number' => 0,
                    'name' => $this->editBreakName,
                    'start_time' => $start->format('H:i'),
                    'end_time' => $end->format('H:i'),
                    'is_break' => true,
                    'type' => 'break',
                    'color' => $this->editBreakColor,
                ];
            }
            $config = $newConfig;
        } else {
            $parts = explode('-', $this->editBreakOriginalTime);
            $oldStart = $parts[0] ?? '';
            $oldEnd = $parts[1] ?? '';

            foreach ($config as &$item) {
                if (! empty($item['is_break']) && ($item['start_time'] === $oldStart || $item['name'] === $this->editBreakName)) {
                    $item['name'] = $this->editBreakName;
                    $item['color'] = $this->editBreakColor;
                    if ($this->editBreakStart && $this->editBreakEnd) {
                        $item['start_time'] = $this->editBreakStart;
                        $item['end_time'] = $this->editBreakEnd;
                    } else {
                        $start = Carbon::createFromFormat('H:i', $item['start_time']);
                        $end = $start->copy()->addMinutes($this->editBreakDuration);
                        $item['end_time'] = $end->format('H:i');
                    }
                }
            }
            unset($item);
        }

        $structure->update(['periods_configuration' => $config]);
        $this->showEditBreakModal = false;
        session()->flash('success', "Break \"{$this->editBreakName}\" saved successfully.");
    }

    public function deleteBreak(): void
    {
        abort_unless(auth()->user()->can('edit timetable'), 403);

        $structure = $this->activeStructure;
        if (! $structure) {
            return;
        }

        $config = $structure->periods_configuration ?? [];
        $parts = explode('-', $this->editBreakOriginalTime);
        $oldStart = $parts[0] ?? '';

        $config = array_values(array_filter($config, function ($item) use ($oldStart) {
            if (! empty($item['is_break']) && ($item['start_time'] === $oldStart || $item['name'] === $this->editBreakName)) {
                return false;
            }

            return true;
        }));

        $structure->update(['periods_configuration' => $config]);
        $this->showEditBreakModal = false;
        session()->flash('success', "Rest break \"{$this->editBreakName}\" removed.");
    }

    public function openAddLessonModal(?string $day = null, ?string $period = null): void
    {
        abort_unless(auth()->user()->can('create timetable') || auth()->user()->can('edit timetable'), 403);

        $day = $day ?? 'Monday';

        if (! $period) {
            $periods = $this->teachingPeriods;
            $period = $periods->first()['time'] ?? '08:00-08:40';
        }

        $this->openLessonModal($day, $period, null, $this->isDraftMode);
    }

    public function updatedLessonSubject(string $subjectName): void
    {
        if (empty($subjectName) || ! $this->selectedClass) {
            return;
        }

        $subject = Subject::where('institution_id', $this->selectedClass->institution_id)
            ->where('name', $subjectName)
            ->first();

        if ($subject) {
            $assignment = SubjectTeacher::where('class_id', $this->selectedClass->id)
                ->where('subject_id', $subject->id)
                ->first();

            if ($assignment?->teacher_id) {
                $this->lessonTeacherId = $assignment->teacher_id;
            }
        }
    }

    public function openLessonModal(string $day, string $period, ?int $entryId = null, bool $isDraft = false): void
    {
        abort_unless(auth()->user()->can('edit timetable') || auth()->user()->can('create timetable'), 403);

        $this->resetErrorBag();
        $this->resetValidation();

        $this->lessonDay = $day;
        $this->lessonPeriod = $period;
        $this->lessonEntryId = $entryId;
        $this->lessonIsDraft = $isDraft;
        $this->lessonIsDouble = false;

        if ($entryId) {
            if ($isDraft) {
                $draftEntry = TimetableDraftEntry::find($entryId);
                $this->lessonSubject = $draftEntry?->subject_title ?? '';
                $this->lessonTeacherId = $draftEntry?->teacher_id;
                $this->lessonRoom = $draftEntry?->room ?? '';
                $this->lessonIsDouble = $draftEntry?->isDoubleLesson() ?? false;
            } else {
                $liveEntry = TimetableEntry::find($entryId);
                $this->lessonSubject = $liveEntry?->subject ?? '';
                $this->lessonTeacherId = $liveEntry?->teacher_id;
                $this->lessonRoom = $liveEntry?->room ?? '';
                $this->lessonIsDouble = $liveEntry?->isDoubleLesson() ?? false;
            }
        } else {
            $this->lessonSubject = '';
            $this->lessonTeacherId = null;
            $this->lessonRoom = '';
            $this->lessonIsDouble = false;
        }

        $this->showLessonModal = true;
    }

    public function saveLesson(): void
    {
        abort_unless(auth()->user()->can('edit timetable') || auth()->user()->can('create timetable'), 403);

        $this->resetErrorBag();

        $this->validate([
            'lessonSubject' => ['required', 'string', 'max:100'],
            'lessonTeacherId' => ['nullable', 'exists:users,id'],
            'lessonRoom' => ['nullable', 'string', 'max:50'],
        ]);

        $times = explode('-', $this->lessonPeriod);
        $start = $times[0] ?? '08:00';
        $end = $times[1] ?? '08:40';

        $instId = $this->selectedClass?->institution_id
            ?? auth()->user()->active_institution_id
            ?? currentInstitution()?->id
            ?? auth()->user()->institution_id
            ?? 0;

        // Verify teacher qualification/assignment for this subject
        if ($this->lessonTeacherId && filled($this->lessonSubject)) {
            $subjObj = Subject::where('institution_id', $instId)
                ->where('name', $this->lessonSubject)
                ->first();

            if ($subjObj) {
                $hasAnyQualifications = TeacherSubjects::where('subject_id', $subjObj->id)->exists()
                    || SubjectTeacher::where('institution_id', $instId)->where('subject_id', $subjObj->id)->exists();

                if ($hasAnyQualifications) {
                    $isAssigned = $this->selectedClass && SubjectTeacher::where('class_id', $this->selectedClass->id)
                        ->where('subject_id', $subjObj->id)
                        ->where('teacher_id', $this->lessonTeacherId)
                        ->exists();

                    $isQualified = TeacherSubjects::where('subject_id', $subjObj->id)
                        ->where('teacher_id', $this->lessonTeacherId)
                        ->exists();

                    $teachesSubjectElsewhere = SubjectTeacher::where('institution_id', $instId)
                        ->where('subject_id', $subjObj->id)
                        ->where('teacher_id', $this->lessonTeacherId)
                        ->exists();

                    if (! $isAssigned && ! $isQualified && ! $teachesSubjectElsewhere) {
                        $teacherName = User::find($this->lessonTeacherId)?->name ?? 'This teacher';
                        $this->addError('lessonTeacherId', "Subject Mismatch: {$teacherName} is not registered or qualified to teach {$this->lessonSubject}.");

                        return;
                    }
                }
            }
        }

        // Check teacher conflict for period 1 (excluding self when editing)
        if ($this->lessonTeacherId && ! $this->lessonIsDraft) {
            $conflict1 = SaveTimetableEntry::teacherIsDoubleBooked([
                'teacher_id' => $this->lessonTeacherId,
                'day_of_week' => $this->lessonDay,
                'start_time' => $start,
                'end_time' => $end,
            ], $this->lessonEntryId);

            if ($conflict1) {
                $clash = TimetableEntry::with('schoolClass')
                    ->where('teacher_id', $this->lessonTeacherId)
                    ->where('day_of_week', $this->lessonDay)
                    ->when($this->lessonEntryId, fn ($q) => $q->whereKeyNot($this->lessonEntryId))
                    ->where(function ($query) use ($start, $end) {
                        $query->where('start_time', '<', $end)
                            ->where('end_time', '>', $start);
                    })
                    ->first();

                $className = $clash?->schoolClass?->name ?? 'another class';
                $this->addError('lessonTeacherId', "Teacher Clash: This teacher is already scheduled to teach {$className} on {$this->lessonDay} ({$start}-{$end}).");

                return;
            }
        }

        if ($this->lessonTeacherId && $this->lessonIsDraft) {
            $draftClash = TimetableDraftEntry::with('schoolClass')
                ->where('draft_id', $this->activeDraftId)
                ->where('teacher_id', $this->lessonTeacherId)
                ->where('day_of_week', $this->lessonDay)
                ->when($this->lessonEntryId, fn ($q) => $q->whereKeyNot($this->lessonEntryId))
                ->where(function ($query) use ($start, $end) {
                    $query->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
                })
                ->first();

            if ($draftClash) {
                $className = $draftClash->schoolClass?->name ?? 'another class';
                $this->addError('lessonTeacherId', "Teacher Clash: This teacher is already scheduled to teach {$className} on {$this->lessonDay} ({$start}-{$end}) in this draft.");

                return;
            }
        }

        $nextPeriod = null;
        if ($this->lessonIsDouble) {
            $nextPeriod = $this->activeStructure?->getNextConsecutiveTeachingPeriod($this->lessonPeriod);
            if (! $nextPeriod) {
                $this->addError('lessonIsDouble', 'A double lesson cannot be scheduled here because a break interval follows or this is the last period.');

                return;
            }

            // Check if subsequent period already has a different subject with lessons
            if (! $this->lessonIsDraft && $this->selectedClass) {
                $institutionId = currentInstitution()?->id ?? auth()->user()->institution_id;
                $nextStart = $nextPeriod['start_time'];
                $nextEnd = $nextPeriod['end_time'];
                $existingNext = TimetableEntry::where('institution_id', $institutionId)
                    ->where('class_id', $this->selectedClass->id)
                    ->where('day_of_week', $this->lessonDay)
                    ->where(function ($q) use ($nextStart) {
                        $q->where('start_time', $nextStart)->orWhere('start_time', $nextStart.':00');
                    })
                    ->where(function ($q) use ($nextEnd) {
                        $q->where('end_time', $nextEnd)->orWhere('end_time', $nextEnd.':00');
                    })
                    ->first();

                if ($existingNext && $existingNext->subject !== $this->lessonSubject && $existingNext->lessons()->exists()) {
                    $this->addError('lessonIsDouble', "Period 2 ({$nextStart}-{$nextEnd}) already has \"{$existingNext->subject}\" with recorded attendance. Move or delete that lesson first.");

                    return;
                }
            }

            // Check teacher conflict for period 2
            if ($this->lessonTeacherId) {
                if (! $this->lessonIsDraft) {
                    $conflict2 = SaveTimetableEntry::teacherIsDoubleBooked([
                        'teacher_id' => $this->lessonTeacherId,
                        'day_of_week' => $this->lessonDay,
                        'start_time' => $nextPeriod['start_time'],
                        'end_time' => $nextPeriod['end_time'],
                    ]);
                    if ($conflict2) {
                        $clash2 = TimetableEntry::with('schoolClass')
                            ->where('teacher_id', $this->lessonTeacherId)
                            ->where('day_of_week', $this->lessonDay)
                            ->where(function ($query) use ($nextPeriod) {
                                $query->where('start_time', '<', $nextPeriod['end_time'])
                                    ->where('end_time', '>', $nextPeriod['start_time']);
                            })
                            ->first();

                        $className2 = $clash2?->schoolClass?->name ?? 'another class';
                        $this->addError('lessonTeacherId', "Teacher Clash (Period 2): This teacher is already scheduled to teach {$className2} on {$this->lessonDay} ({$nextPeriod['start_time']}-{$nextPeriod['end_time']}).");

                        return;
                    }
                } else {
                    $draftClash2 = TimetableDraftEntry::with('schoolClass')
                        ->where('draft_id', $this->activeDraftId)
                        ->where('teacher_id', $this->lessonTeacherId)
                        ->where('day_of_week', $this->lessonDay)
                        ->where(function ($query) use ($nextPeriod) {
                            $query->where('start_time', '<', $nextPeriod['end_time'])
                                ->where('end_time', '>', $nextPeriod['start_time']);
                        })
                        ->first();

                    if ($draftClash2) {
                        $className2 = $draftClash2->schoolClass?->name ?? 'another class';
                        $this->addError('lessonTeacherId', "Teacher Clash (Period 2): This teacher is already scheduled to teach {$className2} on {$this->lessonDay} ({$nextPeriod['start_time']}-{$nextPeriod['end_time']}) in this draft.");

                        return;
                    }
                }
            }
        }

        // Unlink twin if unchecking double lesson on an existing slot
        if (! $this->lessonIsDouble && $this->lessonEntryId) {
            $nextP = $this->activeStructure?->getNextConsecutiveTeachingPeriod($this->lessonPeriod);
            if ($nextP) {
                $nPStart = $nextP['start_time'];
                $nPEnd = $nextP['end_time'];
                if ($this->lessonIsDraft && $this->activeDraftId) {
                    TimetableDraftEntry::where('draft_id', $this->activeDraftId)
                        ->where('day_of_week', $this->lessonDay)
                        ->where(function ($q) use ($nPStart) {
                            $q->where('start_time', $nPStart)->orWhere('start_time', $nPStart.':00');
                        })
                        ->where(function ($q) use ($nPEnd) {
                            $q->where('end_time', $nPEnd)->orWhere('end_time', $nPEnd.':00');
                        })
                        ->where('notes', '[Double Lesson Part 2/2]')
                        ->update(['notes' => null]);
                } else {
                    $instId = currentInstitution()?->id ?? auth()->user()->institution_id;
                    TimetableEntry::where('institution_id', $instId)
                        ->where('class_id', $this->selectedClass?->id)
                        ->where('day_of_week', $this->lessonDay)
                        ->where(function ($q) use ($nPStart) {
                            $q->where('start_time', $nPStart)->orWhere('start_time', $nPStart.':00');
                        })
                        ->where(function ($q) use ($nPEnd) {
                            $q->where('end_time', $nPEnd)->orWhere('end_time', $nPEnd.':00');
                        })
                        ->where('notes', '[Double Lesson Part 2/2]')
                        ->update(['notes' => null]);
                }
            }
        }

        $notes1 = $this->lessonIsDouble ? '[Double Lesson Part 1/2]' : null;
        $notes2 = $this->lessonIsDouble ? '[Double Lesson Part 2/2]' : null;

        if ($this->lessonIsDraft && $this->activeDraftId) {
            if ($this->lessonEntryId) {
                TimetableDraftEntry::where('id', $this->lessonEntryId)->update([
                    'subject_title' => $this->lessonSubject,
                    'teacher_id' => $this->lessonTeacherId,
                    'room' => $this->lessonRoom,
                    'notes' => $notes1,
                ]);
            } else {
                TimetableDraftEntry::create([
                    'draft_id' => $this->activeDraftId,
                    'class_id' => $this->selectedClass?->id ?? 1,
                    'subject_id' => null,
                    'subject_title' => $this->lessonSubject,
                    'teacher_id' => $this->lessonTeacherId,
                    'day_of_week' => $this->lessonDay,
                    'start_time' => $start,
                    'end_time' => $end,
                    'room' => $this->lessonRoom,
                    'notes' => $notes1,
                ]);
            }

            if ($this->lessonIsDouble && $nextPeriod) {
                TimetableDraftEntry::updateOrCreate(
                    [
                        'draft_id' => $this->activeDraftId,
                        'class_id' => $this->selectedClass?->id ?? 1,
                        'day_of_week' => $this->lessonDay,
                        'start_time' => $nextPeriod['start_time'],
                        'end_time' => $nextPeriod['end_time'],
                    ],
                    [
                        'institution_id' => currentInstitution()?->id ?? auth()->user()->institution_id,
                        'subject_id' => null,
                        'subject_title' => $this->lessonSubject,
                        'teacher_id' => $this->lessonTeacherId,
                        'room' => $this->lessonRoom,
                        'notes' => $notes2,
                    ]
                );
            }
        } else {
            $classId = $this->selectedClass?->id;
            if (! $classId) {
                session()->flash('error', 'Please select a class first.');

                return;
            }

            $institutionId = currentInstitution()?->id ?? auth()->user()->institution_id;

            if ($this->lessonEntryId) {
                $entry = TimetableEntry::findOrFail($this->lessonEntryId);
                $entry->update([
                    'subject' => $this->lessonSubject,
                    'teacher_id' => $this->lessonTeacherId,
                    'room' => $this->lessonRoom,
                    'notes' => $notes1,
                ]);
            } else {
                TimetableEntry::create([
                    'institution_id' => $institutionId,
                    'class_id' => $classId,
                    'class_name' => $this->selectedClass?->name,
                    'day_of_week' => $this->lessonDay,
                    'start_time' => $start,
                    'end_time' => $end,
                    'subject' => $this->lessonSubject,
                    'teacher_id' => $this->lessonTeacherId,
                    'room' => $this->lessonRoom,
                    'notes' => $notes1,
                ]);
            }

            if ($this->lessonIsDouble && $nextPeriod) {
                TimetableEntry::updateOrCreate(
                    [
                        'institution_id' => $institutionId,
                        'class_id' => $classId,
                        'day_of_week' => $this->lessonDay,
                        'start_time' => $nextPeriod['start_time'],
                        'end_time' => $nextPeriod['end_time'],
                    ],
                    [
                        'class_name' => $this->selectedClass?->name,
                        'subject' => $this->lessonSubject,
                        'teacher_id' => $this->lessonTeacherId,
                        'room' => $this->lessonRoom,
                        'notes' => $notes2,
                    ]
                );
            }
        }

        $this->showLessonModal = false;
        $successMsg = $this->lessonIsDouble
            ? "Scheduled 2-period Double Lesson for {$this->lessonSubject} on {$this->lessonDay} ({$this->lessonPeriod} + {$nextPeriod['start_time']}-{$nextPeriod['end_time']})."
            : "Saved lesson for {$this->lessonSubject} on {$this->lessonDay} ({$this->lessonPeriod}).";
        session()->flash('success', $successMsg);
    }

    #[Computed]
    public function isTeacher(): bool
    {
        return auth()->user()->hasRole('Teacher');
    }

    #[Computed]
    public function isStudent(): bool
    {
        return auth()->user()->hasRole('Student');
    }

    #[Computed]
    public function isParent(): bool
    {
        return auth()->user()->hasRole('Parent');
    }

    #[Computed]
    public function activeDraft(): ?TimetableDraft
    {
        if (! $this->activeDraftId) {
            return null;
        }

        return TimetableDraft::with(['structure'])
            ->withCount('draftEntries')
            ->where('institution_id', currentInstitution()?->id ?? 0)
            ->find($this->activeDraftId);
    }

    #[Computed]
    public function isDraftMode(): bool
    {
        return $this->activeDraft !== null && $this->activeDraft->isDraft();
    }

    #[Computed]
    public function availableDrafts(): Collection
    {
        if (! auth()->user()->can('create timetable')) {
            return collect();
        }

        $instId = currentInstitution()?->id ?? 0;

        return TimetableDraft::where('institution_id', $instId)
            ->where('status', 'draft')
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function classes(): Collection
    {
        $user = auth()->user();

        if ($this->isStudent || $this->isParent) {
            $classIds = $this->isParent
                ? StudentDetails::where('parent_id', $user->id)->pluck('class_id')
                : collect([StudentDetails::where('student_id', $user->id)->value('class_id')]);

            $classIds = $classIds->filter()->map(fn ($id) => (int) $id)->unique()->values();

            return SchoolClass::whereIn('id', $classIds)->orderBy('name')->get();
        }

        $institutionId = $user->active_institution_id
            ?? currentInstitution()?->id
            ?? $user->institution_id
            ?? 0;

        return app(StudentCacheService::class)->getClasses($institutionId, isAdmin() && ! $institutionId);
    }

    #[Computed]
    public function showPicker(): bool
    {
        return ! $this->isStudent && ! ($this->isParent && $this->classes->count() <= 1);
    }

    #[Computed]
    public function selectedClass(): ?SchoolClass
    {
        if ($this->classId === '') {
            return null;
        }

        return SchoolClass::with(['classTeacher', 'curriculum'])->find((int) $this->classId)
            ?? $this->classes->firstWhere('id', (int) $this->classId);
    }

    #[Computed]
    public function entries(): Collection
    {
        if ($this->isDraftMode) {
            $class = $this->selectedClass;

            return $class
                ? TimetableDraftEntry::with(['teacher', 'subject', 'schoolClass'])
                    ->where('draft_id', $this->activeDraftId)
                    ->where('class_id', $class->id)
                    ->get()
                : collect();
        }

        if ($this->isTeacher) {
            return TimetableEntry::with(['schoolClass', 'subjectRecord', 'teacher'])
                ->where('teacher_id', auth()->id())
                ->get();
        }

        $class = $this->selectedClass;

        return $class
            ? TimetableEntry::with(['teacher', 'subjectRecord', 'schoolClass'])->where('class_id', $class->id)->get()
            : collect();
    }

    #[Computed]
    public function activeStructure(): ?TimetableStructure
    {
        if ($this->isDraftMode && $this->activeDraft?->structure) {
            return $this->activeDraft->structure;
        }

        $institutionId = $this->selectedClass?->institution_id
            ?? auth()->user()->active_institution_id
            ?? currentInstitution()?->id
            ?? auth()->user()->institution_id;
        if (! $institutionId) {
            return null;
        }

        return TimetableStructure::where('institution_id', $institutionId)
            ->where('is_default', true)
            ->orderByDesc('id')
            ->first()
            ?? TimetableStructure::where('institution_id', $institutionId)->orderByDesc('id')->first();
    }

    #[Computed]
    public function teachers(): Collection
    {
        if (! $this->showLessonModal) {
            return collect();
        }

        $institutionId = $this->selectedClass?->institution_id
            ?? auth()->user()->active_institution_id
            ?? currentInstitution()?->id
            ?? auth()->user()->institution_id
            ?? 0;

        $query = User::role('Teacher')->with('teacherUserDetails');

        if ($institutionId) {
            $query->whereHas('teacherUserDetails', fn ($q) => $q->where('institution_id', $institutionId));
        }

        $allTeachers = $query->orderBy('name')->get();

        // If a subject is selected, filter teachers to those qualified or assigned
        if (filled($this->lessonSubject)) {
            $subject = Subject::where('institution_id', $institutionId)
                ->where('name', $this->lessonSubject)
                ->first();

            if ($subject) {
                // 1. Teachers assigned to this subject for this class
                $assignedTeacherIds = $this->selectedClass
                    ? SubjectTeacher::where('class_id', $this->selectedClass->id)
                        ->where('subject_id', $subject->id)
                        ->pluck('teacher_id')
                        ->toArray()
                    : [];

                // 2. Teachers with general subject qualification
                $qualifiedTeacherIds = TeacherSubjects::where('subject_id', $subject->id)
                    ->pluck('teacher_id')
                    ->toArray();

                // 3. Teachers assigned to this subject in any class in the institution
                $institutionalSubjectTeacherIds = SubjectTeacher::where('institution_id', $institutionId)
                    ->where('subject_id', $subject->id)
                    ->pluck('teacher_id')
                    ->toArray();

                $validTeacherIds = array_unique(array_merge(
                    $assignedTeacherIds,
                    $qualifiedTeacherIds,
                    $institutionalSubjectTeacherIds
                ));

                if (! empty($validTeacherIds)) {
                    $filtered = $allTeachers->whereIn('id', $validTeacherIds)->values();
                    if ($filtered->isNotEmpty()) {
                        return $filtered;
                    }
                }
            }
        }

        return $allTeachers;
    }

    #[Computed]
    public function subjects(): Collection
    {
        if (! $this->showLessonModal) {
            return collect();
        }

        $institutionId = $this->selectedClass?->institution_id
            ?? auth()->user()->active_institution_id
            ?? currentInstitution()?->id
            ?? auth()->user()->institution_id
            ?? 0;

        $query = Subject::where('is_active', true);

        if ($institutionId) {
            $query->where('institution_id', $institutionId);
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function teachingPeriods(): Collection
    {
        $structure = $this->activeStructure;
        if ($structure && ! empty($structure->getAllScheduleIntervals())) {
            return collect($structure->getAllScheduleIntervals())
                ->filter(fn ($it) => empty($it['is_break']))
                ->map(function ($it) {
                    return [
                        'time' => $it['start_time'].'-'.$it['end_time'],
                        'label' => ($it['name'] ?? ('Period '.$it['period_number'])).' ('.$it['start_time'].'-'.$it['end_time'].')',
                    ];
                })
                ->values();
        }

        return collect([
            ['time' => '08:00-08:40', 'label' => 'Period 1 (08:00-08:40)'],
            ['time' => '08:40-09:20', 'label' => 'Period 2 (08:40-09:20)'],
            ['time' => '09:20-10:00', 'label' => 'Period 3 (09:20-10:00)'],
            ['time' => '10:30-11:10', 'label' => 'Period 4 (10:30-11:10)'],
            ['time' => '11:10-11:50', 'label' => 'Period 5 (11:10-11:50)'],
            ['time' => '11:50-12:30', 'label' => 'Period 6 (11:50-12:30)'],
            ['time' => '14:00-14:40', 'label' => 'Period 7 (14:00-14:40)'],
            ['time' => '14:40-15:20', 'label' => 'Period 8 (14:40-15:20)'],
        ]);
    }

    #[Computed]
    public function periods(): Collection
    {
        $structure = $this->activeStructure;
        if ($structure && ! empty($structure->getAllScheduleIntervals())) {
            return collect($structure->getAllScheduleIntervals())->map(function ($it) {
                return [
                    'time' => $it['start_time'].'-'.$it['end_time'],
                    'name' => $it['name'] ?? ('Period '.$it['period_number']),
                    'period_number' => $it['period_number'] ?? null,
                    'is_break' => ! empty($it['is_break']),
                    'color' => $it['color'] ?? 'amber',
                    'type' => $it['type'] ?? 'lesson',
                ];
            })->values();
        }

        return $this->entries
            ->map(fn ($entry) => $this->periodKey($entry))
            ->unique()
            ->sort()
            ->values();
    }

    #[Computed]
    public function grid(): array
    {
        $grid = [];

        foreach ($this->entries as $entry) {
            $key = $this->periodKey($entry);
            $grid[$entry->day_of_week][$key] = $entry;
            if ($entry->period_number) {
                $grid[$entry->day_of_week]['p_'.$entry->period_number] = $entry;
            }
        }

        return $grid;
    }

    public function days(): array
    {
        if ($this->isDraftMode && $this->activeDraft?->structure?->days_of_week) {
            return $this->activeDraft->structure->days_of_week;
        }

        return SaveTimetableEntry::DAYS;
    }

    private function periodKey($entry): string
    {
        return $entry->start_time?->format('H:i').'-'.$entry->end_time?->format('H:i');
    }

    public function openGeneratorModal(): void
    {
        abort_unless(auth()->user()->can('create timetable'), 403);
        $this->generatorName = 'School Schedule - '.now()->format('M Y');
        $this->wizardStep = 1;
        $this->showGeneratorModal = true;
    }

    public function closeGeneratorModal(): void
    {
        $this->showGeneratorModal = false;
        $this->wizardStep = 1;
    }

    public function nextWizardStep(): void
    {
        if ($this->wizardStep < 3) {
            $this->wizardStep++;
        }
    }

    public function prevWizardStep(): void
    {
        if ($this->wizardStep > 1) {
            $this->wizardStep--;
        }
    }

    #[Computed]
    public function preflightReport(): ?PreflightReport
    {
        if (! $this->showGeneratorModal || $this->wizardStep !== 3) {
            return null;
        }

        $institutionId = currentInstitution()?->id;
        if (! $institutionId) {
            return null;
        }

        $periodsConfig = TimetableStructure::buildCustomConfiguration([
            'school_start_time' => $this->schoolStartTime,
            'lesson_duration' => $this->lessonDuration,
            'daily_periods' => $this->dailyPeriods,
            'has_assembly' => $this->hasAssembly,
            'assembly_duration' => $this->assemblyDuration,
            'breaks' => $this->breaks,
            'has_games' => $this->hasGames,
            'games_duration' => $this->gamesDuration,
        ]);

        $structure = new TimetableStructure([
            'institution_id' => $institutionId,
            'name' => 'Custom Bell Routine ('.$this->lessonDuration.'m, '.count($this->breaks).' breaks)',
            'days_of_week' => ! empty($this->activeDays) ? array_values($this->activeDays) : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'periods_configuration' => $periodsConfig,
        ]);

        $scopeId = in_array($this->generatorScope, ['class', 'stream'], true) && filled($this->classId) ? (int) $this->classId : null;

        return app(TimetablePreflightService::class)->check($institutionId, $structure, $this->generatorScope, $scopeId);
    }

    public function runAutomaticGenerator(TimetableGeneratorService $generator): void
    {
        abort_unless(auth()->user()->can('create timetable'), 403);

        $institutionId = currentInstitution()?->id;
        abort_unless($institutionId, 422, 'No institution selected.');

        // Build tailored bell schedule structure in-memory (persisted atomically only on successful generation)
        $periodsConfig = TimetableStructure::buildCustomConfiguration([
            'school_start_time' => $this->schoolStartTime,
            'lesson_duration' => $this->lessonDuration,
            'daily_periods' => $this->dailyPeriods,
            'has_assembly' => $this->hasAssembly,
            'assembly_duration' => $this->assemblyDuration,
            'breaks' => $this->breaks,
            'has_games' => $this->hasGames,
            'games_duration' => $this->gamesDuration,
        ]);

        $customStructure = new TimetableStructure([
            'institution_id' => $institutionId,
            'name' => 'Custom Bell Routine ('.$this->lessonDuration.'m, '.count($this->breaks).' breaks)',
            'days_of_week' => ! empty($this->activeDays) ? array_values($this->activeDays) : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'periods_configuration' => $periodsConfig,
        ]);

        $options = [
            'structure' => $customStructure,
            'make_default' => false,
            'scope_type' => $this->generatorScope,
            'scope_id' => in_array($this->generatorScope, ['class', 'stream'], true) && filled($this->classId) ? (int) $this->classId : null,
            'draft_name' => filled($this->generatorName) ? $this->generatorName : ('Academic Draft - '.now()->format('M Y')),
            'academic_year' => $this->generatorAcademicYear,
            'term' => $this->generatorTerm,
            'prefer_morning_high_cognitive' => $this->preferMorningHighCognitive,
            'created_by' => auth()->id(),
        ];

        try {
            $draft = $generator->generate($institutionId, $options);
            $this->activeDraftId = $draft->id;
            $this->showGeneratorModal = false;
            Cache::forget("inst_{$institutionId}_avail_drafts");
            Cache::forget("inst_{$institutionId}_default_structure");

            $conflictSummary = $draft->conflict_summary ?? [];
            $placed = $conflictSummary['total_placed'] ?? 0;
            $required = $conflictSummary['total_required'] ?? 0;

            session()->flash('success', "Timetable tailored to your school routine! {$placed} of {$required} lesson slots scheduled with custom colored breaks. Review slots in the studio below.");
        } catch (PreflightFailedException $e) {
            $count = count($e->report->blockers());
            session()->flash('error', "Cannot generate timetable: {$count} requirement(s) need attention. Review the checklist below.");
        } catch (Throwable $e) {
            session()->flash('error', 'Generation error: '.$e->getMessage());
        }
    }

    public function publishActiveDraft(TimetablePublishService $publisher): void
    {
        abort_unless(auth()->user()->can('create timetable'), 403);

        $draft = $this->activeDraft;
        abort_unless($draft, 404, 'No active draft selected.');

        $instId = currentInstitution()?->id ?? 0;

        try {
            $metrics = $publisher->publish($draft, auth()->id());
            $this->activeDraftId = null;
            $this->showPublishConfirmModal = false;
            Cache::forget("inst_{$instId}_avail_drafts");

            $msg = "Timetable successfully published! {$metrics['created_new']} new slots created, {$metrics['updated_in_place']} existing slots updated in place.";
            if ($metrics['historical_preserved'] > 0) {
                $msg .= " ({$metrics['historical_preserved']} historical lesson slots preserved to protect student attendance logs).";
            }

            session()->flash('success', $msg);
        } catch (Throwable $e) {
            $this->showPublishConfirmModal = false;
            session()->flash('error', 'Publishing error: '.$e->getMessage());
        }
    }

    public function discardActiveDraft(): void
    {
        abort_unless(auth()->user()->can('delete timetable'), 403);

        if ($this->activeDraft) {
            $this->activeDraft->delete();
        }

        $instId = currentInstitution()?->id ?? 0;
        Cache::forget("inst_{$instId}_avail_drafts");

        $this->activeDraftId = null;
        session()->flash('success', 'Draft timetable discarded.');
    }

    public function exitDraftMode(): void
    {
        $this->activeDraftId = null;
    }

    public function selectDraft(int $draftId): void
    {
        $this->activeDraftId = $draftId;
    }

    public function moveDraftEntry(
        int $draftEntryId,
        string $targetDay,
        string $targetStartTime,
        string $targetEndTime,
        ?int $targetPeriodNumber = null
    ): void {
        abort_unless(auth()->user()->can('create timetable'), 403);

        $entry = TimetableDraftEntry::where('draft_id', $this->activeDraftId)->findOrFail($draftEntryId);

        if ($entry->is_locked) {
            session()->flash('error', 'This slot is locked. Unlock it first before moving.');

            return;
        }

        // Check teacher collision in another class for this slot
        if ($entry->teacher_id) {
            $teacherConflict = TimetableDraftEntry::checkTeacherConflict(
                $this->activeDraftId,
                $entry->teacher_id,
                $targetDay,
                $targetStartTime,
                $targetEndTime,
                $entry->id
            );

            if ($teacherConflict) {
                $teacherName = $entry->teacher?->name ?? 'Teacher';
                session()->flash('error', "Collision prevented: {$teacherName} is already scheduled to teach another class on {$targetDay} at {$targetStartTime} - {$targetEndTime}.");

                return;
            }
        }

        // Check if target slot already contains another entry for this class (Swap scenario)
        $targetSlotEntry = TimetableDraftEntry::where('draft_id', $this->activeDraftId)
            ->where('class_id', $entry->class_id)
            ->where('day_of_week', $targetDay)
            ->where('start_time', $targetStartTime)
            ->where('end_time', $targetEndTime)
            ->whereKeyNot($entry->id)
            ->first();

        if ($targetSlotEntry) {
            if ($targetSlotEntry->is_locked) {
                session()->flash('error', 'Cannot swap: target slot is locked.');

                return;
            }

            if ($targetSlotEntry->teacher_id) {
                $swapConflict = TimetableDraftEntry::checkTeacherConflict(
                    $this->activeDraftId,
                    $targetSlotEntry->teacher_id,
                    $entry->day_of_week,
                    $entry->start_time->format('H:i'),
                    $entry->end_time->format('H:i'),
                    $targetSlotEntry->id
                );

                if ($swapConflict) {
                    session()->flash('error', 'Cannot swap: the other teacher is occupied in the source slot.');

                    return;
                }
            }

            $sourceDay = $entry->day_of_week;
            $sourceStart = $entry->start_time->format('H:i');
            $sourceEnd = $entry->end_time->format('H:i');
            $sourcePeriod = $entry->period_number;

            $entry->update([
                'day_of_week' => $targetDay,
                'start_time' => $targetStartTime,
                'end_time' => $targetEndTime,
                'period_number' => $targetPeriodNumber ?? $targetSlotEntry->period_number,
            ]);

            $targetSlotEntry->update([
                'day_of_week' => $sourceDay,
                'start_time' => $sourceStart,
                'end_time' => $sourceEnd,
                'period_number' => $sourcePeriod,
            ]);

            session()->flash('success', "Swapped {$entry->subject_title} and {$targetSlotEntry->subject_title} successfully.");

            return;
        }

        // Normal move to empty slot
        $entry->update([
            'day_of_week' => $targetDay,
            'start_time' => $targetStartTime,
            'end_time' => $targetEndTime,
            'period_number' => $targetPeriodNumber,
        ]);

        session()->flash('success', "Moved {$entry->subject_title} to {$targetDay} ({$targetStartTime} - {$targetEndTime}).");
    }

    public function toggleLockDraftEntry(int $draftEntryId): void
    {
        abort_unless(auth()->user()->can('create timetable'), 403);

        $entry = TimetableDraftEntry::where('draft_id', $this->activeDraftId)->findOrFail($draftEntryId);
        $entry->update(['is_locked' => ! $entry->is_locked]);

        $status = $entry->is_locked ? 'locked' : 'unlocked';
        session()->flash('success', "{$entry->subject_title} slot {$status}.");
    }

    public function deleteDraftEntry(int $draftEntryId): void
    {
        abort_unless(auth()->user()->can('delete timetable'), 403);

        $entry = TimetableDraftEntry::where('draft_id', $this->activeDraftId)->findOrFail($draftEntryId);
        $subjectName = $entry->subject_title;
        $notes = $entry->notes;
        $classId = $entry->class_id;
        $day = $entry->day_of_week;
        $start = $entry->start_time?->format('H:i');
        $end = $entry->end_time?->format('H:i');

        $entry->delete();

        // If part 1 was deleted, remove twin part 2 from draft
        if ($notes === '[Double Lesson Part 1/2]' && $start && $end) {
            $periodKey = "{$start}-{$end}";
            $structure = $this->activeStructure
                ?? ($this->activeDraft?->structure_id ? TimetableStructure::find($this->activeDraft->structure_id) : null);
            $nextP = $structure?->getNextConsecutiveTeachingPeriod($periodKey);
            if ($nextP) {
                $nPStart = $nextP['start_time'];
                $nPEnd = $nextP['end_time'];
                TimetableDraftEntry::where('draft_id', $this->activeDraftId)
                    ->where('class_id', $classId)
                    ->where('day_of_week', $day)
                    ->where(function ($q) use ($nPStart) {
                        $q->where('start_time', $nPStart)->orWhere('start_time', $nPStart.':00');
                    })
                    ->where(function ($q) use ($nPEnd) {
                        $q->where('end_time', $nPEnd)->orWhere('end_time', $nPEnd.':00');
                    })
                    ->where('notes', '[Double Lesson Part 2/2]')
                    ->delete();
            }
        } elseif ($notes === '[Double Lesson Part 2/2]') {
            TimetableDraftEntry::where('draft_id', $this->activeDraftId)
                ->where('class_id', $classId)
                ->where('day_of_week', $day)
                ->where('notes', '[Double Lesson Part 1/2]')
                ->update(['notes' => null]);
        }

        session()->flash('success', "Removed {$subjectName} from draft.");
    }

    public function deleteEntry(int $entryId): void
    {
        abort_unless(auth()->user()->can('delete timetable'), 403);

        $entry = TimetableEntry::findOrFail($entryId);

        if (! isAdmin()) {
            abort_unless($entry->institution_id === currentInstitutionId(), 403);
        }

        if ($entry->lessons()->exists()) {
            session()->flash('error', 'Cannot delete this lesson slot: historical student attendance logs are tied to it.');

            return;
        }

        $entryNotes = $entry->notes;
        $classId = $entry->class_id;
        $instId = $entry->institution_id;
        $day = $entry->day_of_week;
        $start = $entry->start_time?->format('H:i');
        $end = $entry->end_time?->format('H:i');

        $entry->delete();

        // If part 1 was deleted, clean up twin part 2
        if ($entryNotes === '[Double Lesson Part 1/2]' && $start && $end) {
            $periodKey = "{$start}-{$end}";
            $structure = $this->activeStructure
                ?? TimetableStructure::where('institution_id', $instId)->where('is_default', true)->orderByDesc('id')->first()
                ?? TimetableStructure::where('institution_id', $instId)->orderByDesc('id')->first();
            $nextP = $structure?->getNextConsecutiveTeachingPeriod($periodKey);
            if ($nextP) {
                $nPStart = $nextP['start_time'];
                $nPEnd = $nextP['end_time'];
                $twin = TimetableEntry::where('institution_id', $instId)
                    ->where('class_id', $classId)
                    ->where('day_of_week', $day)
                    ->where(function ($q) use ($nPStart) {
                        $q->where('start_time', $nPStart)->orWhere('start_time', $nPStart.':00');
                    })
                    ->where(function ($q) use ($nPEnd) {
                        $q->where('end_time', $nPEnd)->orWhere('end_time', $nPEnd.':00');
                    })
                    ->where('notes', '[Double Lesson Part 2/2]')
                    ->first();
                if ($twin) {
                    if (! $twin->lessons()->exists()) {
                        $twin->delete();
                    } else {
                        $twin->update(['notes' => null]);
                    }
                }
            }
        } elseif ($entryNotes === '[Double Lesson Part 2/2]') {
            TimetableEntry::where('institution_id', $instId)
                ->where('class_id', $classId)
                ->where('day_of_week', $day)
                ->where('notes', '[Double Lesson Part 1/2]')
                ->update(['notes' => null]);
        }

        session()->flash('success', 'Lesson slot removed from schedule.');
    }

    public function clearClassTimetable(): void
    {
        abort_unless(auth()->user()->can('delete timetable'), 403);

        $class = $this->selectedClass;
        if (! $class) {
            return;
        }

        if (! isAdmin()) {
            abort_unless($class->institution_id === currentInstitutionId(), 403);
        }

        $hasLessons = TimetableEntry::where('class_id', $class->id)->has('lessons')->exists();
        if ($hasLessons) {
            session()->flash('error', "Cannot clear timetable for {$class->name}: Past student attendance logs are attached to scheduled lessons. Please edit individual slots or use Timetable Generator to safely replace them.");

            return;
        }

        $count = TimetableEntry::where('class_id', $class->id)->delete();

        session()->flash('success', "Timetable for {$class->name} completely cleared ({$count} lessons deleted).");
    }
}; ?>
@php
    $isTeacher = $this->isTeacher;
    $isStudent = $this->isStudent;
    $isParent = $this->isParent;
    $showPicker = $this->showPicker;
    $classes = $this->classes;
    $selectedClass = $this->selectedClass;
    $entries = $this->entries;
    $periods = $this->periods;
    $grid = $this->grid;
    $days = $this->days();
    $isDraftMode = $this->isDraftMode;
    $activeDraft = $this->activeDraft;
    $availableDrafts = $this->availableDrafts;
    $canEdit = auth()->user()->can('edit timetable');
    $canDelete = auth()->user()->can('delete timetable');

    $currentDayName = now()->format('l');
    $totalWeeklyPeriods = $entries->count();
    $uniqueStreamsCount = $entries->pluck('class_id')->filter()->unique()->count();
    $uniqueSubjectsCount = $entries->map(fn ($e) => $isDraftMode ? $e->subject_title : ($e->subjectRecord?->name ?? $e->subject))->filter()->unique()->count();
    $todayPeriodsCount = $entries->where('day_of_week', $currentDayName)->count();
@endphp

    <div class="flex flex-col gap-6 w-full max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" x-data="{ viewMode: 'grid', draggedEntryId: null, hoveredSlot: null }">
        <style>
            @media print {
                aside, [role="navigation"], .no-print, [wire\:loading], button, a, .flux-sidebar, .flux-header, .kpi-cards {
                    display: none !important;
                }
                body {
                    background: #ffffff !important;
                    color: #0f172a !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }
            }
        </style>
        
        {{-- Header Section --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-indigo-50 text-indigo-700 border border-indigo-200/60 dark:bg-indigo-950/50 dark:text-indigo-400 dark:border-indigo-800/60 flex items-center gap-1.5">
                        <span class="size-1.5 rounded-full bg-indigo-500"></span>
                        Academic Schedule
                    </span>
                    <span class="text-xs text-zinc-400">&bull;</span>
                    <span class="text-xs text-zinc-500 font-medium">{{ currentInstitution()?->name ?? __('Institution') }}</span>
                    @if ($isDraftMode)
                        <span class="text-xs text-zinc-400">&bull;</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                            Draft Sandbox
                        </span>
                    @endif
                </div>

                <flux:heading size="xl" class="text-2xl font-black tracking-tight text-zinc-900 dark:text-white">
                    @if ($isTeacher)
                        My Academic Timetable
                    @elseif ($isStudent)
                        My Class Timetable
                    @elseif ($isParent)
                        My Child's Timetable
                    @else
                        {{ $isDraftMode ? 'Timetable Review Studio' : 'Master Class Timetables' }}
                    @endif
                </flux:heading>
                <flux:text class="text-zinc-500 text-xs sm:text-sm">
                    Manage weekly lesson distributions, period allocations, and venue locations.
                </flux:text>
            </div>
            
            <div class="flex items-center gap-2 flex-wrap">
                @if (! $isDraftMode)
                    <a href="{{ route('timetable.print', ['class_id' => $selectedClass?->id]) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700 shadow-2xs hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors">
                        <flux:icon icon="printer" class="size-3.5 text-zinc-400" />
                        Print Schedule
                    </a>

                    <x-export-buttons
                        base-route="timetable.export"
                        module="timetable"
                        :params="['class_id' => $selectedClass?->id]" />
                @endif

                @can('create timetable')
                    @if (! $isDraftMode)
                        <button type="button" 
                            wire:click="openGeneratorModal" 
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-linear-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white shadow-xs transition-all cursor-pointer">
                            <flux:icon icon="sparkles" class="size-3.5" />
                            Timetable Generator
                        </button>

                        @if ($availableDrafts->isNotEmpty())
                            <flux:dropdown>
                                <flux:button icon="document-text" variant="subtle" size="sm" class="font-semibold text-xs">
                                    Saved Drafts ({{ $availableDrafts->count() }})
                                </flux:button>
                                <flux:menu>
                                    @foreach ($availableDrafts as $d)
                                        <flux:menu.item wire:click="selectDraft({{ $d->id }})">
                                            {{ $d->name }} ({{ $d->generation_score ?? 100 }}%)
                                        </flux:menu.item>
                                    @endforeach
                                </flux:menu>
                            </flux:dropdown>
                        @endif

                        <flux:button href="{{ route('timetable.import') }}" wire:navigate.hover icon="arrow-up-tray" variant="subtle" class="font-semibold text-xs">
                            Import CSV
                        </flux:button>
                        <flux:button type="button" wire:click="openAddLessonModal" icon="plus" variant="primary" class="font-semibold text-xs shadow-sm bg-[#0047FF] hover:bg-[#0037cc] text-white">
                            Add Lesson
                        </flux:button>
                    @endif
                @endcan
            </div>
        </div>

        {{-- Draft Studio Action Banner --}}
        @if ($isDraftMode && $activeDraft)
            <div class="rounded-3xl border border-amber-300/80 bg-linear-to-r from-amber-50 via-amber-50/50 to-orange-50/40 dark:from-amber-950/40 dark:via-zinc-900 dark:to-orange-950/30 dark:border-amber-700/60 p-5 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-amber-500 text-white">
                                <flux:icon icon="sparkles" class="size-3" />
                                Draft Mode Active
                            </span>
                            <span class="font-bold text-zinc-900 dark:text-white text-base">
                                {{ $activeDraft->name }}
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded-md bg-white/80 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 font-mono">
                                {{ $activeDraft->academic_year }} &bull; {{ $activeDraft->term }}
                            </span>
                        </div>
                        <p class="text-xs text-zinc-600 dark:text-zinc-400">
                            Fitness Score: <strong class="text-emerald-600 dark:text-emerald-400 font-bold">{{ $activeDraft->generation_score ?? 100 }}%</strong> &bull;
                            Total Slots: <strong class="text-zinc-900 dark:text-white">{{ $activeDraft->draft_entries_count ?? $activeDraft->draftEntries()->count() }}</strong> &bull;
                            Breaks and assemblies are highlighted with custom colors. You can drag-and-drop any lesson block to swap or place.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 flex-wrap">
                        <flux:button wire:click="exitDraftMode" variant="subtle" size="sm" class="font-semibold text-xs">
                            Exit Studio
                        </flux:button>
                        <flux:button wire:click="confirmDelete('discard_draft')" variant="danger" size="sm" class="font-semibold text-xs">
                            Discard Draft
                        </flux:button>
                        <flux:button wire:click="$set('showPublishConfirmModal', true)" variant="primary" size="sm" class="font-bold text-xs bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm">
                            <flux:icon icon="check" class="size-3.5 mr-1" />
                            Publish to Live Schedule
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200/60 bg-emerald-50/60 dark:bg-emerald-950/40 dark:border-emerald-800/60 p-4 flex items-center gap-3 shadow-2xs">
                <flux:icon icon="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span class="text-xs font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200/60 bg-rose-50/60 dark:bg-rose-950/40 dark:border-rose-800/60 p-4 flex items-center gap-3 shadow-2xs">
                <flux:icon icon="exclamation-circle" class="size-5 text-rose-600 dark:text-rose-400 shrink-0" />
                <span class="text-xs font-medium text-rose-800 dark:text-rose-300">{{ session('error') }}</span>
            </div>
        @endif

        {{-- Analytical Metric Tiles --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-card 
                title="Weekly Load" 
                icon="clock" 
                color="indigo" 
                :count="$totalWeeklyPeriods . ' Periods'" 
                subtext="total weekly allocations" 
            />
            <x-kpi-card 
                title="Assigned Streams" 
                icon="rectangle-group" 
                color="sky" 
                :count="$uniqueStreamsCount . ' Classes'" 
                subtext="teaching class sections" 
            />
            <x-kpi-card 
                title="Unique Subjects" 
                icon="book-open" 
                color="emerald" 
                :count="$uniqueSubjectsCount . ' Subjects'" 
                subtext="curriculum disciplines" 
            />
            <x-kpi-card 
                title="Today's Sessions" 
                icon="calendar" 
                color="violet" 
                :count="$todayPeriodsCount . ' Sessions'" 
                :subtext="'scheduled for ' . $currentDayName" 
            />
        </div>

        {{-- View Switcher and Class Picker Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            {{-- Dual View Switcher Tabs --}}
            <div class="inline-flex p-1 bg-zinc-100 dark:bg-zinc-800 rounded-xl">
                <button 
                    type="button" 
                    @click="viewMode = 'grid'" 
                    :class="viewMode === 'grid' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-2xs font-bold' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white font-medium'"
                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all inline-flex items-center gap-1.5"
                >
                    <flux:icon icon="table-cells" class="size-3.5" />
                    Weekly Grid
                </button>
                <button 
                    type="button" 
                    @click="viewMode = 'agenda'" 
                    :class="viewMode === 'agenda' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-white shadow-2xs font-bold' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white font-medium'"
                    class="px-3.5 py-1.5 rounded-lg text-xs transition-all inline-flex items-center gap-1.5"
                >
                    <flux:icon icon="calendar-days" class="size-3.5" />
                    Daily Agenda
                </button>
            </div>

            {{-- Class Picker (when applicable) --}}
            @if ($showPicker)
                <div class="flex items-center gap-2">
                    <label for="class_picker" class="text-xs font-semibold text-zinc-500 shrink-0">Class Roster:</label>
                    <div class="relative flex items-center">
                        <select id="class_picker" wire:model.live="classId" class="text-xs font-medium bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl pl-3 pr-8 py-1.5 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden cursor-pointer transition">
                            <option value="">Select a class...</option>
                            @foreach ($classes as $schoolClass)
                                <option value="{{ $schoolClass->id }}">
                                    {{ $schoolClass->name }}
                                </option>
                            @endforeach
                        </select>
                        <div wire:loading wire:target="classId" class="absolute right-2.5 pointer-events-none flex items-center">
                            <flux:icon icon="arrow-path" class="size-3.5 animate-spin text-indigo-600 dark:text-indigo-400" />
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Schedule Display Card --}}
        <div class="relative bg-white dark:bg-zinc-900 border border-zinc-200/80 dark:border-zinc-800 rounded-3xl overflow-hidden shadow-xs">
            
            @if ($isTeacher && ! $isDraftMode)
                {{-- Interactive Edit Mode Active Banner --}}
                @if ($isEditMode)
                    <div class="m-3 p-3 rounded-2xl bg-gradient-to-r from-amber-50 to-indigo-50/60 dark:from-amber-950/40 dark:to-indigo-950/30 border border-amber-200/80 dark:border-amber-900/50 flex flex-wrap items-center justify-between gap-3 shadow-2xs animate-in fade-in slide-in-from-top-1 duration-150">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-black bg-amber-500 text-white shadow-2xs">
                                <span class="size-1.5 rounded-full bg-white animate-ping"></span>
                                EDIT MODE
                            </span>
                            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                Click any period time to adjust bell hours, click breaks to customize rest intervals &amp; colors, or click empty slots to add lessons.
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <flux:button type="button" wire:click="openNewBreakModal" variant="subtle" size="sm" class="text-xs font-bold border border-zinc-200 dark:border-zinc-700 bg-white/90 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 shadow-2xs">
                                <flux:icon icon="plus" class="size-3 text-amber-600" />
                                + Add Rest Break
                            </flux:button>
                            <flux:button type="button" wire:click="exitEditMode" variant="primary" size="sm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-2xs">
                                Done Editing
                            </flux:button>
                        </div>
                    </div>
                @endif

                @if ($periods->isEmpty())
                    <x-empty-state 
                        icon="calendar" 
                        title="No Schedule Assigned" 
                        description="You have no timetable entries assigned yet. Contact your school administrator."
                        color="indigo"
                    />
                @else
                    <div x-show="viewMode === 'grid'">
                        @include('timetable::partials._grid', ['grid' => $grid, 'days' => $days, 'periods' => $periods, 'isDraftMode' => false, 'isEditMode' => $isEditMode, 'canEdit' => $canEdit, 'canDelete' => $canDelete])
                    </div>
                    <div x-show="viewMode === 'agenda'">
                        @include('timetable::partials._agenda', ['entries' => $entries, 'days' => $days, 'currentDayName' => $currentDayName, 'isTeacher' => $isTeacher, 'canEdit' => $canEdit, 'canDelete' => $canDelete])
                    </div>
                @endif
                
            @elseif (($isStudent || $isParent) && $classes->isEmpty())
                <x-empty-state 
                    icon="academic-cap" 
                    title="No Class Assigned" 
                    description="{{ $isStudent ? 'You are not assigned to a class yet. Contact your school administrator.' : 'No child is assigned to a class yet. Contact your school administrator.' }}"
                    color="amber"
                />
                
            @elseif (! $isStudent && ! $isParent && $classes->isEmpty())
                <x-empty-state 
                    icon="rectangle-group" 
                    title="No Classes Available" 
                    description="There are no classes created in the system yet."
                    color="sky"
                />
                
            @elseif (!$selectedClass)
                <x-empty-state 
                    icon="cursor-arrow-rays" 
                    title="Select a Class" 
                    description="Choose a class from the dropdown above to view its timetable schedule."
                    color="indigo"
                />
                
            @else
                <div class="px-6 py-4 border-b border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3 flex-wrap">
                        <flux:heading size="lg" class="font-bold">
                            {{ $selectedClass->name }} {{ $isDraftMode ? 'Draft Preview' : 'Master Schedule' }}
                        </flux:heading>
                        @if ($selectedClass->classTeacher)
                            <div class="flex items-center gap-1.5 text-xs text-zinc-600 dark:text-zinc-400 bg-white dark:bg-zinc-800 px-3 py-1 rounded-full border border-zinc-200 dark:border-zinc-700">
                                <flux:icon icon="user" class="size-3.5 text-indigo-500" />
                                Class Teacher: <span class="font-bold text-zinc-900 dark:text-white">{{ $selectedClass->classTeacher->name }}</span>
                            </div>
                        @endif
                        @if ($selectedClass->curriculum)
                            <span class="text-xs px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 font-semibold border border-indigo-200 dark:border-indigo-800">
                                {{ $selectedClass->curriculum->systemLabel() }}
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        @can('edit timetable')
                            @if ($isEditMode)
                                <flux:button type="button" wire:click="exitEditMode" variant="primary" size="sm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-2xs">
                                    <flux:icon icon="check" class="size-3.5" />
                                    Done Editing
                                </flux:button>
                            @else
                                <flux:button type="button" wire:click="toggleEditMode" variant="subtle" size="sm" class="font-bold text-xs border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                    <flux:icon icon="pencil-square" class="size-3.5 text-indigo-500" />
                                    Edit Timetable
                                </flux:button>
                            @endif
                        @endcan

                        @if (! $isDraftMode)
                            @can('create timetable')
                                <flux:button type="button" wire:click="openAddLessonModal" icon="plus" size="sm" variant="subtle" class="font-semibold text-xs">
                                    Add Lesson
                                </flux:button>
                            @endcan

                            @can('delete timetable')
                                @if ($entries->isNotEmpty())
                                    <button type="button" 
                                        wire:click="confirmDelete('clear_class')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 dark:hover:bg-rose-900/60 border border-rose-200/80 dark:border-rose-800/60 transition-colors shadow-2xs">
                                        <flux:icon icon="trash" class="size-3.5 text-rose-600 dark:text-rose-400" />
                                        Clear Timetable
                                    </button>
                                @endif
                            @endcan
                        @else
                            <span class="text-xs text-zinc-500 italic">
                                Tip: Drag any cell into another to swap or place lessons
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Interactive Edit Mode Active Banner --}}
                @if ($isEditMode)
                    <div class="m-3 p-3 rounded-2xl bg-gradient-to-r from-amber-50 to-indigo-50/60 dark:from-amber-950/40 dark:to-indigo-950/30 border border-amber-200/80 dark:border-amber-900/50 flex flex-wrap items-center justify-between gap-3 shadow-2xs animate-in fade-in slide-in-from-top-1 duration-150">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-black bg-amber-500 text-white shadow-2xs">
                                <span class="size-1.5 rounded-full bg-white animate-ping"></span>
                                EDIT MODE
                            </span>
                            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                Click any period time to adjust bell hours, click breaks to customize rest intervals &amp; colors, or click empty slots to add lessons.
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <flux:button type="button" wire:click="openNewBreakModal" variant="subtle" size="sm" class="text-xs font-bold border border-zinc-200 dark:border-zinc-700 bg-white/90 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 shadow-2xs">
                                <flux:icon icon="plus" class="size-3 text-amber-600" />
                                + Add Rest Break
                            </flux:button>
                            <flux:button type="button" wire:click="exitEditMode" variant="primary" size="sm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-2xs">
                                Done Editing
                            </flux:button>
                        </div>
                    </div>
                @endif

                @if ($periods->isEmpty())
                    <x-empty-state 
                        icon="calendar" 
                        title="No Schedule Yet" 
                        description="There are no timetable entries recorded for this class yet. Use 'Timetable Generator' above to automatically produce an optimized schedule."
                        color="indigo"
                    />
                @else
                    <div x-show="viewMode === 'grid'">
                        @include('timetable::partials._grid', ['grid' => $grid, 'days' => $days, 'periods' => $periods, 'isDraftMode' => $isDraftMode, 'isEditMode' => $isEditMode, 'canEdit' => $canEdit, 'canDelete' => $canDelete])
                    </div>
                    <div x-show="viewMode === 'agenda'">
                        @include('timetable::partials._agenda', ['entries' => $entries, 'days' => $days, 'currentDayName' => $currentDayName, 'isTeacher' => $isTeacher, 'canEdit' => $canEdit, 'canDelete' => $canDelete])
                    </div>
                @endif
            @endif

        </div>

        {{-- Multi-Step Timetable Generator Wizard Modal with Custom Breaks Builder --}}
        @if ($showGeneratorModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-2xl w-full max-w-2xl overflow-hidden p-6 space-y-6 animate-in fade-in zoom-in duration-150">
                    
                    {{-- Wizard Header --}}
                    <div class="flex items-center justify-between pb-4 border-b border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 rounded-2xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400">
                                <flux:icon icon="sparkles" class="size-5" />
                            </div>
                            <div>
                                <flux:heading size="lg" class="font-bold">Timetable Generator Wizard</flux:heading>
                                <flux:text class="text-xs text-zinc-500">Configure your school's daily routine, custom breaks, and academic parameters</flux:text>
                            </div>
                        </div>
                        <button type="button" wire:click="closeGeneratorModal" class="p-1 rounded-lg text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                            <flux:icon icon="x-mark" class="size-5" />
                        </button>
                    </div>

                    {{-- Step Navigation Indicator --}}
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" wire:click="$set('wizardStep', 1)" class="p-2 rounded-xl text-left border transition-all {{ $wizardStep === 1 ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300' : 'border-zinc-200 dark:border-zinc-800 text-zinc-500' }}">
                            <div class="text-[10px] font-bold uppercase tracking-wider">Step 1</div>
                            <div class="text-xs font-semibold truncate">Daily Routine</div>
                        </button>
                        <button type="button" wire:click="$set('wizardStep', 2)" class="p-2 rounded-xl text-left border transition-all {{ $wizardStep === 2 ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300' : 'border-zinc-200 dark:border-zinc-800 text-zinc-500' }}">
                            <div class="text-[10px] font-bold uppercase tracking-wider">Step 2</div>
                            <div class="text-xs font-semibold truncate">Breaks Builder ({{ count($breaks) }})</div>
                        </button>
                        <button type="button" wire:click="$set('wizardStep', 3)" class="p-2 rounded-xl text-left border transition-all {{ $wizardStep === 3 ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300' : 'border-zinc-200 dark:border-zinc-800 text-zinc-500' }}">
                            <div class="text-[10px] font-bold uppercase tracking-wider">Step 3</div>
                            <div class="text-xs font-semibold truncate">Scope &amp; Optimization</div>
                        </button>
                    </div>

                    {{-- Wizard Step 1: Daily Schedule & Lesson Duration --}}
                    @if ($wizardStep === 1)
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">First Lesson Start Time</label>
                                    <flux:input type="time" wire:model="schoolStartTime" required />
                                    <span class="text-[10px] text-zinc-400 mt-0.5 block">e.g. 08:00 AM or 08:20 AM</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">Lesson Duration</label>
                                    <flux:select wire:model="lessonDuration">
                                        <flux:select.option value="35">35 mins (Primary)</flux:select.option>
                                        <flux:select.option value="40">40 mins (Standard Secondary)</flux:select.option>
                                        <flux:select.option value="45">45 mins (Extended)</flux:select.option>
                                        <flux:select.option value="50">50 mins</flux:select.option>
                                    </flux:select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">Lessons Per Day</label>
                                    <flux:select wire:model="dailyPeriods">
                                        <flux:select.option value="6">6 Periods / day</flux:select.option>
                                        <flux:select.option value="7">7 Periods / day</flux:select.option>
                                        <flux:select.option value="8">8 Periods / day (Standard)</flux:select.option>
                                        <flux:select.option value="9">9 Periods / day</flux:select.option>
                                        <flux:select.option value="10">10 Periods / day</flux:select.option>
                                    </flux:select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">Active Teaching Days</label>
                                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                                    @foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $d)
                                        <label class="flex items-center gap-1.5 p-2 rounded-xl border border-zinc-200 dark:border-zinc-800 text-xs font-medium cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                            <input type="checkbox" wire:model="activeDays" value="{{ $d }}" class="size-4 rounded text-indigo-600 focus:ring-indigo-500 border-zinc-300" />
                                            <span>{{ substr($d, 0, 3) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Morning Assembly --}}
                            <div class="p-3.5 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-xs font-bold text-zinc-900 dark:text-white">Morning Assembly</div>
                                        <div class="text-[10px] text-zinc-500">Held before Period 1 starts (e.g. on assembly days)</div>
                                    </div>
                                    <input type="checkbox" wire:model.live="hasAssembly" class="size-4 rounded text-indigo-600 focus:ring-indigo-500 border-zinc-300" />
                                </div>
                                @if ($hasAssembly)
                                    <div class="flex items-center gap-3 pt-2 border-t border-zinc-200/60 dark:border-zinc-700/60">
                                        <label class="text-xs text-zinc-600 dark:text-zinc-400">Duration:</label>
                                        <flux:select wire:model="assemblyDuration" class="w-32">
                                            <flux:select.option value="20">20 mins</flux:select.option>
                                            <flux:select.option value="30">30 mins</flux:select.option>
                                            <flux:select.option value="45">45 mins</flux:select.option>
                                        </flux:select>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Wizard Step 2: Dynamic Custom Breaks Builder --}}
                    @if ($wizardStep === 2)
                        <div class="space-y-4 max-h-[360px] overflow-y-auto pr-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-xs font-bold text-zinc-900 dark:text-white">School Breaks &amp; Meal Intervals</div>
                                    <div class="text-[10px] text-zinc-500">Add any breaks your school takes (after 1 lesson, 2 lessons, etc.) and assign custom colors</div>
                                </div>
                                <button type="button" wire:click="addBreak" class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1.5 rounded-xl bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition">
                                    <flux:icon icon="plus" class="size-3" />
                                    Add Break
                                </button>
                            </div>

                            <div class="space-y-3">
                                @forelse ($breaks as $index => $brk)
                                    <div class="p-3.5 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 space-y-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 flex-1">
                                                <input type="text" wire:model="breaks.{{ $index }}.name" placeholder="Break Name (e.g. Tea Break)" class="text-xs font-bold bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg px-2.5 py-1 text-zinc-900 dark:text-white w-48 focus:ring-1 focus:ring-indigo-500" />
                                                
                                                {{-- Color Picker --}}
                                                <select wire:model="breaks.{{ $index }}.color" class="text-xs font-semibold bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg px-2 py-1 text-zinc-700 dark:text-zinc-300">
                                                    <option value="amber">🟡 Amber / Gold</option>
                                                    <option value="emerald">🟢 Emerald / Green</option>
                                                    <option value="sky">🔵 Sky Blue</option>
                                                    <option value="indigo">🟣 Indigo / Purple</option>
                                                    <option value="rose">🔴 Rose / Pink</option>
                                                </select>
                                            </div>

                                            <button type="button" wire:click="removeBreak({{ $index }})" class="p-1 rounded-lg text-zinc-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition" title="Delete break">
                                                <flux:icon icon="trash" class="size-3.5" />
                                            </button>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-zinc-200/60 dark:border-zinc-700/60">
                                            <div>
                                                <label class="text-[11px] text-zinc-600 dark:text-zinc-400 block mb-1">Occurs directly after:</label>
                                                <select wire:model="breaks.{{ $index }}.after_period" class="w-full text-xs font-medium bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg px-2 py-1 text-zinc-800 dark:text-zinc-200">
                                                    @for ($p = 1; $p <= $dailyPeriods; $p++)
                                                        <option value="{{ $p }}">Period {{ $p }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                            <div>
                                                <label class="text-[11px] text-zinc-600 dark:text-zinc-400 block mb-1">Break Duration (mins):</label>
                                                <select wire:model="breaks.{{ $index }}.duration" class="w-full text-xs font-medium bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg px-2 py-1 text-zinc-800 dark:text-zinc-200">
                                                    <option value="10">10 mins (Short Changeover)</option>
                                                    <option value="15">15 mins</option>
                                                    <option value="20">20 mins</option>
                                                    <option value="25">25 mins</option>
                                                    <option value="30">30 mins (Standard Tea)</option>
                                                    <option value="40">40 mins</option>
                                                    <option value="45">45 mins (Lunch)</option>
                                                    <option value="60">60 mins (Full Lunch)</option>
                                                    <option value="75">75 mins</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="p-6 text-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl">
                                        <p class="text-xs text-zinc-400">No breaks configured yet. Click "Add Break" above to insert breaks after any period.</p>
                                    </div>
                                @endforelse
                            </div>

                            {{-- Evening Games/Clubs Toggle --}}
                            <div class="p-3 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 flex items-center justify-between">
                                <div>
                                    <div class="text-xs font-bold text-zinc-900 dark:text-white">Evening Games / Clubs / Activities</div>
                                    <div class="text-[10px] text-zinc-500">Scheduled after the final period of the day</div>
                                </div>
                                <input type="checkbox" wire:model="hasGames" class="size-4 rounded text-indigo-600 border-zinc-300" />
                            </div>
                        </div>
                    @endif

                    {{-- Wizard Step 3: Scope, Plan Title & Pedagogical Heuristics --}}
                    @if ($wizardStep === 3)
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">Timetable Name / Label</label>
                                <flux:input type="text" wire:model="generatorName" placeholder="e.g. Master Schedule Term 1 2026" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">Academic Year</label>
                                    <flux:input type="text" wire:model="generatorAcademicYear" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">Term</label>
                                    <flux:select wire:model="generatorTerm">
                                        <flux:select.option value="Term 1">Term 1</flux:select.option>
                                        <flux:select.option value="Term 2">Term 2</flux:select.option>
                                        <flux:select.option value="Term 3">Term 3</flux:select.option>
                                    </flux:select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-300 mb-1.5">Generation Target Scope</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <label class="cursor-pointer border rounded-2xl p-3 text-center transition-all {{ $generatorScope === 'whole_school' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30' : 'border-zinc-200 dark:border-zinc-800' }}">
                                        <input type="radio" wire:model.live="generatorScope" value="whole_school" class="sr-only" />
                                        <div class="text-xs font-bold text-zinc-900 dark:text-white">Whole School</div>
                                        <div class="text-[10px] text-zinc-500 mt-0.5">All classes &amp; streams</div>
                                    </label>
                                    <label class="cursor-pointer border rounded-2xl p-3 text-center transition-all {{ $generatorScope === 'class' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30' : 'border-zinc-200 dark:border-zinc-800' }}">
                                        <input type="radio" wire:model.live="generatorScope" value="class" class="sr-only" />
                                        <div class="text-xs font-bold text-zinc-900 dark:text-white">Current Class</div>
                                        <div class="text-[10px] text-zinc-500 mt-0.5">{{ $selectedClass?->name ?? 'Single class' }}</div>
                                    </label>
                                    <label class="cursor-pointer border rounded-2xl p-3 text-center transition-all {{ $generatorScope === 'stream' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30' : 'border-zinc-200 dark:border-zinc-800' }}">
                                        <input type="radio" wire:model.live="generatorScope" value="stream" class="sr-only" />
                                        <div class="text-xs font-bold text-zinc-900 dark:text-white">Grade Level</div>
                                        <div class="text-[10px] text-zinc-500 mt-0.5">{{ $selectedClass?->level ?? 'Same level' }}</div>
                                    </label>
                                </div>
                            </div>

                            <div class="p-3.5 rounded-2xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200/60 dark:border-zinc-700/60 space-y-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-xs font-bold text-zinc-900 dark:text-white">Kenyan Cognitive Rhythm Policy</div>
                                        <div class="text-[10px] text-zinc-500">Schedules Math, Sciences &amp; Languages in morning alert periods (Periods 1-4); PHE &amp; Arts in afternoon</div>
                                    </div>
                                    <input type="checkbox" wire:model="preferMorningHighCognitive" class="size-4 rounded text-indigo-600 border-zinc-300" />
                                </div>
                            </div>

                            {{-- Preflight Readiness Verification --}}
                            @if ($this->preflightReport)
                                <div class="space-y-3 pt-2">
                                    <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">Pre-Flight Readiness Check</div>

                                    @if ($this->preflightReport->hasBlockers())
                                        <div class="p-3.5 rounded-2xl bg-rose-50/70 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 space-y-2.5">
                                            <div class="flex items-center gap-2 text-rose-800 dark:text-rose-300 font-bold text-xs">
                                                <flux:icon icon="exclamation-triangle" class="size-4 text-rose-600 shrink-0" />
                                                <span>Cannot Generate Yet &mdash; {{ count($this->preflightReport->blockers()) }} Requirement(s) Missing</span>
                                            </div>
                                            <div class="space-y-2">
                                                @foreach ($this->preflightReport->blockers() as $blocker)
                                                    <div class="text-xs bg-white dark:bg-zinc-900 p-2.5 rounded-xl border border-rose-100 dark:border-rose-900/40 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                        <div>
                                                            <div class="font-semibold text-rose-900 dark:text-rose-200">{{ $blocker->message }}</div>
                                                            <div class="text-[11px] text-zinc-600 dark:text-zinc-400 mt-0.5">{{ $blocker->howToFix }}</div>
                                                        </div>
                                                        @if ($blocker->actionRoute)
                                                            <a href="{{ route($blocker->actionRoute) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline shrink-0">
                                                                <span>{{ $blocker->actionLabel ?? 'Fix Setup' }}</span>
                                                                <flux:icon icon="arrow-top-right-on-square" class="size-3" />
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <div class="p-3 rounded-2xl bg-emerald-50/80 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 flex items-center gap-2 text-emerald-800 dark:text-emerald-300 text-xs font-bold">
                                            <flux:icon icon="check-circle" class="size-4 text-emerald-600 shrink-0" />
                                            <span>Ready to Generate: All classes, subjects, and periods verified.</span>
                                        </div>
                                    @endif

                                    @if (!empty($this->preflightReport->warnings()))
                                        <div class="p-3 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 space-y-1.5">
                                            <div class="flex items-center gap-1.5 text-amber-800 dark:text-amber-300 font-bold text-[11px]">
                                                <flux:icon icon="information-circle" class="size-3.5 text-amber-600 shrink-0" />
                                                <span>{{ count($this->preflightReport->warnings()) }} Advisory Notice(s)</span>
                                            </div>
                                            <div class="space-y-1 max-h-32 overflow-y-auto pr-1">
                                                @foreach ($this->preflightReport->warnings() as $warn)
                                                    <div class="text-[11px] text-amber-900/90 dark:text-amber-200/90">
                                                        &bull; {{ $warn->message }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Wizard Footer Controls --}}
                    <div class="flex items-center justify-between pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        @if ($wizardStep > 1)
                            <flux:button type="button" wire:click="prevWizardStep" variant="subtle" size="sm">
                                &larr; Back
                            </flux:button>
                        @else
                            <flux:button type="button" wire:click="closeGeneratorModal" variant="subtle" size="sm">
                                Cancel
                            </flux:button>
                        @endif

                        <div class="flex items-center gap-2">
                            @if ($wizardStep < 3)
                                <flux:button type="button" wire:click="nextWizardStep" variant="primary" size="sm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold">
                                    Continue to Step {{ $wizardStep + 1 }} &rarr;
                                </flux:button>
                            @else
                                <flux:button type="button" wire:click="runAutomaticGenerator" variant="primary" size="sm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold disabled:opacity-50 disabled:cursor-not-allowed" wire:loading.attr="disabled" :disabled="(bool) ($this->preflightReport?->hasBlockers())">
                                    <span wire:loading.remove wire:target="runAutomaticGenerator">⚡ Generate Tailored Timetable</span>
                                    <span wire:loading wire:target="runAutomaticGenerator">Calculating Solvable Schedule...</span>
                                </flux:button>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        @endif

        {{-- Publish Confirmation Modal --}}
        @if ($showPublishConfirmModal && $activeDraft)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-2xl w-full max-w-md overflow-hidden p-6 space-y-4 animate-in fade-in zoom-in duration-150">
                    <div class="p-2.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 w-fit">
                        <flux:icon icon="arrow-up-tray" class="size-6" />
                    </div>
                    
                    <div>
                        <flux:heading size="lg" class="font-bold">Publish to Live Master Timetable?</flux:heading>
                        <flux:text class="text-xs text-zinc-500 mt-1">
                            This will activate <strong class="text-zinc-900 dark:text-white">{{ $activeDraft->name }}</strong> as the official school timetable.
                        </flux:text>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 text-xs text-indigo-900 dark:text-indigo-200">
                        <p class="font-bold flex items-center gap-1.5">
                            <flux:icon icon="shield-check" class="size-4 text-emerald-600" />
                            Student Attendance Records Protected
                        </p>
                        <p class="text-[11px] text-zinc-600 dark:text-zinc-400 mt-1">
                            Any existing timetable slots with recorded student attendance logs are updated in-place to ensure zero attendance data loss.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button type="button" wire:click="$set('showPublishConfirmModal', false)" variant="subtle" size="sm">Cancel</flux:button>
                        <flux:button type="button" wire:click="publishActiveDraft" variant="primary" size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="publishActiveDraft">Confirm &amp; Publish</span>
                            <span wire:loading wire:target="publishActiveDraft">Publishing...</span>
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Clear, Custom Delete Confirmation Modal --}}
        @if ($showDeleteConfirmModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-2xl w-full max-w-md overflow-hidden p-6 space-y-4 animate-in fade-in zoom-in duration-150">
                    <div class="p-2.5 rounded-2xl bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 w-fit">
                        <flux:icon icon="exclamation-triangle" class="size-6" />
                    </div>
                    
                    <div>
                        <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">
                            {{ $deleteTargetTitle }}
                        </flux:heading>
                        <flux:text class="text-xs text-zinc-500 mt-1">
                            {{ $deleteTargetDetails }}
                        </flux:text>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-100 dark:border-rose-900/40 text-xs text-rose-900 dark:text-rose-200 flex items-center gap-2">
                        <flux:icon icon="shield-exclamation" class="size-4 text-rose-600 shrink-0" />
                        <span>Please confirm you wish to perform this action. This cannot be undone.</span>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button type="button" wire:click="$set('showDeleteConfirmModal', false)" variant="subtle" size="sm">
                            Cancel
                        </flux:button>
                        <flux:button type="button" wire:click="executeDelete" variant="danger" size="sm" class="bg-rose-600 hover:bg-rose-700 text-white font-bold" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="executeDelete">Yes, Delete</span>
                            <span wire:loading wire:target="executeDelete">Deleting...</span>
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Interactive Edit Mode Prompt Modal --}}
        @if ($showEditModePromptModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-2xl w-full max-w-md overflow-hidden p-6 space-y-4 animate-in fade-in zoom-in duration-150">
                    <div class="p-2.5 rounded-2xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 w-fit">
                        <flux:icon icon="pencil-square" class="size-6" />
                    </div>
                    
                    <div>
                        <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">
                            Enable Timetable Edit Mode?
                        </flux:heading>
                        <flux:text class="text-xs text-zinc-500 mt-1">
                            Would you like to switch to Edit Mode? In Edit Mode, you can click directly on period times to adjust bell hours, modify rest break durations and colors, assign or change subjects, and rearrange slots.
                        </flux:text>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 text-xs text-indigo-900 dark:text-indigo-200 flex items-center gap-2">
                        <flux:icon icon="sparkles" class="size-4 text-indigo-600 shrink-0" />
                        <span>You can adjust any period hour or rest break with one click once in Edit Mode.</span>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button type="button" wire:click="$set('showEditModePromptModal', false)" variant="subtle" size="sm">
                            Cancel
                        </flux:button>
                        <flux:button type="button" wire:click="confirmEnterEditMode" variant="primary" size="sm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold">
                            Yes, Enable Edit Mode
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Edit Period Time Modal --}}
        @if ($showEditPeriodModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-2xl w-full max-w-md overflow-hidden p-6 space-y-4 animate-in fade-in zoom-in duration-150">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600">
                                <flux:icon icon="clock" class="size-5" />
                            </div>
                            <flux:heading size="lg" class="font-bold">Edit Period Hours</flux:heading>
                        </div>
                        <button type="button" wire:click="$set('showEditPeriodModal', false)" class="text-zinc-400 hover:text-zinc-600">
                            <flux:icon icon="x-mark" class="size-5" />
                        </button>
                    </div>

                    @if ($errors->any())
                        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 dark:bg-rose-950/50 dark:border-rose-800/60 p-3.5 flex items-start gap-3 shadow-xs animate-in fade-in duration-150">
                            <flux:icon icon="exclamation-circle" class="size-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                            <div class="text-xs text-rose-800 dark:text-rose-200 space-y-1">
                                <p class="font-bold">Cannot save period hours:</p>
                                <ul class="list-disc list-inside space-y-0.5 font-medium">
                                    @foreach ($errors->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-3">
                        <flux:field>
                            <flux:label>Period Label</flux:label>
                            <flux:input wire:model="editPeriodName" placeholder="e.g. Period 1" class="{{ $errors->has('editPeriodName') ? 'border-rose-500 ring-rose-200' : '' }}" />
                            @error('editPeriodName')
                                <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </flux:field>

                        <div class="grid grid-cols-2 gap-3">
                            <flux:field>
                                <flux:label>Start Time</flux:label>
                                <flux:input type="time" wire:model="editPeriodStart" class="{{ $errors->has('editPeriodStart') ? 'border-rose-500 ring-rose-200' : '' }}" />
                                @error('editPeriodStart')
                                    <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                @enderror
                            </flux:field>
                            <flux:field>
                                <flux:label>End Time</flux:label>
                                <flux:input type="time" wire:model="editPeriodEnd" class="{{ $errors->has('editPeriodEnd') ? 'border-rose-500 ring-rose-200' : '' }}" />
                                @error('editPeriodEnd')
                                    <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                @enderror
                            </flux:field>
                        </div>

                        <label class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400 pt-1 cursor-pointer">
                            <input type="checkbox" wire:model="cascadeLessonTimes" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span>Update all scheduled lesson slots matching {{ $editPeriodOriginalTime }} to the new hours</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button type="button" wire:click="$set('showEditPeriodModal', false)" variant="subtle" size="sm">Cancel</flux:button>
                        <flux:button type="button" wire:click="savePeriodTime" variant="primary" size="sm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold">
                            Save Period Time
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Edit / Add Break (Rest) Modal --}}
        @if ($showEditBreakModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-2xl w-full max-w-md overflow-hidden p-6 space-y-4 animate-in fade-in zoom-in duration-150">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950 text-amber-600">
                                <flux:icon icon="sparkles" class="size-5" />
                            </div>
                            <flux:heading size="lg" class="font-bold">
                                {{ $isNewBreak ? 'Add Rest / Break Period' : 'Edit Rest Break' }}
                            </flux:heading>
                        </div>
                        <button type="button" wire:click="$set('showEditBreakModal', false)" class="text-zinc-400 hover:text-zinc-600">
                            <flux:icon icon="x-mark" class="size-5" />
                        </button>
                    </div>

                    @if ($errors->any())
                        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 dark:bg-rose-950/50 dark:border-rose-800/60 p-3.5 flex items-start gap-3 shadow-xs animate-in fade-in duration-150">
                            <flux:icon icon="exclamation-circle" class="size-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                            <div class="text-xs text-rose-800 dark:text-rose-200 space-y-1">
                                <p class="font-bold">Cannot save rest break:</p>
                                <ul class="list-disc list-inside space-y-0.5 font-medium">
                                    @foreach ($errors->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-3">
                        <flux:field>
                            <flux:label>Break Name</flux:label>
                            <flux:input wire:model="editBreakName" placeholder="e.g. Short Break, Tea Break, Lunch" class="{{ $errors->has('editBreakName') ? 'border-rose-500 ring-rose-200' : '' }}" />
                            @error('editBreakName')
                                <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </flux:field>

                        @if ($isNewBreak)
                            <flux:field>
                                <flux:label>Place After Period</flux:label>
                                <flux:select wire:model="newBreakAfterPeriod">
                                    @for ($p = 1; $p <= $dailyPeriods; $p++)
                                        <option value="{{ $p }}">After Period {{ $p }}</option>
                                    @endfor
                                </flux:select>
                            </flux:field>
                        @endif

                        <div class="grid grid-cols-2 gap-3">
                            <flux:field>
                                <flux:label>Duration (minutes)</flux:label>
                                <flux:input type="number" wire:model="editBreakDuration" min="5" max="180" class="{{ $errors->has('editBreakDuration') ? 'border-rose-500 ring-rose-200' : '' }}" />
                                @error('editBreakDuration')
                                    <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                @enderror
                            </flux:field>
                            <flux:field>
                                <flux:label>Badge Color Theme</flux:label>
                                <flux:select wire:model="editBreakColor">
                                    <option value="amber">Warm Amber</option>
                                    <option value="emerald">Fresh Emerald</option>
                                    <option value="sky">Cool Sky Blue</option>
                                    <option value="indigo">Calm Indigo</option>
                                    <option value="rose">Soft Rose</option>
                                </flux:select>
                                @error('editBreakColor')
                                    <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                @enderror
                            </flux:field>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        @if (! $isNewBreak)
                            <flux:button type="button" wire:click="deleteBreak" variant="danger" size="sm" class="text-xs">
                                Remove Break
                            </flux:button>
                        @else
                            <div></div>
                        @endif

                        <div class="flex items-center gap-2">
                            <flux:button type="button" wire:click="$set('showEditBreakModal', false)" variant="subtle" size="sm">Cancel</flux:button>
                            <flux:button type="button" wire:click="saveBreak" variant="primary" size="sm" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold">
                                Save Break
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Quick Lesson Edit / Add Modal --}}
        @if ($showLessonModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4">
                <div class="bg-white dark:bg-zinc-900 rounded-3xl border border-zinc-200 dark:border-zinc-800 shadow-2xl w-full max-w-md overflow-hidden p-6 space-y-4 animate-in fade-in zoom-in duration-150">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="p-2.5 rounded-2xl bg-blue-50 dark:bg-blue-950 text-[#0047FF]">
                                <flux:icon icon="book-open" class="size-5" />
                            </div>
                            <div>
                                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">
                                    {{ $lessonEntryId ? 'Edit Lesson Slot' : 'Assign Lesson Slot' }}
                                </flux:heading>
                                <flux:text class="text-xs text-zinc-500">
                                    <strong class="text-zinc-800 dark:text-zinc-200 font-bold">{{ $selectedClass?->name ?? 'Class' }}</strong> &bull; {{ $lessonDay }} &bull; {{ $lessonPeriod }}
                                </flux:text>
                            </div>
                        </div>
                        <button type="button" wire:click="$set('showLessonModal', false)" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition">
                            <flux:icon icon="x-mark" class="size-5" />
                        </button>
                    </div>

                    {{-- Top Conflict & Validation Alert Banner --}}
                    @if ($errors->any())
                        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 dark:bg-rose-950/50 dark:border-rose-800/60 p-3.5 flex items-start gap-3 shadow-xs animate-in fade-in duration-150">
                            <flux:icon icon="exclamation-circle" class="size-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
                            <div class="text-xs text-rose-800 dark:text-rose-200 space-y-1">
                                <p class="font-bold">Cannot save schedule slot:</p>
                                <ul class="list-disc list-inside space-y-0.5 font-medium">
                                    @foreach ($errors->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-3.5">
                        @if (! $lessonEntryId)
                            <div class="grid grid-cols-2 gap-2.5">
                                <flux:field>
                                    <flux:label class="text-xs font-semibold">Day of Week</flux:label>
                                    <flux:select wire:model.live="lessonDay">
                                        @foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $d)
                                            <option value="{{ $d }}">{{ $d }}</option>
                                        @endforeach
                                    </flux:select>
                                    @error('lessonDay')
                                        <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                    @enderror
                                </flux:field>

                                <flux:field>
                                    <flux:label class="text-xs font-semibold">Period / Time</flux:label>
                                    <flux:select wire:model.live="lessonPeriod">
                                        @foreach ($this->teachingPeriods as $tp)
                                            <option value="{{ $tp['time'] }}">{{ $tp['label'] }}</option>
                                        @endforeach
                                    </flux:select>
                                    @error('lessonPeriod')
                                        <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                                    @enderror
                                </flux:field>
                            </div>
                        @endif

                        <flux:field>
                            <flux:label class="text-xs font-semibold">Subject</flux:label>
                            <flux:select wire:model.live="lessonSubject">
                                <option value="">Select subject...</option>
                                @foreach ($this->subjects as $subj)
                                    <option value="{{ $subj->name }}">{{ $subj->name }}</option>
                                @endforeach
                            </flux:select>
                            @error('lessonSubject')
                                <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label class="text-xs font-semibold">Teacher (Auto-assigned or Select)</flux:label>
                            <flux:select wire:model="lessonTeacherId" class="{{ $errors->has('lessonTeacherId') ? 'border-rose-500 focus:border-rose-500 ring-rose-200' : '' }}">
                                <option value="">Select teacher (optional)...</option>
                                @foreach ($this->teachers as $tch)
                                    <option value="{{ $tch->id }}">{{ $tch->name }}</option>
                                @endforeach
                            </flux:select>
                            @error('lessonTeacherId')
                                <div class="mt-1.5 p-2 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800/60 flex items-start gap-1.5 text-xs text-rose-700 dark:text-rose-300 font-semibold">
                                    <flux:icon icon="exclamation-triangle" class="size-4 text-rose-600 shrink-0 mt-0.5" />
                                    <span>{{ $message }}</span>
                                </div>
                            @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label class="text-xs font-semibold">Room / Location (Optional)</flux:label>
                            <flux:input wire:model="lessonRoom" placeholder="e.g. Science Lab, Room 102" />
                            @error('lessonRoom')
                                <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </flux:field>

                        @php
                            $canDouble = $this->activeStructure?->canFormDoublePeriod($lessonPeriod) ?? false;
                            $nextPeriodObj = $canDouble ? $this->activeStructure?->getNextConsecutiveTeachingPeriod($lessonPeriod) : null;
                        @endphp
                        @if ($canDouble && $nextPeriodObj)
                            <div class="p-3 rounded-xl bg-violet-50/70 dark:bg-violet-950/30 border border-violet-200/80 dark:border-violet-900/50 space-y-1">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="checkbox" wire:model.live="lessonIsDouble" class="rounded border-violet-300 text-violet-600 focus:ring-violet-500 size-4">
                                    <span class="text-xs font-bold text-violet-900 dark:text-violet-200">
                                        Double Lesson (2 Consecutive Periods)
                                    </span>
                                </label>
                                <p class="text-[10px] text-violet-600 dark:text-violet-400 pl-6">
                                    Combines this period with <strong class="font-mono">{{ $nextPeriodObj['start_time'] }}-{{ $nextPeriodObj['end_time'] }}</strong> ({{ $nextPeriodObj['name'] ?? 'Next Period' }}).
                                </p>
                            </div>
                        @endif
                        @error('lessonIsDouble')
                            <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button type="button" wire:click="$set('showLessonModal', false)" variant="subtle" size="sm">Cancel</flux:button>
                        <flux:button type="button" wire:click="saveLesson" variant="primary" size="sm" class="bg-[#0047FF] hover:bg-[#0037cc] text-white font-bold">
                            Save Lesson
                        </flux:button>
                    </div>
                </div>
            </div>
        @endif

    </div>
