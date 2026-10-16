<?php

namespace Modules\Timetable\Services;

use Illuminate\Support\Collection;
use Modules\Classes\Models\SchoolClass;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Models\SubjectAllocation;

/**
 * Works out, per class, which subjects must be taught, how often, and by
 * whom. The pre-flight check and the generator both read from here so they
 * can never disagree about what a school needs.
 *
 * A class is only given subjects the school has linked to it, either with a
 * SubjectAllocation (for the class or its curriculum) or a SubjectTeacher
 * assignment. Subjects with neither are reported as skipped, never guessed.
 */
class TimetableDemandResolver
{
    public const QUOTA_ALLOCATION = 'allocation';

    public const QUOTA_INFERRED = 'inferred';

    public const TEACHER_ASSIGNED = 'assigned';

    public const TEACHER_CLASS_TEACHER = 'class_teacher';

    public const TEACHER_NONE = 'none';

    /**
     * Classes in the requested scope, always limited to one institution.
     */
    public function targetClasses(int $institutionId, string $scopeType = 'whole_school', ?int $scopeId = null): Collection
    {
        $query = SchoolClass::where('institution_id', $institutionId)->with('curriculum');

        if ($scopeType === 'class') {
            if (! $scopeId) {
                return collect();
            }
            $query->where('id', $scopeId);
        } elseif ($scopeType === 'stream') {
            if (! $scopeId) {
                return collect();
            }
            $anchor = SchoolClass::where('institution_id', $institutionId)->find($scopeId);
            if (! $anchor) {
                return collect();
            }
            if (filled($anchor->level)) {
                $query->where('level', $anchor->level);
            } else {
                $query->where('id', $scopeId);
            }
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @param  int  $dayCount  number of teaching days, used to derive a per-day cap
     * @return array{demands: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    public function resolve(int $institutionId, Collection $classes, int $dayCount): array
    {
        $subjects = Subject::where('institution_id', $institutionId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $allocations = SubjectAllocation::where('institution_id', $institutionId)->get();

        $assignments = SubjectTeacher::where('institution_id', $institutionId)->get()
            ->groupBy(fn (SubjectTeacher $row) => $row->class_id.'_'.$row->subject_id);

        $demands = [];
        $skipped = [];

        foreach ($classes as $class) {
            $linked = [];

            foreach ($allocations as $alloc) {
                if ($this->allocationAppliesToClass($alloc, $class)) {
                    $linked[$alloc->subject_id] = true;
                }
            }
            foreach ($assignments as $key => $rows) {
                if ((int) $rows->first()->class_id === (int) $class->id) {
                    $linked[$rows->first()->subject_id] = true;
                }
            }

            foreach ($subjects as $subject) {
                if (! isset($linked[$subject->id])) {
                    $skipped[] = [
                        'class_id' => $class->id,
                        'class_name' => $class->name,
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->name,
                    ];

                    continue;
                }

                $demands[] = $this->buildDemand($class, $subject, $allocations, $assignments, $dayCount);
            }
        }

        return ['demands' => $demands, 'skipped' => $skipped];
    }

    /**
     * Class-specific allocations beat curriculum-wide ones.
     */
    protected function findAllocation(Collection $allocations, SchoolClass $class, Subject $subject): ?SubjectAllocation
    {
        $forClass = $allocations->first(fn (SubjectAllocation $a) => (int) $a->subject_id === (int) $subject->id
            && $a->class_id !== null
            && (int) $a->class_id === (int) $class->id);

        if ($forClass) {
            return $forClass;
        }

        return $allocations->first(fn (SubjectAllocation $a) => (int) $a->subject_id === (int) $subject->id
            && $a->class_id === null
            && $a->curriculum_id !== null
            && $class->curriculum_id !== null
            && (int) $a->curriculum_id === (int) $class->curriculum_id);
    }

    protected function allocationAppliesToClass(SubjectAllocation $alloc, SchoolClass $class): bool
    {
        if ($alloc->class_id !== null) {
            return (int) $alloc->class_id === (int) $class->id;
        }

        return $alloc->curriculum_id !== null
            && $class->curriculum_id !== null
            && (int) $alloc->curriculum_id === (int) $class->curriculum_id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDemand(SchoolClass $class, Subject $subject, Collection $allocations, Collection $assignments, int $dayCount): array
    {
        $alloc = $this->findAllocation($allocations, $class, $subject);

        if ($alloc) {
            $periods = (int) $alloc->periods_per_week;
            $weight = $alloc->cognitive_weight ?: SubjectAllocation::inferWeight($subject->name);
            $allowDouble = (bool) $alloc->allow_double_periods;
            $maxPerDay = $alloc->max_per_day !== null ? (int) $alloc->max_per_day : null;
            $quotaSource = self::QUOTA_ALLOCATION;
        } else {
            $periods = SubjectAllocation::inferDefaultPeriods($subject->name, $class->curriculum?->system);
            $weight = SubjectAllocation::inferWeight($subject->name);
            $allowDouble = SubjectAllocation::inferAllowDouble($subject->name, $periods);
            $maxPerDay = null;
            $quotaSource = self::QUOTA_INFERRED;
        }

        $explicitMaxPerDay = $maxPerDay;
        if ($maxPerDay === null && $periods > 0 && $dayCount > 0) {
            $maxPerDay = (int) ceil($periods / $dayCount);
            if ($allowDouble && $periods >= 2) {
                $maxPerDay = max($maxPerDay, 2);
            }
        }

        $teacherIds = ($assignments->get($class->id.'_'.$subject->id) ?? collect())
            ->pluck('teacher_id')->unique()->values()->all();
        $teacherSource = self::TEACHER_ASSIGNED;

        if ($teacherIds === [] && $class->class_teacher_id) {
            $teacherIds = [(int) $class->class_teacher_id];
            $teacherSource = self::TEACHER_CLASS_TEACHER;
        } elseif ($teacherIds === []) {
            $teacherSource = self::TEACHER_NONE;
        }

        return [
            'class_id' => $class->id,
            'class_name' => $class->name,
            'subject_id' => $subject->id,
            'subject_name' => $subject->name,
            'periods_per_week' => $periods,
            'max_per_day' => $maxPerDay,
            'max_per_day_explicit' => $explicitMaxPerDay,
            'allow_double' => $allowDouble,
            'cognitive_weight' => $weight,
            'teacher_ids' => array_map('intval', $teacherIds),
            'teacher_source' => $teacherSource,
            'quota_source' => $quotaSource,
            'allocation_id' => $alloc?->id,
        ];
    }
}
