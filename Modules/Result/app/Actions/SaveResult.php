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
            'student_id' => 'required|exists:users,id',
            'examination_id' => 'required|exists:examinations,id',
            'marks_obtained' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
        ];
    }

    /**
     * Whether this student already has a mark for this examination - one
     * result per student per paper is the rule the unique index enforces,
     * and this is how each screen warns before hitting it.
     */
    public static function duplicateExists(int $examinationId, int $studentId, ?int $ignoringId = null): bool
    {
        return Result::where('examination_id', $examinationId)
            ->where('student_id', $studentId)
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

        $payload = [
            'institution_id' => $institutionId,
            'class_id' => $data['class_id'],
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
    ): int {
        $marks = collect($marks);

        DB::transaction(function () use ($marks, $remarks, $examination, $institution, $recordedBy) {
            foreach ($marks as $studentId => $value) {
                Result::updateOrCreate(
                    ['examination_id' => $examination->id, 'student_id' => $studentId],
                    [
                        'institution_id' => $institution->id,
                        'class_id' => $examination->class_id,
                        'marks_obtained' => $value,
                        'grade' => self::grade($examination, (float) $value, $institution->id, $examination->class_id),
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
