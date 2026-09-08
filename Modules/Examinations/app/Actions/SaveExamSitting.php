<?php

namespace Modules\Examinations\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Models\ExamSitting;
use Modules\Examinations\Support\PaperSchedule;
use Modules\Subject\Models\Subject;

/**
 * Creating an examination sitting, in one place - shared by the Livewire
 * screen and the controller endpoint.
 *
 * A sitting is the event ("Mock Exam", "End Term"); saving it fans out one
 * paper per subject per class, so a school never enters a subject at a
 * time. Leaving the target class blank runs it for every class; setting one
 * targets that class alone.
 */
class SaveExamSitting
{
    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'term' => 'nullable|string|max:100',
            'exam_type' => 'nullable|string|in:'.implode(',', array_keys(Examination::EXAM_TYPES)),
            'academic_year' => 'nullable|integer|min:2000|max:2100',
            'class_id' => 'nullable|exists:classes,id',
            'starts_on' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(array $data, int $institutionId, ?ExamSitting $sitting = null): ExamSitting
    {
        $startsOn = ! empty($data['starts_on'])
            ? Carbon::parse($data['starts_on'])->startOfDay()
            : PaperSchedule::nextExamWeek();

        $targetClass = ! empty($data['class_id']) ? SchoolClass::find($data['class_id']) : null;

        // 'exists' alone would let a crafted request target another school's
        // class.
        abort_unless(! $targetClass || $targetClass->institution_id === $institutionId, 403);

        $payload = [
            'institution_id' => $institutionId,
            'title' => $data['title'],
            'term' => $data['term'] ?? null,
            'exam_type' => $data['exam_type'] ?? null,
            'academic_year' => $data['academic_year'] ?? $startsOn->year,
            'class_id' => $targetClass?->id,
            'notes' => $data['notes'] ?? null,
        ];

        if ($sitting) {
            $sitting->update($payload);
        } else {
            $sitting = ExamSitting::create($payload);
        }

        self::syncPapers($sitting, $startsOn);

        return $sitting;
    }

    /**
     * Bring the sitting's papers in line with its current scope: one paper
     * per (class, subject). A paper's schedule and marks are written once,
     * on creation, so a later manual tweak survives re-saving the sitting;
     * the denormalised term/type/year are always kept mirrored.
     */
    private static function syncPapers(ExamSitting $sitting, CarbonInterface $startsOn): void
    {
        $classes = $sitting->class_id
            ? SchoolClass::where('id', $sitting->class_id)->get()
            : SchoolClass::where('institution_id', $sitting->institution_id)->orderBy('name')->get();

        $subjects = Subject::where('institution_id', $sitting->institution_id)
            ->where('is_active', true)
            ->orderByDesc('is_compulsory')
            ->orderBy('name')
            ->get()
            ->values();

        if ($classes->isEmpty() || $subjects->isEmpty()) {
            return;
        }

        foreach ($subjects as $slotIndex => $subject) {
            $slot = PaperSchedule::slot($startsOn, $slotIndex);

            foreach ($classes as $class) {
                $paper = Examination::firstOrNew([
                    'sitting_id' => $sitting->id,
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                ]);

                $paper->fill([
                    'institution_id' => $sitting->institution_id,
                    'subject_name' => $subject->name,
                    'class_name' => $class->name,
                    'term' => $sitting->term,
                    'exam_type' => $sitting->exam_type,
                    'academic_year' => $sitting->academic_year,
                ]);

                if (! $paper->exists) {
                    $paper->fill([
                        'title' => $subject->name.' — '.$sitting->displayName(),
                        'exam_date' => $slot['date']->toDateString(),
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'total_marks' => $slot['marks'],
                        'passing_marks' => $slot['passing_marks'],
                    ]);
                }

                $paper->save();
            }
        }
    }
}
