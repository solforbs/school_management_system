<?php

namespace Modules\Subject\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Classes\Models\SchoolClass;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;

/**
 * Who teaches what, to whom. A Director assigns a teacher to a subject in a
 * class here, and that assignment is what lets the teacher enter results
 * for it.
 */
class SubjectTeacherController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('edit subject'), 403);

        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
            // Several teachers can be handed the same subject in one go -
            // co-teaching a class is normal, and doing it one row at a time
            // is not.
            'teacher_ids' => 'required|array|min:1',
            'teacher_ids.*' => 'exists:users,id',
        ]);

        $institutionId = $this->institutionId();

        // 'exists' alone would let a crafted request wire up another
        // school's class, subject or staff.
        $schoolClass = SchoolClass::findOrFail($validated['class_id']);
        $subject = Subject::findOrFail($validated['subject_id']);

        abort_unless($schoolClass->institution_id === $institutionId, 403);
        abort_unless($subject->institution_id === $institutionId, 403);

        $eligibleTeacherIds = $this->scopedTeachers()->pluck('id');
        $assigned = 0;

        foreach ($validated['teacher_ids'] as $teacherId) {
            abort_unless($eligibleTeacherIds->contains((int) $teacherId), 403);

            $assignment = SubjectTeacher::firstOrCreate([
                'class_id' => $schoolClass->id,
                'subject_id' => $subject->id,
                'teacher_id' => (int) $teacherId,
            ], [
                'institution_id' => $institutionId,
                'assigned_by' => Auth::id(),
            ]);

            if ($assignment->wasRecentlyCreated) {
                $assigned++;
            }
        }

        return redirect()->route('subject.teachers.index')->with(
            'success',
            $assigned > 0
                ? $assigned.' teacher(s) assigned to '.$subject->name.' for '.$schoolClass->name.'.'
                : 'Those teachers already teach '.$subject->name.' in '.$schoolClass->name.'.'
        );
    }

    public function destroy(int $id)
    {
        abort_unless(Auth::user()->can('edit subject'), 403);

        $query = SubjectTeacher::query();
        $this->scopeToViewer($query);

        $assignment = $query->findOrFail($id);
        $assignment->delete();

        return redirect()->route('subject.teachers.index')->with('success', 'Assignment removed.');
    }

    private function institutionId(): int
    {
        $institutionId = currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        return $institutionId;
    }

    private function scopeToViewer($query): void
    {
        $user = Auth::user();

        if (isAdmin()) {
            return;
        }

        if ($user->hasRole('Teacher')) {
            // A teacher sees their own assignments - what they've been put
            // down to teach - and not the whole staff's.
            $query->where('institution_id', $user->teacherUserDetails?->institution_id ?? 0)
                ->where('teacher_id', $user->id);

            return;
        }

        $query->where('institution_id', currentInstitution()?->id ?? 0);
    }

    private function teacherCards($teachers): array
    {
        $load = SubjectTeacher::query()
            ->when(! isAdmin(), fn ($query) => $query->where('institution_id', currentInstitution()?->id ?? 0))
            ->get(['teacher_id'])
            ->countBy('teacher_id');

        return $teachers->map(fn (User $teacher) => [
            'id' => (string) $teacher->id,
            'name' => $teacher->name,
            'meta' => collect([
                $teacher->teacherUserDetails?->department,
                $teacher->teacherUserDetails?->employee_number,
            ])->filter()->implode(' · '),
            'initials' => $teacher->initials(),
            'load' => (int) ($load[$teacher->id] ?? 0),
        ])->values()->all();
    }

    private function alreadyAssigned(): array
    {
        if (! Auth::user()->can('edit subject')) {
            return [];
        }

        return SubjectTeacher::query()
            ->when(! isAdmin(), fn ($query) => $query->where('institution_id', currentInstitution()?->id ?? 0))
            ->get(['class_id', 'subject_id', 'teacher_id'])
            ->groupBy(fn ($assignment) => $assignment->class_id.'-'.$assignment->subject_id)
            ->map(fn ($group) => $group->pluck('teacher_id')->map(fn ($id) => (string) $id)->values()->all())
            ->all();
    }

    private function scopedClasses()
    {
        $query = SchoolClass::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    private function scopedSubjects()
    {
        $query = Subject::where('is_active', true);

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    private function scopedTeachers()
    {
        $query = User::role('Teacher')->with('teacherUserDetails');

        if (! isAdmin()) {
            $institutionId = currentInstitution()?->id ?? 0;
            $query->whereHas('teacherUserDetails', fn ($q) => $q->where('institution_id', $institutionId));
        }

        return $query->orderBy('name')->get();
    }
}
