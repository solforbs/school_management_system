<?php

namespace Modules\Selections\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Institution\Models\Institution;
use Modules\Selections\Models\SubjectSelection;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;

class SelectionsController extends Controller
{
    /**
     * Save the student's elective subject picks.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->hasRole('Student'), 403);

        $studentDetails = StudentDetails::where('student_id', Auth::id())->first();
        abort_unless($studentDetails, 404);

        $institution = Institution::findOrFail($studentDetails->institution_id);

        $validated = $request->validate([
            'subject_ids' => 'array',
            'subject_ids.*' => 'integer|distinct',
        ]);

        $subjectIds = $validated['subject_ids'] ?? [];
        $count = count($subjectIds);

        if ($count < $institution->min_electives) {
            return back()->with('error', "Please select at least {$institution->min_electives} subject(s).");
        }

        if ($institution->max_electives !== null && $count > $institution->max_electives) {
            return back()->with('error', "Please select at most {$institution->max_electives} subject(s).");
        }

        $validSubjectIds = Subject::where('institution_id', $institution->id)
            ->where('is_compulsory', false)
            ->where('is_active', true)
            ->whereIn('id', $subjectIds)
            ->pluck('id')
            ->all();

        if (count($validSubjectIds) !== $count) {
            return back()->with('error', 'One or more selected subjects are not available for your institution.');
        }

        DB::transaction(function () use ($studentDetails, $institution, $validSubjectIds) {
            SubjectSelection::where('student_id', Auth::id())->delete();

            foreach ($validSubjectIds as $subjectId) {
                SubjectSelection::create([
                    'institution_id' => $institution->id,
                    'student_id' => Auth::id(),
                    'class_id' => (int) $studentDetails->class_id,
                    'subject_id' => $subjectId,
                ]);
            }
        });

        return redirect()->route('selections.index')->with('success', 'Your subjects have been saved.');
    }
}
