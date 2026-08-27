<?php

namespace Modules\Examinations\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Student\Models\StudentDetails;

/**
 * The examination timetable: which papers a class sits, in what order, for
 * one sitting of one term.
 *
 * Shared by the on-screen preview and the printed PDF so what a Director
 * checks is exactly what the school hands out.
 */
class ExamTimetableBuilder
{
    /**
     * Papers grouped by class, each class's papers in the order they're sat.
     *
     * @param  array{term?: string|null, academic_year?: int|string|null, exam_type?: string|null, class_id?: int|string|null}  $filters
     * @return Collection<int, array{class: SchoolClass|null, class_name: string, examinations: Collection<int, Examination>}>
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
            ->map(fn (Collection $group) => [
                'class' => $group->first()->schoolClass,
                'class_name' => $group->first()->schoolClass?->name
                    ?? $group->first()->class_name
                    ?? 'Unassigned class',
                'examinations' => $group->values(),
            ])
            ->sortBy('class_name')
            ->values();
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
