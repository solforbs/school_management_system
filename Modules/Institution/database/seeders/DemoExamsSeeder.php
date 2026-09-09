<?php

namespace Modules\Institution\Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\ExamSitting;
use Modules\Examinations\Support\PaperSchedule;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;

/**
 * The demo school's examinations: a finished term with report cards already
 * out, and the current term part-way through - an opening exam and a mid
 * term done, the end term still ahead. Built with bulk inserts.
 */
class DemoExamsSeeder extends Seeder
{
    /** @var list<array{title: string, type: string, term: string, year_offset: int, month: int, graded: bool, reported: bool}> */
    private const SITTINGS = [
        ['title' => 'End Term Exam', 'type' => 'end_term', 'term' => 'Term 3', 'year_offset' => -1, 'month' => 11, 'graded' => true, 'reported' => true],
        ['title' => 'Opening Exam', 'type' => 'opening', 'term' => 'Term 1', 'year_offset' => 0, 'month' => 2, 'graded' => true, 'reported' => false],
        ['title' => 'Mid Term Exam', 'type' => 'mid_term', 'term' => 'Term 1', 'year_offset' => 0, 'month' => 3, 'graded' => true, 'reported' => true],
        ['title' => 'End Term Exam', 'type' => 'end_term', 'term' => 'Term 1', 'year_offset' => 0, 'month' => 4, 'graded' => false, 'reported' => false],
    ];

    public function run(): void
    {
        $institution = DemoAccountSeeder::institution();

        if (! $institution) {
            return;
        }

        $classes = SchoolClass::where('institution_id', $institution->id)->orderBy('id')->get();
        $students = StudentDetails::where('institution_id', $institution->id)->get(['student_id', 'class_id']);
        $subjects = Subject::where('institution_id', $institution->id)
            ->orderByDesc('is_compulsory')->orderBy('id')->get();
        $gradedSubjectIds = $subjects->take(10)->pluck('id')->all();

        if ($classes->isEmpty() || $students->isEmpty() || $subjects->isEmpty()) {
            return;
        }

        foreach (self::SITTINGS as $spec) {
            $startsOn = now()->addYears($spec['year_offset'])->month($spec['month'])->day(10);

            $sitting = ExamSitting::create([
                'institution_id' => $institution->id,
                'title' => $spec['title'],
                'term' => $spec['term'],
                'exam_type' => $spec['type'],
                'academic_year' => $startsOn->year,
            ]);

            $paperIds = $this->papers($institution, $sitting, $classes, $subjects, $startsOn);

            if ($spec['graded']) {
                $this->marks($institution, $paperIds, $students, $gradedSubjectIds);
            }

            if ($spec['reported']) {
                $this->reportCards($institution, $students, $spec, $startsOn);
            }
        }
    }

    /**
     * One paper per class per subject, spread across the exam week.
     *
     * @param  Collection<int, SchoolClass>  $classes
     * @param  Collection<int, Subject>  $subjects
     * @return array<int, array{class_id: int, subject_id: int, total_marks: int}> keyed by paper id
     */
    private function papers($institution, ExamSitting $sitting, $classes, $subjects, CarbonInterface $startsOn): array
    {
        $now = now();
        $rows = [];

        foreach ($subjects->values() as $slot => $subject) {
            $s = PaperSchedule::slot($startsOn, $slot);

            foreach ($classes as $class) {
                $rows[] = [
                    'sitting_id' => $sitting->id,
                    'institution_id' => $institution->id,
                    'class_id' => $class->id,
                    'subject_id' => $subject->id,
                    'title' => $subject->name.' — '.$sitting->title,
                    'subject_name' => $subject->name,
                    'class_name' => $class->name,
                    'term' => $sitting->term,
                    'exam_type' => $sitting->exam_type,
                    'academic_year' => $sitting->academic_year,
                    'exam_date' => $s['date']->toDateString(),
                    'start_time' => $s['start'],
                    'end_time' => $s['end'],
                    'total_marks' => $s['marks'],
                    'passing_marks' => $s['passing_marks'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('examinations')->insert($chunk);
        }

        return DB::table('examinations')
            ->where('sitting_id', $sitting->id)
            ->get(['id', 'class_id', 'subject_id', 'total_marks'])
            ->mapWithKeys(fn ($p) => [$p->id => [
                'class_id' => (int) $p->class_id,
                'subject_id' => (int) $p->subject_id,
                'total_marks' => (int) $p->total_marks,
            ]])
            ->all();

    }

    /**
     * @param  array<int, array{class_id: int, subject_id: int, total_marks: int}>  $paperIds
     * @param  Collection<int, StudentDetails>  $students
     * @param  list<int>  $gradedSubjectIds
     */
    private function marks($institution, array $paperIds, $students, array $gradedSubjectIds): void
    {
        $now = now();
        $byClass = $students->groupBy(fn (StudentDetails $d) => (int) $d->class_id);
        $rows = [];

        foreach ($paperIds as $paperId => $paper) {
            if (! in_array($paper['subject_id'], $gradedSubjectIds, true)) {
                continue;
            }

            foreach ($byClass->get($paper['class_id'], collect()) as $detail) {
                $marks = rand(30, 96);
                $pct = $paper['total_marks'] > 0 ? $marks / $paper['total_marks'] * 100 : 0;

                $rows[] = [
                    'institution_id' => $institution->id,
                    'class_id' => $paper['class_id'],
                    'subject_id' => $paper['subject_id'],
                    'student_id' => $detail->student_id,
                    'examination_id' => $paperId,
                    'marks_obtained' => $marks,
                    'grade' => DemoAccountSeeder::gradeForPercentage($pct),
                    'recorded_by' => $institution->user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('results')->insertOrIgnore($chunk);
        }
    }

    /**
     * @param  Collection<int, StudentDetails>  $students
     * @param  array<string, mixed>  $spec
     */
    private function reportCards($institution, $students, array $spec, CarbonInterface $issuedOn): void
    {
        $termNumber = (int) filter_var($spec['term'], FILTER_SANITIZE_NUMBER_INT) ?: 1;
        $rows = [];

        foreach ($students as $detail) {
            $mean = (float) rand(52, 90);

            $rows[] = [
                'institution_id' => $institution->id,
                'student_id' => $detail->student_id,
                'class_id' => (int) $detail->class_id,
                'term' => $spec['term'],
                'academic_year' => $issuedOn->year,
                'term_number' => $termNumber,
                'mean_percentage' => $mean,
                'mean_grade' => DemoAccountSeeder::gradeForPercentage($mean),
                'status' => 'sent',
                'download_token' => Str::random(40),
                'completed_at' => $issuedOn->copy()->addDays(5),
                'sent_at' => $issuedOn->copy()->addDays(6),
                'created_at' => $issuedOn,
                'updated_at' => $issuedOn,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('report_cards')->insertOrIgnore($chunk);
        }
    }
}
