<?php

namespace Modules\Examinations\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Student\Models\StudentDetails;

/**
 * The examination timetable: which papers a class sits, in what order, for
 * one sitting of one term.
 *
 * An examination run is not a single week - end-of-term papers routinely
 * stretch across a fortnight or more - so the timetable is laid out as a
 * date-by-session calendar grid (rows are exam days, grouped into weeks;
 * columns are the sitting times), one grid per class.
 *
 * Shared by the on-screen preview and the printed PDF so what a Director
 * checks is exactly what the school hands out.
 */
class ExamTimetableBuilder
{
    /**
     * The label given to papers that have a date but no sitting time yet, so
     * they still land on the grid instead of dropping off it.
     */
    public const UNSCHEDULED_SESSION = 'Time TBA';

    /**
     * Papers grouped by class: each class carries its papers, the distinct
     * sitting times that become the grid's columns, and the exam days -
     * numbered by week - that become its rows.
     *
     * @param  array{term?: string|null, academic_year?: int|string|null, exam_type?: string|null, class_id?: int|string|null}  $filters
     * @return Collection<int, array{
     *     class: SchoolClass|null,
     *     class_name: string,
     *     examinations: Collection<int, Examination>,
     *     sessions: Collection<int, string>,
     *     days: Collection<int, array<string, mixed>>,
     *     week_count: int,
     *     starts_on: CarbonInterface|null,
     *     ends_on: CarbonInterface|null,
     * }>
     */
    public function build(User $viewer, array $filters = []): Collection
    {
        $examinations = $this->query($viewer, $filters)
            ->with(['schoolClass', 'subject'])
            ->get()
            // Papers with no date sit at the end rather than jumbled among
            // the scheduled ones.
            ->sortBy(fn (Examination $examination) => [
                $examination->exam_date?->format('Y-m-d') ?? '9999-12-31',
                $examination->start_time?->format('H:i') ?? '99:99',
                $examination->subject?->name ?? $examination->subject_name ?? '',
            ])
            ->values();

        return $examinations
            ->groupBy(fn (Examination $examination) => (int) $examination->class_id)
            ->map(function (Collection $group) {
                $group = $group->values();
                $days = $this->days($group);

                return [
                    'class' => $group->first()->schoolClass,
                    'class_name' => $group->first()->schoolClass?->name
                        ?? $group->first()->class_name
                        ?? 'Unassigned class',
                    'examinations' => $group,
                    'sessions' => $this->sessions($group),
                    'days' => $days,
                    'week_count' => (int) $days->max('week'),
                    'starts_on' => $this->firstExamDate($group),
                    'ends_on' => $group->pluck('exam_date')->filter()->sort()->last(),
                ];
            })
            ->sortBy('class_name')
            ->values();
    }

    /**
     * How a paper's sitting time reads as a grid column heading.
     */
    public function sessionLabel(Examination $examination): string
    {
        if (! $examination->start_time) {
            return self::UNSCHEDULED_SESSION;
        }

        return $examination->end_time
            ? $examination->start_time->format('H:i').' – '.$examination->end_time->format('H:i')
            : $examination->start_time->format('H:i');
    }

    /**
     * The distinct sitting times across a class's papers, earliest first,
     * with the "time TBA" column (if any) kept last. These are the grid's
     * columns.
     *
     * @param  Collection<int, Examination>  $examinations
     * @return Collection<int, string>
     */
    private function sessions(Collection $examinations): Collection
    {
        $scheduled = $examinations
            ->filter(fn (Examination $examination) => $examination->start_time !== null)
            ->map(fn (Examination $examination) => $this->sessionLabel($examination))
            ->unique()
            ->sort()
            ->values();

        if ($examinations->contains(fn (Examination $examination) => $examination->start_time === null)) {
            $scheduled->push(self::UNSCHEDULED_SESSION);
        }

        return $scheduled->values();
    }

    /**
     * One entry per exam day, in date order, each numbered by which week of
     * the run it falls in (week 1 being the week the first paper sits) and
     * carrying its papers bucketed by sitting time. Undated papers collapse
     * into a single trailing "not scheduled" row.
     *
     * @param  Collection<int, Examination>  $examinations
     * @return Collection<int, array{
     *     date: CarbonInterface|null,
     *     date_key: string,
     *     week: int,
     *     is_today: bool,
     *     paper_count: int,
     *     cells: Collection<string, Collection<int, Examination>>,
     * }>
     */
    private function days(Collection $examinations): Collection
    {
        $firstWeekStart = $this->firstExamDate($examinations)?->toImmutable()->startOfWeek();

        return $examinations
            ->groupBy(fn (Examination $examination) => $examination->exam_date?->format('Y-m-d') ?? 'unscheduled')
            ->map(function (Collection $dayExaminations, string $dateKey) use ($firstWeekStart) {
                $date = $dateKey === 'unscheduled' ? null : Carbon::parse($dateKey);

                $week = $date && $firstWeekStart
                    ? intdiv((int) $firstWeekStart->diffInDays($date->toImmutable()->startOfWeek(), absolute: true), 7) + 1
                    : 0;

                return [
                    'date' => $date,
                    'date_key' => $dateKey,
                    'week' => $week,
                    'is_today' => (bool) $date?->isToday(),
                    'paper_count' => $dayExaminations->count(),
                    'cells' => $dayExaminations
                        ->groupBy(fn (Examination $examination) => $this->sessionLabel($examination))
                        ->map(fn (Collection $cell) => $cell->values()),
                ];
            })
            ->values();
    }

    /**
     * The earliest dated paper in the set, or null when nothing is dated.
     *
     * @param  Collection<int, Examination>  $examinations
     */
    private function firstExamDate(Collection $examinations): ?CarbonInterface
    {
        return $examinations->pluck('exam_date')->filter()->sort()->first();
    }

    /**
     * The academic years that actually have examinations, newest first - so
     * the year picker only offers years the school has papers in.
     *
     * @return Collection<int, int>
     */
    public function academicYears(User $viewer): Collection
    {
        return $this->scoped($viewer)
            ->whereNotNull('academic_year')
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year')
            ->map(fn ($year) => (int) $year)
            ->values();
    }

    /**
     * The term labels in use, since a school types its own ("Second Term",
     * "Term 2") rather than picking from a fixed list.
     *
     * @return Collection<int, string>
     */
    public function terms(User $viewer): Collection
    {
        return $this->scoped($viewer)
            ->whereNotNull('term')
            ->distinct()
            ->orderBy('term')
            ->pluck('term')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(User $viewer, array $filters)
    {
        return $this->scoped($viewer)
            ->when(filled($filters['sitting_id'] ?? null), fn ($query) => $query->where('sitting_id', $filters['sitting_id']))
            ->when(filled($filters['term'] ?? null), fn ($query) => $query->where('term', $filters['term']))
            ->when(filled($filters['academic_year'] ?? null), fn ($query) => $query->where('academic_year', $filters['academic_year']))
            ->when(filled($filters['exam_type'] ?? null), fn ($query) => $query->where('exam_type', $filters['exam_type']))
            ->when(filled($filters['class_id'] ?? null), fn ($query) => $query->where('class_id', $filters['class_id']));
    }

    /**
     * Examinations this viewer may see: their school's, or their children's
     * / their own school for a Parent or Student.
     */
    private function scoped(User $viewer)
    {
        $query = Examination::query();

        if (isAdmin()) {
            return $query;
        }

        if ($viewer->hasRole('Teacher')) {
            return $query->where('institution_id', $viewer->teacherUserDetails?->institution_id ?? 0);
        }

        if ($viewer->hasAnyRole(['Parent', 'Student'])) {
            $institutionIds = $viewer->hasRole('Parent')
                ? StudentDetails::where('parent_id', $viewer->id)->pluck('institution_id')
                : StudentDetails::where('student_id', $viewer->id)->pluck('institution_id');

            return $query->whereIn('institution_id', $institutionIds);
        }

        return $query->where('institution_id', currentInstitution()?->id ?? 0);
    }
}
