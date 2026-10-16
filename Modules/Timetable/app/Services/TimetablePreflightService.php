<?php

namespace Modules\Timetable\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Modules\Classes\Models\SchoolClass;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Models\TimetableStructure;
use Modules\Timetable\Support\PreflightIssue;
use Modules\Timetable\Support\PreflightReport;
use Spatie\Permission\Models\Role;

/**
 * Checks a school has what the generator needs before it runs, and says
 * plainly what is missing and what to do about it. Read-only: it never
 * writes to the database.
 */
class TimetablePreflightService
{
    public function __construct(protected TimetableDemandResolver $resolver) {}

    public function check(int $institutionId, TimetableStructure $structure, string $scopeType = 'whole_school', ?int $scopeId = null): PreflightReport
    {
        $report = new PreflightReport;

        $classes = $this->resolver->targetClasses($institutionId, $scopeType, $scopeId);
        if ($classes->isEmpty()) {
            $report->add($this->issue(PreflightIssue::BLOCKER, 'NO_CLASSES',
                $scopeType === 'whole_school'
                    ? 'This school has no classes yet.'
                    : 'The selected class or stream could not be found.',
                'Add your classes first, then come back to generate the timetable.',
                'classes.index', 'Go to Classes'));

            return $report;
        }

        $days = array_values(array_filter((array) ($structure->days_of_week ?? [])));
        $teaching = $structure->getTeachingPeriods();

        $structureOk = $this->checkStructure($report, $structure, $days, $teaching);

        $subjectCount = Subject::where('institution_id', $institutionId)->where('is_active', true)->count();
        if ($subjectCount === 0) {
            $report->add($this->issue(PreflightIssue::BLOCKER, 'NO_SUBJECTS',
                'This school has no active subjects.',
                'Add the subjects your school teaches, and make sure they are marked active.',
                'subject.index', 'Go to Subjects'));
        }

        if (! $this->schoolHasTeachers($institutionId)) {
            $report->add($this->issue(PreflightIssue::BLOCKER, 'NO_TEACHERS',
                'This school has no teachers.',
                'Add your teachers, then assign them to the subjects they teach in each class.',
                'teacher.index', 'Go to Teachers'));
        }

        if ($subjectCount === 0 || ! $structureOk) {
            return $report;
        }

        $slotsPerWeek = count($days) * count($teaching);
        $resolved = $this->resolver->resolve($institutionId, $classes, count($days));

        $this->checkDemands($report, $classes, $resolved, $structure, $days, $teaching, $slotsPerWeek);

        return $report;
    }

    /**
     * @param  array<int, string>  $days
     * @param  array<int, array<string, mixed>>  $teaching
     */
    protected function checkStructure(PreflightReport $report, TimetableStructure $structure, array $days, array $teaching): bool
    {
        $ok = true;

        if ($days === [] || $teaching === []) {
            $report->add($this->issue(PreflightIssue::BLOCKER, 'NO_STRUCTURE_PERIODS',
                $days === []
                    ? 'The bell schedule has no teaching days.'
                    : 'The bell schedule has no lesson periods.',
                'Open the generator wizard and set the school days and the number of lessons per day.',
                'timetable.index', 'Review bell schedule'));

            $ok = false;
        }

        if ($teaching !== [] && ! $this->hasValidTimes($structure, $teaching)) {
            $report->add($this->issue(PreflightIssue::BLOCKER, 'INVALID_STRUCTURE',
                'The bell schedule has periods with missing, reversed or overlapping times.',
                'Open the generator wizard and correct the start time, lesson length and breaks so periods follow one another without overlapping.',
                'timetable.index', 'Review bell schedule'));

            $ok = false;
        }

        return $ok;
    }

    /**
     * @param  array<int, array<string, mixed>>  $teaching
     */
    protected function hasValidTimes(TimetableStructure $structure, array $teaching): bool
    {
        $numbers = array_map(fn ($p) => (int) ($p['period_number'] ?? 0), $teaching);
        if (count($numbers) !== count(array_unique($numbers)) || in_array(0, $numbers, true)) {
            return false;
        }

        $previousEnd = null;
        foreach (array_values($structure->getAllScheduleIntervals()) as $interval) {
            $start = $this->minutes($interval['start_time'] ?? null);
            $end = $this->minutes($interval['end_time'] ?? null);

            if ($start === null || $end === null || $end <= $start) {
                return false;
            }
            if ($previousEnd !== null && $start < $previousEnd) {
                return false;
            }
            $previousEnd = $end;
        }

        return true;
    }

    protected function minutes(mixed $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    protected function schoolHasTeachers(int $institutionId): bool
    {
        if (SubjectTeacher::where('institution_id', $institutionId)->exists()) {
            return true;
        }

        if (! Role::where('name', 'Teacher')->exists()) {
            return false;
        }

        return User::role('Teacher')
            ->whereHas('teacherUserDetails', fn ($q) => $q->where('institution_id', $institutionId))
            ->exists();
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @param  array{demands: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}  $resolved
     * @param  array<int, string>  $days
     * @param  array<int, array<string, mixed>>  $teaching
     */
    protected function checkDemands(PreflightReport $report, Collection $classes, array $resolved, TimetableStructure $structure, array $days, array $teaching, int $slotsPerWeek): void
    {
        $dayCount = count($days);
        $demandsByClass = collect($resolved['demands'])->groupBy('class_id');
        $validDemands = [];

        foreach ($classes as $class) {
            $classDemands = $demandsByClass->get($class->id, collect());

            if ($classDemands->isEmpty()) {
                $report->add($this->issue(PreflightIssue::BLOCKER, 'CLASS_NO_SUBJECTS',
                    "{$class->name} has no subjects to schedule.",
                    'Assign teachers to subjects for this class, or set subject allocations (lessons per week) for it.',
                    'subject.teachers.index', 'Assign subject teachers', ['class_id' => $class->id]));

                continue;
            }

            $required = 0;
            foreach ($classDemands as $d) {
                if ($d['periods_per_week'] <= 0) {
                    $report->add($this->issue(PreflightIssue::BLOCKER, 'INVALID_ALLOCATION',
                        "{$d['subject_name']} in {$class->name} is set to {$d['periods_per_week']} lessons per week.",
                        'Set lessons per week to at least 1, or remove the allocation if the class does not take this subject.',
                        'timetable.index', 'Edit allocation', ['class_id' => $class->id, 'subject_id' => $d['subject_id']]));

                    continue;
                }

                if ($d['max_per_day_explicit'] !== null && $d['periods_per_week'] > $d['max_per_day_explicit'] * $dayCount) {
                    $report->add($this->issue(PreflightIssue::BLOCKER, 'INVALID_ALLOCATION',
                        "{$d['subject_name']} in {$class->name} needs {$d['periods_per_week']} lessons a week but is limited to {$d['max_per_day_explicit']} per day over {$dayCount} days.",
                        'Raise the daily limit or lower the weekly lessons for this subject.',
                        'timetable.index', 'Edit allocation', ['class_id' => $class->id, 'subject_id' => $d['subject_id']]));

                    continue;
                }

                $required += $d['periods_per_week'];
                $validDemands[] = $d;
                $this->addDemandNotices($report, $d);
            }

            if ($required > $slotsPerWeek) {
                $report->add($this->issue(PreflightIssue::BLOCKER, 'CLASS_OVER_CAPACITY',
                    "{$class->name} needs {$required} lessons a week but the timetable only has {$slotsPerWeek} slots ({$dayCount} days × ".count($teaching).' lessons).',
                    'Reduce lessons per week for some subjects, add lessons per day, or add a school day.',
                    'timetable.index', 'Review allocations', ['class_id' => $class->id, 'required' => $required, 'available' => $slotsPerWeek]));
            }
        }

        $this->checkDoubleFeasibility($report, $structure, $validDemands, $teaching);
        $this->checkTeacherLoad($report, $validDemands, $slotsPerWeek, $dayCount);
        $this->reportSkipped($report, $resolved['skipped']);
    }

    /**
     * @param  array<string, mixed>  $d
     */
    protected function addDemandNotices(PreflightReport $report, array $d): void
    {
        $where = "{$d['subject_name']} in {$d['class_name']}";
        $context = ['class_id' => $d['class_id'], 'subject_id' => $d['subject_id']];

        if ($d['teacher_source'] === TimetableDemandResolver::TEACHER_NONE) {
            $report->add($this->issue(PreflightIssue::WARNING, 'LESSON_NO_TEACHER',
                "{$where} has no teacher assigned. Its lessons will be placed as \"teacher TBD\".",
                'Assign a teacher to this subject for this class so clashes can be avoided.',
                'subject.teachers.index', 'Assign subject teachers', $context));
        } elseif ($d['teacher_source'] === TimetableDemandResolver::TEACHER_CLASS_TEACHER) {
            $report->add($this->issue(PreflightIssue::WARNING, 'FALLBACK_TO_CLASS_TEACHER',
                "{$where} has no subject teacher, so the class teacher will be used.",
                'Assign a subject teacher if someone else teaches this subject.',
                'subject.teachers.index', 'Assign subject teachers', $context));
        } elseif (count($d['teacher_ids']) > 1) {
            $report->add($this->issue(PreflightIssue::WARNING, 'MULTIPLE_TEACHERS',
                "{$where} has several teachers. The first one free in each slot will be used.",
                'No action needed, or keep a single teacher if the lessons should not be split.',
                'subject.teachers.index', 'Review subject teachers', $context));
        }

        if ($d['allow_double'] && $d['max_per_day'] !== null && $d['max_per_day'] < 2) {
            $report->add($this->issue(PreflightIssue::WARNING, 'DOUBLE_BLOCKED_BY_DAILY_LIMIT',
                "{$where} allows double lessons but is limited to {$d['max_per_day']} lesson per day, so a double cannot be placed.",
                'Raise the daily limit to at least 2, or switch double lessons off for this subject.',
                'timetable.index', 'Edit allocation', $context));
        }

        if ($d['quota_source'] === TimetableDemandResolver::QUOTA_INFERRED) {
            $report->add($this->issue(PreflightIssue::WARNING, 'FALLBACK_QUOTA',
                "{$where} has no lessons-per-week set. {$d['periods_per_week']} per week was estimated from the subject name.",
                'Set the exact lessons per week for this subject so the timetable matches your syllabus.',
                'timetable.index', 'Edit allocation', $context));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $demands
     * @param  array<int, array<string, mixed>>  $teaching
     */
    protected function checkDoubleFeasibility(PreflightReport $report, TimetableStructure $structure, array $demands, array $teaching): void
    {
        $wantsDouble = array_values(array_filter($demands, fn ($d) => $d['allow_double'] && $d['periods_per_week'] >= 2));
        if ($wantsDouble === []) {
            return;
        }

        $numbers = array_map(fn ($p) => (int) $p['period_number'], $teaching);
        foreach ($numbers as $n) {
            if ($structure->areConsecutiveTeachingPeriods($n, $n + 1)) {
                return;
            }
        }

        $report->add($this->issue(PreflightIssue::WARNING, 'DOUBLE_UNSATISFIABLE',
            'Some subjects allow double lessons, but no two lesson periods in the bell schedule sit side by side without a break between them. Doubles will be scheduled as single lessons.',
            'Move or remove a break, or switch double lessons off for these subjects.',
            'timetable.index', 'Review bell schedule'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $demands
     */
    protected function checkTeacherLoad(PreflightReport $report, array $demands, int $slotsPerWeek, int $dayCount): void
    {
        $maxDaily = config('timetable.max_daily_lessons');
        $capacity = $maxDaily ? min($slotsPerWeek, $dayCount * (int) $maxDaily) : $slotsPerWeek;
        $ratio = (float) config('timetable.teacher_high_load_ratio', 0.85);

        $exclusive = [];
        foreach ($demands as $d) {
            if (count($d['teacher_ids']) === 1) {
                $exclusive[$d['teacher_ids'][0]] = ($exclusive[$d['teacher_ids'][0]] ?? 0) + $d['periods_per_week'];
            }
        }

        $names = User::whereIn('id', array_keys($exclusive))->pluck('name', 'id');

        foreach ($exclusive as $teacherId => $load) {
            $name = $names[$teacherId] ?? "Teacher #{$teacherId}";

            if ($load > $capacity) {
                $report->add($this->issue(PreflightIssue::BLOCKER, 'TEACHER_OVERBOOKED',
                    "{$name} is the only teacher for {$load} lessons a week but can take at most {$capacity}.",
                    'Assign another teacher to some of these subjects or classes, or reduce the lessons per week.',
                    'subject.teachers.index', 'Assign subject teachers', ['teacher_id' => $teacherId, 'required' => $load, 'available' => $capacity]));
            } elseif ($load >= $ratio * $capacity) {
                $report->add($this->issue(PreflightIssue::WARNING, 'TEACHER_HIGH_LOAD',
                    "{$name} is booked for {$load} of {$capacity} possible lessons a week, which leaves little room to arrange the timetable.",
                    'Consider sharing some of these lessons with another teacher.',
                    'subject.teachers.index', 'Review subject teachers', ['teacher_id' => $teacherId, 'required' => $load, 'available' => $capacity]));
            }
        }

        $bySubject = collect($demands)->filter(fn ($d) => $d['teacher_ids'] !== [])->groupBy('subject_id');
        foreach ($bySubject as $subjectId => $rows) {
            $pool = $rows->pluck('teacher_ids')->flatten()->unique()->values();
            $required = $rows->sum('periods_per_week');

            if ($pool->count() > 1 && $required > $pool->count() * $capacity) {
                $report->add($this->issue(PreflightIssue::BLOCKER, 'SUBJECT_UNSTAFFABLE',
                    "{$rows->first()['subject_name']} needs {$required} lessons a week across all classes, but its {$pool->count()} teachers can give at most ".($pool->count() * $capacity).'.',
                    'Assign more teachers to this subject, or reduce its lessons per week.',
                    'subject.teachers.index', 'Assign subject teachers', ['subject_id' => $subjectId, 'required' => $required]));
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $skipped
     */
    protected function reportSkipped(PreflightReport $report, array $skipped): void
    {
        foreach (collect($skipped)->groupBy('class_id') as $classId => $rows) {
            $names = $rows->pluck('subject_name')->implode(', ');
            $report->add($this->issue(PreflightIssue::INFO, 'SUBJECTS_SKIPPED',
                "{$rows->first()['class_name']} will not be given: {$names}. They are not linked to this class.",
                'If this class takes any of them, assign a teacher or set an allocation for it.',
                'subject.teachers.index', 'Assign subject teachers', ['class_id' => $classId]));
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function issue(string $severity, string $code, string $message, string $fix, ?string $route = null, ?string $label = null, array $context = []): PreflightIssue
    {
        return new PreflightIssue(
            $severity,
            $code,
            $message,
            $fix,
            $route !== null && Route::has($route) ? $route : null,
            $label,
            $context,
        );
    }
}
