<?php

namespace Modules\Timetable\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Timetable\Models\TimetableDraft;
use Modules\Timetable\Models\TimetableDraftEntry;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Models\TimetableStructure;
use Modules\Timetable\Support\PreflightFailedException;
use Modules\Timetable\Support\UnplacedReason;

/**
 * Builds a draft timetable.
 *
 * It first runs the pre-flight check and refuses to generate if the school's
 * setup is incomplete. Placement is a constraint-driven greedy search with a
 * bounded repair pass: the hardest lessons go first, every slot is checked
 * against hard constraints (no clashes, daily and consecutive limits), and a
 * lesson that does not fit may displace one single lesson that can itself
 * move elsewhere. Everything is computed in memory and written to the
 * database in one transaction, so a failed run leaves nothing behind.
 *
 * Limits come from config/timetable.php and the school's own allocations;
 * nothing about the bell schedule (period count, break positions) is assumed.
 */
class TimetableGeneratorService
{
    protected int $institutionId;

    protected TimetableStructure $structure;

    /** @var array<int, string> */
    protected array $days = [];

    /** @var array<int, array<string, mixed>> */
    protected array $teachingPeriods = [];

    /** @var array<int, int> period_number => position in the day */
    protected array $periodPos = [];

    /** @var array<int, array<string, mixed>> */
    protected array $slotList = [];

    /** @var array<string, array<string, mixed>> "Day|period" => slot */
    protected array $slotByKey = [];

    /** @var array<int, array<string, array<int, bool>>> teacher grid for this run */
    protected array $teacherGrid = [];

    /** @var array<int, array<string, array<int, bool>>> teacher busy from published lessons elsewhere */
    protected array $externalBusy = [];

    /** @var array<int, array<string, array<int, array<string, mixed>>>> */
    protected array $classGrid = [];

    /** @var array<int, array<string, array<int, int>>> */
    protected array $classDaySubjects = [];

    /** @var array<int, array<string, int>> */
    protected array $teacherDayCount = [];

    /** @var array<string, bool> */
    protected array $adjacentCache = [];

    protected bool $preferMorning = true;

    protected ?string $seed = null;

    /** @var array<string, int> reason code => count, filled by the last failed placement */
    protected array $lastTally = [];

    public function __construct(
        protected TimetableDemandResolver $resolver,
        protected TimetablePreflightService $preflight,
    ) {}

    /**
     * Generate a draft timetable.
     *
     * Options: structure (model, may be unsaved) | structure_id, make_default,
     * scope_type, scope_id, draft_name, academic_year, term,
     * prefer_morning_high_cognitive, seed, created_by, locked_entries.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws PreflightFailedException when the school's setup is incomplete
     */
    public function generate(int $institutionId, array $options = []): TimetableDraft
    {
        $this->institutionId = $institutionId;
        $this->preferMorning = (bool) ($options['prefer_morning_high_cognitive'] ?? true);
        $this->seed = isset($options['seed']) ? (string) $options['seed'] : null;

        $this->structure = $this->resolveStructure($options);
        $this->days = array_values($this->structure->days_of_week ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']);
        $this->teachingPeriods = $this->structure->getTeachingPeriods();

        $scopeType = $options['scope_type'] ?? 'whole_school';
        $scopeId = isset($options['scope_id']) ? (int) $options['scope_id'] : null;

        // 1. Refuse to generate from incomplete setup. Nothing has been written yet.
        $report = $this->preflight->check($institutionId, $this->structure, $scopeType, $scopeId);
        if ($report->hasBlockers()) {
            throw new PreflightFailedException($report);
        }

        $classes = $this->resolver->targetClasses($institutionId, $scopeType, $scopeId);
        $this->buildSlots();
        $this->resetState();
        $this->loadExternalBusy($classes->pluck('id')->all());

        $resolved = $this->resolver->resolve($institutionId, $classes, count($this->days));
        $demandsByClass = collect($resolved['demands'])
            ->filter(fn ($d) => $d['periods_per_week'] > 0)
            ->groupBy('class_id');

        // 2. Locked lessons are fixed first and reduce what is left to place.
        [$lockedCounts, $lockedConflicts] = $this->applyLockedEntries($options['locked_entries'] ?? [], $classes->pluck('id')->all());

        // 3. Place lessons class by class, hardest first.
        $unplaced = [];
        $doubleFallbacks = 0;

        foreach ($classes as $class) {
            $units = $this->buildUnits(
                $demandsByClass->get($class->id, collect())->all(),
                $lockedCounts[$class->id] ?? []
            );

            foreach ($units as $unit) {
                if ($unit['kind'] === 'double') {
                    if ($this->placeDouble($unit)) {
                        continue;
                    }
                    $doubleFallbacks++;
                    $unit['kind'] = 'single';
                    $twin = $unit;
                    foreach ([$unit, $twin] as $single) {
                        if (! $this->placeSingle($single)) {
                            $unplaced[] = ['unit' => $single, 'tally' => $this->lastTally];
                        }
                    }

                    continue;
                }

                if (! $this->placeSingle($unit)) {
                    $unplaced[] = ['unit' => $unit, 'tally' => $this->lastTally];
                }
            }
        }

        // 4. Give lessons that did not fit a bounded chance to displace a movable lesson.
        $unplaced = $this->repair($unplaced);

        // 5. Summarise.
        $totalRequired = (int) $demandsByClass->flatten(1)->sum('periods_per_week');
        $totalPlaced = max(0, $totalRequired - count($unplaced));
        $score = $totalRequired > 0 ? round(($totalPlaced / $totalRequired) * 100, 2) : 0.0;

        $summary = [
            'total_required' => $totalRequired,
            'total_placed' => $totalPlaced,
            'unplaced_count' => count($unplaced),
            'unplaced_details' => $this->describeUnplaced($unplaced),
            'locked_conflicts' => $lockedConflicts,
            'double_fallbacks' => $doubleFallbacks,
            'classes_processed' => $classes->count(),
            'total_periods_per_week' => count($this->slotList),
            'preflight_warnings' => array_map(fn ($i) => $i->toArray(), array_merge($report->warnings(), $report->info())),
        ];

        // 6. Write everything or nothing.
        return DB::transaction(function () use ($options, $scopeType, $scopeId, $score, $summary) {
            $this->persistStructure($options);

            $draft = TimetableDraft::create([
                'institution_id' => $this->institutionId,
                'name' => $options['draft_name'] ?? ('Draft Timetable - '.now()->format('M Y')),
                'academic_year' => $options['academic_year'] ?? (string) now()->year,
                'term' => $options['term'] ?? 'Term 1',
                'structure_id' => $this->structure->id,
                'status' => 'draft',
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'generator_settings' => array_merge(Arr::except($options, ['structure']), ['structure_id' => $this->structure->id]),
                'generation_score' => $score,
                'conflict_summary' => $summary,
                'created_by' => $options['created_by'] ?? auth()->id(),
            ]);

            foreach ($this->collectEntries() as $entry) {
                TimetableDraftEntry::create([
                    'draft_id' => $draft->id,
                    'institution_id' => $this->institutionId,
                    'class_id' => $entry['class_id'],
                    'subject_id' => $entry['subject_id'],
                    'teacher_id' => $entry['teacher_id'],
                    'day_of_week' => $entry['day'],
                    'start_time' => $entry['start_time'],
                    'end_time' => $entry['end_time'],
                    'period_number' => $entry['period_number'],
                    'room' => null,
                    'is_locked' => $entry['locked'],
                    'notes' => $entry['notes'],
                ]);
            }

            return $draft->fresh(['draftEntries', 'structure', 'targetClass']);
        });
    }

    // --- setup ------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $options
     */
    protected function resolveStructure(array $options): TimetableStructure
    {
        $given = $options['structure'] ?? null;
        if ($given instanceof TimetableStructure) {
            if ($given->exists) {
                abort_unless((int) $given->institution_id === $this->institutionId, 403);
            } else {
                $given->institution_id = $this->institutionId;
            }

            return $given;
        }

        if (! empty($options['structure_id'])) {
            return TimetableStructure::where('institution_id', $this->institutionId)->findOrFail($options['structure_id']);
        }

        return TimetableStructure::getOrCreateDefault($this->institutionId);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function persistStructure(array $options): void
    {
        if ($this->structure->exists) {
            return;
        }

        if (! empty($options['make_default'])) {
            TimetableStructure::where('institution_id', $this->institutionId)->update(['is_default' => false]);
            $this->structure->is_default = true;
        }

        $this->structure->save();
    }

    protected function buildSlots(): void
    {
        $this->slotList = [];
        $this->slotByKey = [];
        $this->periodPos = [];

        foreach ($this->teachingPeriods as $index => $tp) {
            $this->periodPos[(int) $tp['period_number']] = $index;
        }

        foreach ($this->days as $day) {
            foreach ($this->teachingPeriods as $tp) {
                $slot = [
                    'day' => $day,
                    'period_number' => (int) $tp['period_number'],
                    'start_time' => $tp['start_time'],
                    'end_time' => $tp['end_time'],
                ];
                $this->slotList[] = $slot;
                $this->slotByKey[$day.'|'.$slot['period_number']] = $slot;
            }
        }
    }

    protected function resetState(): void
    {
        $this->teacherGrid = [];
        $this->externalBusy = [];
        $this->classGrid = [];
        $this->classDaySubjects = [];
        $this->teacherDayCount = [];
        $this->adjacentCache = [];
        $this->lastTally = [];
    }

    /**
     * Teachers already teaching published lessons in classes we are NOT
     * regenerating are busy at those times, whatever we place.
     *
     * @param  array<int, int>  $targetClassIds
     */
    protected function loadExternalBusy(array $targetClassIds): void
    {
        $entries = TimetableEntry::where('institution_id', $this->institutionId)
            ->whereNotNull('teacher_id')
            ->whereNotIn('class_id', $targetClassIds)
            ->get();

        foreach ($entries as $entry) {
            $start = $entry->start_time?->format('H:i');
            $end = $entry->end_time?->format('H:i');
            if (! $start || ! $end) {
                continue;
            }

            foreach ($this->slotList as $slot) {
                if ($slot['day'] === $entry->day_of_week && $start < $slot['end_time'] && $end > $slot['start_time']) {
                    $teacherId = (int) $entry->teacher_id;
                    if (! isset($this->externalBusy[$teacherId][$slot['day']][$slot['period_number']])) {
                        $this->externalBusy[$teacherId][$slot['day']][$slot['period_number']] = true;
                        $this->teacherDayCount[$teacherId][$slot['day']] = ($this->teacherDayCount[$teacherId][$slot['day']] ?? 0) + 1;
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $locked
     * @param  array<int, int>  $targetClassIds
     * @return array{0: array<int, array<int, int>>, 1: array<int, array<string, mixed>>}
     */
    protected function applyLockedEntries(array $locked, array $targetClassIds): array
    {
        $counts = [];
        $conflicts = [];

        foreach ($locked as $item) {
            $day = $item['day_of_week'] ?? $item['day'] ?? null;
            $period = (int) ($item['period_number'] ?? 0);
            $classId = (int) ($item['class_id'] ?? 0);
            $subjectId = (int) ($item['subject_id'] ?? 0);
            $teacherId = isset($item['teacher_id']) ? (int) $item['teacher_id'] : null;

            $slot = $this->slotByKey[$day.'|'.$period] ?? null;
            $problem = null;

            if (! in_array($classId, $targetClassIds, true)) {
                $problem = 'The class is not part of this timetable run.';
            } elseif (! $slot) {
                $problem = 'The day or period does not exist in the bell schedule.';
            } elseif (isset($this->classGrid[$classId][$day][$period])) {
                $problem = 'The class already has a locked lesson in that slot.';
            } elseif ($teacherId && (isset($this->teacherGrid[$teacherId][$day][$period]) || isset($this->externalBusy[$teacherId][$day][$period]))) {
                $problem = 'The teacher is already teaching somewhere else in that slot.';
            }

            if ($problem !== null) {
                $conflicts[] = [
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'day_of_week' => $day,
                    'period_number' => $period,
                    'reason_code' => UnplacedReason::LockedConflict->value,
                    'reason' => $problem,
                ];

                continue;
            }

            $this->assign([
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'day' => $day,
                'period_number' => $period,
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'notes' => $item['notes'] ?? null,
                'locked' => true,
                'double' => false,
                'unit' => null,
            ]);

            $counts[$classId][$subjectId] = ($counts[$classId][$subjectId] ?? 0) + 1;
        }

        return [$counts, $conflicts];
    }

    // --- units of work ----------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $demands
     * @param  array<int, int>  $lockedBySubject
     * @return array<int, array<string, mixed>>
     */
    protected function buildUnits(array $demands, array $lockedBySubject): array
    {
        $hasPair = $this->structureHasAdjacentPair();
        $blocksPerSubject = max(0, (int) config('timetable.double_blocks_per_subject', 1));
        $units = [];

        foreach ($demands as $d) {
            $need = max(0, $d['periods_per_week'] - ($lockedBySubject[$d['subject_id']] ?? 0));
            $canDouble = $d['allow_double'] && $hasPair && ($d['max_per_day'] === null || $d['max_per_day'] >= 2);
            $blocks = $canDouble ? min($blocksPerSubject, intdiv($need, 2)) : 0;
            $singles = $need - ($blocks * 2);

            $base = [
                'class_id' => $d['class_id'],
                'class_name' => $d['class_name'],
                'subject_id' => $d['subject_id'],
                'subject_name' => $d['subject_name'],
                'teacher_ids' => $d['teacher_ids'],
                'cognitive_weight' => $d['cognitive_weight'],
                'weight_score' => match ($d['cognitive_weight']) {
                    'high' => 3,
                    'low' => 1,
                    default => 2,
                },
                'max_per_day' => $d['max_per_day'],
                'total_periods' => $d['periods_per_week'],
            ];

            for ($i = 0; $i < $blocks; $i++) {
                $units[] = $base + ['kind' => 'double'];
            }
            for ($i = 0; $i < $singles; $i++) {
                $units[] = $base + ['kind' => 'single'];
            }
        }

        // Most constrained first: doubles, then heavier subjects, then those
        // with a named teacher, then bigger quotas, with a stable final order.
        usort($units, function ($a, $b) {
            return [$a['kind'] === 'double' ? 0 : 1, -$a['weight_score'], $a['teacher_ids'] === [] ? 1 : 0, -$a['total_periods'], $a['subject_id']]
                <=> [$b['kind'] === 'double' ? 0 : 1, -$b['weight_score'], $b['teacher_ids'] === [] ? 1 : 0, -$b['total_periods'], $b['subject_id']];
        });

        return $units;
    }

    protected function structureHasAdjacentPair(): bool
    {
        $count = count($this->teachingPeriods);
        for ($i = 0; $i < $count - 1; $i++) {
            $p1 = (int) $this->teachingPeriods[$i]['period_number'];
            $p2 = (int) $this->teachingPeriods[$i + 1]['period_number'];
            if ($this->adjacent($p1, $p2)) {
                return true;
            }
        }

        return false;
    }

    protected function adjacent(int $a, int $b): bool
    {
        $key = min($a, $b).'-'.max($a, $b);

        return $this->adjacentCache[$key] ??= $this->structure->areConsecutiveTeachingPeriods($a, $b);
    }

    // --- placement --------------------------------------------------------

    /**
     * @param  array<string, mixed>  $unit
     */
    protected function placeSingle(array $unit, ?array $excludeSlot = null): ?array
    {
        $tally = [];
        $classId = $unit['class_id'];

        foreach ($this->rankCandidateSlots($unit) as $slot) {
            $day = $slot['day'];
            $period = $slot['period_number'];

            if ($excludeSlot && $excludeSlot[0] === $day && $excludeSlot[1] === $period) {
                continue;
            }

            if (isset($this->classGrid[$classId][$day][$period])) {
                $this->tally($tally, UnplacedReason::ClassSlotsFull);

                continue;
            }

            if ($this->subjectAtDailyLimit($unit, $day, 1)) {
                $this->tally($tally, UnplacedReason::SubjectDailyLimit);

                continue;
            }

            [$teacherId, $reason] = $this->pickTeacher($unit['teacher_ids'], $day, [$period]);
            if ($reason !== null) {
                $this->tally($tally, $reason);

                continue;
            }

            $entry = $this->makeEntry($unit, $slot, $teacherId, null, false);
            $this->assign($entry);
            $this->lastTally = [];

            return $entry;
        }

        $this->lastTally = $tally;

        return null;
    }

    /**
     * Place two consecutive periods atomically. Never spans a break.
     *
     * @param  array<string, mixed>  $unit
     */
    protected function placeDouble(array $unit): bool
    {
        $classId = $unit['class_id'];

        foreach ($this->rankCandidateSlots($unit) as $slot1) {
            $day = $slot1['day'];
            $p1 = $slot1['period_number'];
            $pos = $this->periodPos[$p1] ?? null;
            $p2 = ($pos !== null && isset($this->teachingPeriods[$pos + 1]))
                ? (int) $this->teachingPeriods[$pos + 1]['period_number']
                : ($p1 + 1);

            if (! $this->adjacent($p1, $p2)) {
                continue;
            }

            $slot2 = $this->slotByKey[$day.'|'.$p2] ?? null;
            if (! $slot2) {
                continue;
            }

            if (isset($this->classGrid[$classId][$day][$p1]) || isset($this->classGrid[$classId][$day][$p2])) {
                continue;
            }

            if ($this->subjectAtDailyLimit($unit, $day, 2)) {
                continue;
            }

            [$teacherId, $reason] = $this->pickTeacher($unit['teacher_ids'], $day, [$p1, $p2]);
            if ($reason !== null) {
                continue;
            }

            $this->assign($this->makeEntry($unit, $slot1, $teacherId, '[Double Lesson Part 1/2]', true));
            $this->assign($this->makeEntry($unit, $slot2, $teacherId, '[Double Lesson Part 2/2]', true));

            return true;
        }

        return false;
    }

    /**
     * @param  array<int, int>  $teacherIds
     * @param  array<int, int>  $periods
     * @return array{0: ?int, 1: ?UnplacedReason} the teacher to use, or the reason none can
     */
    protected function pickTeacher(array $teacherIds, string $day, array $periods): array
    {
        if ($teacherIds === []) {
            return [null, null]; // "teacher TBD": nothing to clash with
        }

        $firstReason = null;
        foreach ($teacherIds as $teacherId) {
            $reason = $this->teacherBlocker($teacherId, $day, $periods);
            if ($reason === null) {
                return [$teacherId, null];
            }
            $firstReason ??= $reason;
        }

        return [null, $firstReason];
    }

    /**
     * @param  array<int, int>  $periods
     */
    protected function teacherBlocker(int $teacherId, string $day, array $periods): ?UnplacedReason
    {
        foreach ($periods as $p) {
            if (isset($this->teacherGrid[$teacherId][$day][$p]) || isset($this->externalBusy[$teacherId][$day][$p])) {
                return UnplacedReason::TeacherBusy;
            }
        }

        $dailyCap = config('timetable.max_daily_lessons');
        if ($dailyCap && (($this->teacherDayCount[$teacherId][$day] ?? 0) + count($periods)) > (int) $dailyCap) {
            return UnplacedReason::TeacherDailyLimit;
        }

        $maxRun = config('timetable.max_consecutive_lessons');
        if ($maxRun && $this->consecutiveRun($teacherId, $day, $periods) > (int) $maxRun) {
            return UnplacedReason::TeacherConsecutiveLimit;
        }

        return null;
    }

    /**
     * Length of the back-to-back run that would include the new periods.
     * A break between two periods ends the run.
     *
     * @param  array<int, int>  $newPeriods
     */
    protected function consecutiveRun(int $teacherId, string $day, array $newPeriods): int
    {
        $occupied = ($this->teacherGrid[$teacherId][$day] ?? []) + ($this->externalBusy[$teacherId][$day] ?? []);
        foreach ($newPeriods as $p) {
            $occupied[$p] = true;
        }

        $start = min($newPeriods);
        while (isset($occupied[$start - 1]) && $this->adjacent($start - 1, $start)) {
            $start--;
        }

        $end = max($newPeriods);
        while (isset($occupied[$end + 1]) && $this->adjacent($end, $end + 1)) {
            $end++;
        }

        return $end - $start + 1;
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    protected function subjectAtDailyLimit(array $unit, string $day, int $adding): bool
    {
        if ($unit['max_per_day'] === null) {
            return false;
        }

        return (($this->classDaySubjects[$unit['class_id']][$day][$unit['subject_id']] ?? 0) + $adding) > $unit['max_per_day'];
    }

    /**
     * @param  array<string, int>  $tally
     */
    protected function tally(array &$tally, UnplacedReason $reason): void
    {
        $tally[$reason->value] = ($tally[$reason->value] ?? 0) + 1;
    }

    // --- repair pass ------------------------------------------------------

    /**
     * For each lesson that did not fit, try taking over a slot held by a
     * single (not locked, not double) lesson of the same class, provided that
     * lesson can move to another valid slot. Bounded: one pass, one swap each.
     *
     * @param  array<int, array{unit: array<string, mixed>, tally: array<string, int>}>  $unplaced
     * @return array<int, array{unit: array<string, mixed>, tally: array<string, int>}>
     */
    protected function repair(array $unplaced): array
    {
        $remaining = [];

        foreach ($unplaced as $failure) {
            if (! $this->tryDisplace($failure['unit'])) {
                $remaining[] = $failure;
            }
        }

        return $remaining;
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    protected function tryDisplace(array $unit): bool
    {
        $classId = $unit['class_id'];

        foreach ($this->rankCandidateSlots($unit) as $slot) {
            $day = $slot['day'];
            $period = $slot['period_number'];
            $occupant = $this->classGrid[$classId][$day][$period] ?? null;

            if (! $occupant || $occupant['locked'] || $occupant['double'] || $occupant['unit'] === null) {
                continue;
            }

            $this->unassign($occupant);

            [$teacherId, $reason] = $this->pickTeacher($unit['teacher_ids'], $day, [$period]);
            if ($reason !== null || $this->subjectAtDailyLimit($unit, $day, 1)) {
                $this->assign($occupant);

                continue;
            }

            $moved = $this->placeSingle($occupant['unit'], [$day, $period]);
            if ($moved === null) {
                $this->assign($occupant);

                continue;
            }

            // The move may have changed the teacher's run lengths; re-verify.
            [$teacherId, $reason] = $this->pickTeacher($unit['teacher_ids'], $day, [$period]);
            if ($reason === null && ! $this->subjectAtDailyLimit($unit, $day, 1)) {
                $this->assign($this->makeEntry($unit, $slot, $teacherId, null, false));

                return true;
            }

            $this->unassign($moved);
            $this->assign($occupant);
        }

        return false;
    }

    // --- scoring ----------------------------------------------------------

    /**
     * Slots best-first for this lesson. Ties break deterministically; a seed
     * (option) varies the tie-break without making it random.
     *
     * @param  array<string, mixed>  $unit
     * @return array<int, array<string, mixed>>
     */
    protected function rankCandidateSlots(array $unit): array
    {
        $scored = [];
        foreach ($this->slotList as $index => $slot) {
            $tie = $this->seed === null
                ? $index
                : crc32($this->seed.'|'.$unit['class_id'].'|'.$unit['subject_id'].'|'.$slot['day'].'|'.$slot['period_number']);

            $scored[] = [$this->slotScore($slot, $unit), $tie, $index, $slot];
        }

        usort($scored, fn ($a, $b) => [$b[0], $a[1], $a[2]] <=> [$a[0], $b[1], $b[2]]);

        return array_map(fn ($row) => $row[3], $scored);
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  array<string, mixed>  $unit
     */
    protected function slotScore(array $slot, array $unit): int
    {
        $score = 50;
        $count = max(1, count($this->teachingPeriods));
        $position = $this->periodPos[$slot['period_number']] ?? 0;
        $morningCount = (int) ceil($count / 2);
        $lateCount = max(1, (int) floor($count / 4));
        $classId = $unit['class_id'];
        $day = $slot['day'];

        if ($unit['cognitive_weight'] === 'high' && $this->preferMorning) {
            if ($position < $morningCount) {
                $score += 35;
            } elseif ($position >= $count - $lateCount) {
                $score -= 25;
            }
        } elseif ($unit['cognitive_weight'] === 'low') {
            $score += $position >= $morningCount ? 30 : -10;
        }

        if (($this->classDaySubjects[$classId][$day][$unit['subject_id']] ?? 0) > 0) {
            $score -= 40;
        }

        // Balance teacher's workload evenly across days to avoid starving late-week days
        foreach ($unit['teacher_ids'] as $teacherId) {
            $score -= ($this->teacherDayCount[$teacherId][$day] ?? 0) * 8;
        }

        // Gently spread each class's week so no day is packed while another is empty.
        $score -= count($this->classGrid[$classId][$day] ?? []);

        return $score;
    }

    // --- grid bookkeeping -------------------------------------------------

    /**
     * @param  array<string, mixed>  $unit
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>
     */
    protected function makeEntry(array $unit, array $slot, ?int $teacherId, ?string $notes, bool $double): array
    {
        return [
            'class_id' => $unit['class_id'],
            'subject_id' => $unit['subject_id'],
            'teacher_id' => $teacherId,
            'day' => $slot['day'],
            'period_number' => $slot['period_number'],
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'notes' => $notes,
            'locked' => false,
            'double' => $double,
            'unit' => $unit,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function assign(array $entry): void
    {
        $classId = $entry['class_id'];
        $day = $entry['day'];
        $period = $entry['period_number'];

        $this->classGrid[$classId][$day][$period] = $entry;
        $this->classDaySubjects[$classId][$day][$entry['subject_id']] = ($this->classDaySubjects[$classId][$day][$entry['subject_id']] ?? 0) + 1;

        if ($entry['teacher_id']) {
            $this->teacherGrid[$entry['teacher_id']][$day][$period] = true;
            $this->teacherDayCount[$entry['teacher_id']][$day] = ($this->teacherDayCount[$entry['teacher_id']][$day] ?? 0) + 1;
        }
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function unassign(array $entry): void
    {
        $classId = $entry['class_id'];
        $day = $entry['day'];
        $period = $entry['period_number'];

        unset($this->classGrid[$classId][$day][$period]);
        $this->classDaySubjects[$classId][$day][$entry['subject_id']] = max(0, ($this->classDaySubjects[$classId][$day][$entry['subject_id']] ?? 1) - 1);

        if ($entry['teacher_id']) {
            unset($this->teacherGrid[$entry['teacher_id']][$day][$period]);
            $this->teacherDayCount[$entry['teacher_id']][$day] = max(0, ($this->teacherDayCount[$entry['teacher_id']][$day] ?? 1) - 1);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function collectEntries(): array
    {
        $entries = [];
        foreach ($this->classGrid as $days) {
            foreach ($days as $periods) {
                foreach ($periods as $entry) {
                    $entries[] = $entry;
                }
            }
        }

        $dayOrder = array_flip($this->days);
        usort($entries, fn ($a, $b) => [$a['class_id'], $dayOrder[$a['day']] ?? 0, $a['period_number']] <=> [$b['class_id'], $dayOrder[$b['day']] ?? 0, $b['period_number']]);

        return $entries;
    }

    // --- reporting --------------------------------------------------------

    /**
     * @param  array<int, array{unit: array<string, mixed>, tally: array<string, int>}>  $unplaced
     * @return array<int, array<string, mixed>>
     */
    protected function describeUnplaced(array $unplaced): array
    {
        $teacherIds = collect($unplaced)->pluck('unit.teacher_ids')->flatten()->unique()->all();
        $names = $teacherIds === [] ? collect() : User::whereIn('id', $teacherIds)->pluck('name', 'id');

        return array_map(function ($failure) use ($names) {
            $unit = $failure['unit'];
            $reason = $this->dominantReason($failure['tally']);
            $teacherNames = collect($unit['teacher_ids'])->map(fn ($id) => $names[$id] ?? "Teacher #{$id}")->implode(', ');

            return [
                'class_id' => $unit['class_id'],
                'class_name' => $unit['class_name'],
                'subject_id' => $unit['subject_id'],
                'subject_name' => $unit['subject_name'],
                'teacher_id' => $unit['teacher_ids'][0] ?? null,
                'teacher_name' => $teacherNames !== '' ? $teacherNames : null,
                'reason_code' => $reason->value,
                'reason' => $this->reasonMessage($reason, $unit, $teacherNames),
            ];
        }, $unplaced);
    }

    /**
     * The most specific reason that applied to the slots we rejected. "Class
     * is full" only wins when nothing else was holding the lesson back.
     *
     * @param  array<string, int>  $tally
     */
    protected function dominantReason(array $tally): UnplacedReason
    {
        $best = UnplacedReason::ClassSlotsFull;
        $bestKey = [-1, -1, -1];

        foreach ($tally as $code => $count) {
            $reason = UnplacedReason::from($code);
            $key = $reason === UnplacedReason::ClassSlotsFull ? [0, $count, 0] : [1, $count, $reason->priority()];
            if ($key > $bestKey) {
                $bestKey = $key;
                $best = $reason;
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    protected function reasonMessage(UnplacedReason $reason, array $unit, string $teacherNames): string
    {
        $teacher = $teacherNames !== '' ? $teacherNames : 'The teacher';
        $class = $unit['class_name'];
        $subject = $unit['subject_name'];

        return match ($reason) {
            UnplacedReason::ClassSlotsFull => "{$class} has no free slot left for {$subject}.",
            UnplacedReason::TeacherBusy => "{$teacher} is already teaching in every slot where {$class} is free.",
            UnplacedReason::TeacherDailyLimit => "{$teacher} has reached the daily limit of ".(int) config('timetable.max_daily_lessons')." lessons on every day {$class} is free.",
            UnplacedReason::TeacherConsecutiveLimit => "Placing {$subject} would give {$teacher} more than ".(int) config('timetable.max_consecutive_lessons').' lessons in a row in every free slot.',
            UnplacedReason::SubjectDailyLimit => "{$subject} is already at its limit of {$unit['max_per_day']} lesson(s) per day for {$class} on every day.",
            UnplacedReason::NoDoublePairAvailable => "No two consecutive free periods are available for a double {$subject} lesson in {$class}.",
            UnplacedReason::LockedConflict => 'A locked lesson conflicts with the rest of the timetable.',
        };
    }
}
