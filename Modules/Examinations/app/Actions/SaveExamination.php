<?php

namespace Modules\Examinations\Actions;

use Illuminate\Support\Carbon;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Subject\Models\Subject;

/**
 * Scheduling an examination, in one place - shared by the Livewire screen
 * and the controller endpoint.
 */
class SaveExamination
{
    /**
     * @return array<string, string|array<int, string>>
     */
    public static function rules(): array
    {
        return [
            // Both optional: an examination is a whole-school sitting by
            // default, and the Director only pins it to a class/subject
            // when they deliberately want a one-off paper.
            'class_id' => 'nullable|exists:classes,id',
            'title' => 'required|string|max:255',
            'subject_id' => 'nullable|exists:subjects,id',
            'term' => 'nullable|string|max:100',
            'exam_type' => 'nullable|in:'.implode(',', array_keys(Examination::EXAM_TYPES)),
            'exam_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'total_marks' => 'required|integer|min:1',
            'passing_marks' => 'nullable|integer|min:0|lte:total_marks',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(array $data, int $institutionId, ?Examination $examination = null): Examination
    {
        $classId = $data['class_id'] ?? null;
        $subjectId = $data['subject_id'] ?? null;

        $schoolClass = $classId ? SchoolClass::find($classId) : null;
        $subject = $subjectId ? Subject::find($subjectId) : null;

        // 'exists' alone would let a crafted request schedule a paper for
        // another school's class or subject. Only enforced when the
        // Director actually pinned one.
        abort_unless(! $classId || ($schoolClass && $schoolClass->institution_id === $institutionId), 403);
        abort_unless(! $subjectId || ($subject && $subject->institution_id === $institutionId), 403);

        $payload = [
            'institution_id' => $institutionId,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'title' => $data['title'],
            'term' => $data['term'] ?? null,
            'exam_type' => $data['exam_type'] ?? null,
            'exam_date' => $data['exam_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'total_marks' => $data['total_marks'],
            'passing_marks' => $data['passing_marks'] ?? null,
            'notes' => $data['notes'] ?? null,

            // Keep the legacy class_name/subject_name columns in sync for
            // anything still reading them directly, though class_id and
            // subject_id are now the source of truth.
            'class_name' => $schoolClass?->name,
            'subject_name' => $subject?->name,

            // Term labels repeat every year ("Second Term" happens
            // annually), so the exam date's year is what actually
            // distinguishes them.
            'academic_year' => Carbon::parse($data['exam_date'])->year,
        ];

        if ($examination) {
            $examination->update($payload);

            return $examination;
        }

        return Examination::create($payload);
    }
}
