<?php

namespace Modules\Result\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Services\ReportCardCompletionService;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultGrader;

/**
 * Recording marks, in one place.
 *
 * The Livewire screens and the controllers both go through here, so a mark
 * is graded and stored the same way whether it was typed into the single
 * result form, the class marks sheet, or posted straight at the endpoint.
 */
class SaveResult
{
    /**
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'student_id' => 'required|exists:users,id',
            'examination_id' => 'required|exists:examinations,id',
            'marks_obtained' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
        ];
    }

    /**
     * Whether this student already has a mark for this examination in this
     * subject - a whole-school sitting holds one row per student per
     * subject, so the subject is part of the check. This is how each screen
     * warns before hitting the unique index.
     */
    public static function duplicateExists(int $examinationId, int $studentId, ?int $ignoringId = null, ?int $subjectId = null): bool
    {
        return Result::where('examination_id', $examinationId)
            ->where('student_id', $studentId)
            ->when($subjectId !== null, fn ($query) => $query->where('subject_id', $subjectId))
            ->when($ignoringId, fn ($query) => $query->whereKeyNot($ignoringId))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(
        array $data,
        int $institutionId,
        ?Result $result = null,
        ?int $recordedBy = null,
    ): Result {
        $examination = Examination::find($data['examination_id']);

        if ($examination && $data['marks_obtained'] > $examination->total_marks) {
            abort(422, "Marks obtained can't exceed the examination's total marks ({$examination->total_marks}).");
        }

        // The subject a mark is for comes from the form; a paper the
        // Director pinned to one subject supplies it as the fallback.
        $subjectId = $data['subject_id'] ?? $examination?->subject_id;

        $payload = [
            'institution_id' => $institutionId,
            'class_id' => $data['class_id'],
            'subject_id' => $subjectId,
            'student_id' => $data['student_id'],
            'examination_id' => $data['examination_id'],
            'marks_obtained' => $data['marks_obtained'],
            'remarks' => $data['remarks'] ?? null,
            // Grade is auto-computed from the grading scale the class's
            // curriculum runs on, not typed by whoever is entering marks -
            // left null if no scale is configured yet.
            'grade' => self::grade($examination, (float) $data['marks_obtained'], $institutionId, $data['class_id']),
        ];

        if ($result) {
            $result->update($payload);
        } else {
            $result = Result::create($payload + ['recorded_by' => $recordedBy]);
        }

        self::refreshReportCard($result);

        return $result;
    }

    /**
     * Save a whole class's marks for one paper. Blank entries are skipped
     * rather than treated as a zero, so a half-marked sheet can be saved
     * and finished later.
     *
     * The class and subject are the ones the teacher picked on the sheet;
     * a paper the Director pinned to a class/subject supplies them as the
     * fallback.
     *
     * @param  Collection<int, float>|array<int, float>  $marks  keyed by student id
     * @param  array<int, string>  $remarks  keyed by student id
     * @return int how many marks were written
     */
    public static function saveSheet(
        Examination $examination,
        Institution $institution,
        $marks,
        array $remarks = [],
        ?int $recordedBy = null,
        ?int $classId = null,
        ?int $subjectId = null,
    ): int {
        $marks = collect($marks);

        $classId ??= $examination->class_id;
        $subjectId ??= $examination->subject_id;

        DB::transaction(function () use ($marks, $remarks, $examination, $institution, $recordedBy, $classId, $subjectId) {
            foreach ($marks as $studentId => $value) {
                Result::updateOrCreate(
                    ['examination_id' => $examination->id, 'student_id' => $studentId, 'subject_id' => $subjectId],
                    [
                        'institution_id' => $institution->id,
                        'class_id' => $classId,
                        'marks_obtained' => $value,
                        'grade' => self::grade($examination, (float) $value, $institution->id, $classId),
                        'remarks' => ($remarks[$studentId] ?? null) ?: null,
                        'recorded_by' => $recordedBy,
                    ]
                );
            }
        });

        self::refreshReportCards($examination, $marks->keys());

        return $marks->count();
    }

    private static function grade(?Examination $examination, float $marks, int $institutionId, $classId): ?string
    {
        if (! $examination) {
            return null;
        }

        $institution = Institution::find($institutionId);

        if (! $institution) {
            return null;
        }

        return ResultGrader::grade($examination, $marks, $institution, SchoolClass::find($classId));
    }

    /**
     * A saved mark can complete a student's set for the term, which is what
     * makes their report card ready to send.
     */
    private static function refreshReportCard(Result $result): void
    {
        $result->loadMissing(['student', 'examination']);

        if (! $result->examination?->term || ! $result->examination?->academic_year || ! $result->student) {
            return;
        }

        app(ReportCardCompletionService::class)->handle(
            $result->student,
            $result->examination->term,
            $result->examination->academic_year,
        );
    }

    /**
     * @param  Collection<int, int>  $studentIds
     */
    private static function refreshReportCards(Examination $examination, Collection $studentIds): void
    {
        if (! $examination->term || ! $examination->academic_year) {
            return;
        }

        $service = app(ReportCardCompletionService::class);

        Result::whereIn('student_id', $studentIds)
            ->where('examination_id', $examination->id)
            ->with('student')
            ->get()
            ->each(function (Result $result) use ($service, $examination) {
                if ($result->student) {
                    $service->handle($result->student, $examination->term, $examination->academic_year);
                }
            });
    }
}
