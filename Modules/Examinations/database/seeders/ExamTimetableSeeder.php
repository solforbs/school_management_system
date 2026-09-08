<?php

namespace Modules\Examinations\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Actions\SaveExamSitting;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Models\ExamSitting;
use Modules\Examinations\Support\PaperSchedule;
use Modules\Institution\Models\Institution;

/**
 * One end-of-term examination sitting for every institution, fanned out
 * into a paper per subject per class so the exam timetable and the mark
 * sheets have something realistic to render.
 *
 * Runs against a live database: it discovers the real institutions,
 * classes and subjects, and every write is idempotent, so re-running it
 * shifts the same sitting onto the next week rather than piling up
 * duplicates.
 */
class ExamTimetableSeeder extends Seeder
{
    private const TERM = 'Term 2';

    /** A key of {@see Examination::EXAM_TYPES}. */
    private const EXAM_TYPE = 'end_term';

    private const TITLE = 'End Term Exam';

    public function run(): void
    {
        $year = PaperSchedule::nextExamWeek()->year;
        $sittingCount = 0;
        $paperCount = 0;

        foreach (Institution::query()->orderBy('id')->get() as $institution) {
            if (! SchoolClass::where('institution_id', $institution->id)->exists()) {
                continue;
            }

            // Keyed on the sitting's identity so a second run reuses it.
            $sitting = ExamSitting::firstOrNew([
                'institution_id' => $institution->id,
                'term' => self::TERM,
                'exam_type' => self::EXAM_TYPE,
                'academic_year' => $year,
                'class_id' => null,
            ]);
            $sitting->title = self::TITLE;
            $sitting->save();

            SaveExamSitting::handle([
                'title' => self::TITLE,
                'term' => self::TERM,
                'exam_type' => self::EXAM_TYPE,
                'academic_year' => $year,
                'class_id' => null,
            ], $institution->id, $sitting);

            $sittingCount++;
            $paperCount += $sitting->papers()->count();
        }

        $this->command?->info(sprintf(
            'Exam timetable seeded: %d sitting(s), %d paper(s) for %s %d.',
            $sittingCount,
            $paperCount,
            self::TITLE,
            $year,
        ));
    }
}
