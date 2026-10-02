<?php

namespace Modules\Result\Services;

use App\Models\TeacherClasses;
use App\Models\TeacherSubjects;
use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Models\ExamSitting;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Models\TimetableEntry;

/**
 * Which results a teacher may see and enter.
 *
 * Two doors lead in, and a teacher only needs one of them:
 *
 * - Subject teacher: they've been assigned a subject in a class (or are
 *   timetabled to teach it), and own that subject's marks for that class.
 * - Class teacher: the class is theirs, so every subject in it is theirs to
 *   enter - they're the one collating the class's report cards.
 *
 * Timetable entries still count as an assignment. They were the only signal
 * before subject_teachers existed, and a school that has scheduled a
 * teacher for a subject has said the same thing a different way.
 */
class ResultAccessService
{
    /**
     * The (class_id, subject_id) pairs a teacher owns marks for, from
     * explicit assignments and from the timetable alike.
     *
     * @return Collection<int, object{class_id: int, subject_id: int}>
     */
    public static function subjectPairs(User $teacher): Collection
    {
        $assigned = SubjectTeacher::where('teacher_id', $teacher->id)
            ->get(['class_id', 'subject_id']);

        $timetabled = TimetableEntry::where('teacher_id', $teacher->id)
            ->whereNotNull('subject_id')
            ->whereNotNull('class_id')
            ->get(['class_id', 'subject_id']);

        // The simple pivots say "these are my classes" and "these are my
        // subjects" separately; every combination of the two is a pair the
        // teacher owns - pick the class, then one of your subjects.
        $pivotClassIds = TeacherClasses::where('teacher_id', $teacher->id)->pluck('class_id');
        $pivotSubjectIds = TeacherSubjects::where('teacher_id', $teacher->id)->pluck('subject_id');

        $pivotPairs = $pivotClassIds->crossJoin($pivotSubjectIds)
            ->map(fn ($combo) => (object) ['class_id' => (int) $combo[0], 'subject_id' => (int) $combo[1]]);

        return $assigned->concat($timetabled)
            ->map(fn ($entry) => (object) ['class_id' => (int) $entry->class_id, 'subject_id' => (int) $entry->subject_id])
            ->concat($pivotPairs)
            ->unique(fn ($pair) => $pair->class_id.'-'.$pair->subject_id)
            ->values();
    }

    /**
     * Classes this teacher is the class teacher of - every subject in them
     * is theirs to enter.
     *
     * @return Collection<int, int>
     */
    public static function classTeacherClassIds(User $teacher): Collection
    {
        return SchoolClass::where('class_teacher_id', $teacher->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * Every class the teacher is attached to - the ones they've been given
     * a subject in, the ones they are class teacher of, and the ones on
     * their class pivot even before a subject is assigned. Whether they may
     * actually save a mark is still gated per subject by canGrade().
     *
     * @return Collection<int, int>
     */
    public static function classIds(User $teacher): Collection
    {
        return self::subjectPairs($teacher)
            ->pluck('class_id')
            ->concat(self::classTeacherClassIds($teacher))
            ->concat(TeacherClasses::where('teacher_id', $teacher->id)->pluck('class_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Whether this teacher may enter or amend a mark for the given class
     * and subject. A null subject only passes for a class teacher - there's
     * no subject to have been assigned.
     */
    public static function canGrade(User $teacher, int $classId, ?int $subjectId): bool
    {
        if (self::classTeacherClassIds($teacher)->contains($classId)) {
            return true;
        }

        if ($subjectId === null) {
            return false;
        }

        return self::subjectPairs($teacher)
            ->contains(fn ($pair) => $pair->class_id === $classId && $pair->subject_id === $subjectId);
    }

    /**
     * Narrow a Result query to what this teacher may see: their own
     * subjects in the classes they teach, plus everything in the classes
     * they're class teacher of.
     *
     * The subject a mark belongs to now lives on the result itself
     * (`results.subject_id`); older rows that pre-date the column fall
     * back to their examination's subject.
     */
    public static function scopeResults($query, User $teacher): void
    {
        $pairs = self::subjectPairs($teacher);
        $classTeacherClassIds = self::classTeacherClassIds($teacher);

        $query->where(function ($q) use ($pairs, $classTeacherClassIds) {
            foreach ($pairs as $pair) {
                $q->orWhere(function ($q2) use ($pair) {
                    $q2->where('class_id', $pair->class_id)
                        ->where(function ($q3) use ($pair) {
                            $q3->where('subject_id', $pair->subject_id)
                                ->orWhere(fn ($q4) => $q4->whereNull('subject_id')
                                    ->whereHas('examination', fn ($q5) => $q5->where('subject_id', $pair->subject_id)));
                        });
                });
            }

            if ($classTeacherClassIds->isNotEmpty()) {
                $q->orWhereIn('class_id', $classTeacherClassIds);
            }

            // Nothing assigned and no class of their own - the teacher sees
            // nothing rather than everything at the institution.
            if ($pairs->isEmpty() && $classTeacherClassIds->isEmpty()) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Narrow a Result query to everything the given viewer may see, whoever
     * they are: an Admin sees the lot, a Teacher their own subjects and
     * class, a Parent their children's marks, a Student their own, and
     * anyone else their school's.
     */
    public static function scopeVisibleResults($query, User $user): void
    {
        if (isAdmin()) {
            return;
        }

        if ($user->hasRole('Teacher')) {
            $query->where('institution_id', $user->teacherUserDetails?->institution_id ?? 0);

            self::scopeResults($query, $user);

            return;
        }

        if ($user->hasRole('Parent')) {
            $query->whereIn('student_id', StudentDetails::where('parent_id', $user->id)->pluck('student_id'));

            return;
        }

        if ($user->hasRole('Student')) {
            $query->where('student_id', $user->id);

            return;
        }

        $query->where('institution_id', currentInstitution()?->id ?? 0);
    }

    /**
     * Classes selectable on a result screen: for a Teacher, the ones they
     * may enter marks in; for anyone else, their school's.
     *
     * @return Collection<int, SchoolClass>
     */
    public static function selectableClasses(User $user): Collection
    {
        if ($user->hasRole('Teacher')) {
            return SchoolClass::with('curriculum')->whereIn('id', self::classIds($user))->orderBy('name')->get();
        }

        $query = SchoolClass::with('curriculum');

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Subjects selectable on a result screen, optionally narrowed to a
     * single class. For a Teacher these are the subjects they own marks
     * for (in that class, if one is given); for anyone else, the school's.
     *
     * @return Collection<int, Subject>
     */
    public static function selectableSubjects(User $user, ?int $classId = null): Collection
    {
        if ($user->hasRole('Teacher')) {
            $institutionId = $user->teacherUserDetails?->institution_id ?? 0;

            // A class teacher owns every subject in their own class; for
            // that class, don't narrow the list down to assigned pairs.
            $isClassTeacherHere = $classId !== null
                && self::classTeacherClassIds($user)->contains($classId);

            if ($isClassTeacherHere) {
                return Subject::where('institution_id', $institutionId)->orderBy('name')->get();
            }

            $subjectIds = self::subjectPairs($user)
                ->when($classId !== null, fn ($pairs) => $pairs->where('class_id', $classId))
                ->pluck('subject_id')
                ->unique()
                ->values();

            return Subject::whereIn('id', $subjectIds)->orderBy('name')->get();
        }

        $query = Subject::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Examinations selectable on a result screen. A sitting is
     * institution-wide, so every viewer sees every sitting at their
     * school - what a Teacher may actually grade within one is enforced
     * per class/subject by canGrade(). The optional class/subject filters
     * are still honoured for a paper the Director pinned.
     *
     * @return Collection<int, Examination>
     */
    public static function selectableExaminations(User $user, ?int $classId = null, ?int $subjectId = null): Collection
    {
        $query = Examination::with(['subject', 'schoolClass']);

        if ($user->hasRole('Teacher')) {
            $query->where('institution_id', $user->teacherUserDetails?->institution_id ?? 0);
        } elseif (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->orderByDesc('exam_date')
            ->get();
    }

    /**
     * Students selectable on a result screen, optionally narrowed to a
     * single class.
     *
     * @return Collection<int, StudentDetails>
     */
    public static function selectableStudents(User $user, ?int $classId = null): Collection
    {
        $query = StudentDetails::with('student');

        if ($user->hasRole('Teacher')) {
            $query->whereIn('class_id', self::classIds($user));
        } elseif (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->when($classId, fn ($q) => $q->where('class_id', $classId))->get();
    }

    /**
     * Exam sittings a viewer records marks for: every sitting at their
     * school, and for a Teacher only those that actually hold a paper they
     * may grade - one in a class/subject they're assigned, or any paper in
     * a class they are class teacher of.
     *
     * @return Collection<int, ExamSitting>
     */
    public static function selectableSittings(User $user): Collection
    {
        $query = ExamSitting::query()->withCount('papers');

        if ($user->hasRole('Teacher')) {
            $query->where('institution_id', $user->teacherUserDetails?->institution_id ?? 0);

            $pairs = self::subjectPairs($user);
            $classTeacherClassIds = self::classTeacherClassIds($user);

            if ($pairs->isEmpty() && $classTeacherClassIds->isEmpty()) {
                return collect();
            }

            $query->whereHas('papers', function ($papers) use ($pairs, $classTeacherClassIds) {
                $papers->where(function ($gradable) use ($pairs, $classTeacherClassIds) {
                    foreach ($pairs as $pair) {
                        $gradable->orWhere(fn ($p) => $p
                            ->where('class_id', $pair->class_id)
                            ->where('subject_id', $pair->subject_id));
                    }

                    if ($classTeacherClassIds->isNotEmpty()) {
                        $gradable->orWhereIn('class_id', $classTeacherClassIds->all());
                    }
                });
            });
        } elseif (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderByDesc('academic_year')->orderByDesc('id')->get();
    }

    /**
     * The classes a viewer picks from once inside a sitting: the classes it
     * has papers for, narrowed for a Teacher to the ones they teach.
     *
     * @return Collection<int, SchoolClass>
     */
    public static function classesForSitting(User $user, ExamSitting $sitting): Collection
    {
        $classIds = $sitting->papers()
            ->whereNotNull('class_id')
            ->distinct()
            ->pluck('class_id')
            ->map(fn ($id) => (int) $id);

        if ($user->hasRole('Teacher')) {
            $allowed = self::classIds($user);
            $classIds = $classIds->filter(fn ($id) => $allowed->contains($id));
        }

        return SchoolClass::whereIn('id', $classIds->unique()->values())
            ->orderBy('name')
            ->get();
    }

    /**
     * The subjects a viewer may enter for one class of a sitting: the
     * subjects that sitting examines in that class, intersected with what
     * the viewer is allowed to grade there.
     *
     * @return Collection<int, Subject>
     */
    public static function subjectsForSittingClass(User $user, ExamSitting $sitting, int $classId): Collection
    {
        $sittingSubjectIds = $sitting->papers()
            ->where('class_id', $classId)
            ->whereNotNull('subject_id')
            ->distinct()
            ->pluck('subject_id')
            ->map(fn ($id) => (int) $id);

        return self::selectableSubjects($user, $classId)
            ->whereIn('id', $sittingSubjectIds->all())
            ->values();
    }

    /**
     * The one paper a sitting holds for a given class and subject - the
     * concrete Examination the mark sheet then saves against.
     */
    public static function resolvePaper(ExamSitting $sitting, int $classId, int $subjectId): ?Examination
    {
        return $sitting->papers()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->first();
    }
}
