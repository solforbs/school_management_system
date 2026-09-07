<?php

namespace Modules\Institution\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;

class GreenValleyAcademicHistorySeeder extends Seeder
{
    private const ACADEMIC_TERMS = [
        ['slug' => '2024-T1', 'title' => '2024 Term 1 End-Term Exam', 'year' => 2024, 'term_num' => 1, 'date' => '2024-03-29'],
        ['slug' => '2024-T2', 'title' => '2024 Term 2 End-Term Exam', 'year' => 2024, 'term_num' => 2, 'date' => '2024-07-26'],
        ['slug' => '2024-T3', 'title' => '2024 Term 3 End-Term Exam', 'year' => 2024, 'term_num' => 3, 'date' => '2024-11-22'],
        ['slug' => '2025-T1', 'title' => '2025 Term 1 End-Term Exam', 'year' => 2025, 'term_num' => 1, 'date' => '2025-03-28'],
        ['slug' => '2025-T2', 'title' => '2025 Term 2 End-Term Exam', 'year' => 2025, 'term_num' => 2, 'date' => '2025-07-25'],
        ['slug' => '2025-T3', 'title' => '2025 Term 3 End-Term Exam', 'year' => 2025, 'term_num' => 3, 'date' => '2025-11-21'],
        ['slug' => '2026-T1', 'title' => '2026 Term 1 End-Term Exam', 'year' => 2026, 'term_num' => 1, 'date' => '2026-03-27'],
        ['slug' => '2026-T2', 'title' => '2026 Term 2 End-Term Exam', 'year' => 2026, 'term_num' => 2, 'date' => '2026-07-24'],
    ];

    private function calculateGrade(float $marks): string
    {
        if ($marks >= 80) {
            return 'A';
        }
        if ($marks >= 75) {
            return 'A-';
        }
        if ($marks >= 70) {
            return 'B+';
        }
        if ($marks >= 65) {
            return 'B';
        }
        if ($marks >= 60) {
            return 'B-';
        }
        if ($marks >= 55) {
            return 'C+';
        }
        if ($marks >= 50) {
            return 'C';
        }
        if ($marks >= 45) {
            return 'C-';
        }
        if ($marks >= 40) {
            return 'D+';
        }
        if ($marks >= 35) {
            return 'D';
        }
        if ($marks >= 30) {
            return 'D-';
        }

        return 'E';
    }

    public function run(): void
    {
        $institution = Institution::where('code', 'GVA001')->first();

        if (! $institution) {
            return;
        }

        $classes = SchoolClass::where('institution_id', $institution->id)->get();
        $subjects = Subject::where('institution_id', $institution->id)->take(2)->get();
        $students = StudentDetails::where('institution_id', $institution->id)->get(['id', 'student_id', 'class_id', 'admission_number']);

        if ($classes->isEmpty() || $subjects->isEmpty() || $students->isEmpty()) {
            return;
        }

        foreach (self::ACADEMIC_TERMS as $term) {
            $examInserts = [];
            $createdAt = now()->parse($term['date']);

            foreach ($classes as $class) {
                foreach ($subjects as $subject) {
                    $examInserts[] = [
                        'institution_id' => $institution->id,
                        'title' => "{$term['title']} - {$subject->name}",
                        'subject_name' => $subject->name,
                        'class_name' => $class->name,
                        'class_id' => $class->id,
                        'subject_id' => $subject->id,
                        'academic_year' => $term['year'],
                        'exam_type' => 'end_term',
                        'term' => $term['slug'],
                        'exam_date' => $term['date'],
                        'start_time' => '08:30:00',
                        'end_time' => '10:30:00',
                        'total_marks' => 100,
                        'passing_marks' => 40,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
            }

            DB::table('examinations')->insert($examInserts);

            $termExams = DB::table('examinations')
                ->where('institution_id', $institution->id)
                ->where('term', $term['slug'])
                ->get(['id', 'class_id']);

            $resultsToInsert = [];

            foreach ($termExams as $exam) {
                $classStudents = $students->where('class_id', (string) $exam->class_id);

                foreach ($classStudents as $student) {
                    // Skip results if exam term year is prior to student admission year
                    $studentAdmYear = (int) (explode('/', $student->admission_number)[1] ?? 2024);
                    if ($term['year'] < $studentAdmYear) {
                        continue;
                    }

                    $marks = (float) rand(48, 95);

                    $resultsToInsert[] = [
                        'institution_id' => $institution->id,
                        'class_id' => $exam->class_id,
                        'student_id' => $student->student_id,
                        'examination_id' => $exam->id,
                        'marks_obtained' => $marks,
                        'grade' => $this->calculateGrade($marks),
                        'remarks' => 'Good performance',
                        'recorded_by' => $institution->user_id,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
            }

            foreach (array_chunk($resultsToInsert, 500) as $chunk) {
                DB::table('results')->insertOrIgnore($chunk);
            }
            $resultsToInsert = [];

            $reportCardsToInsert = [];

            foreach ($students as $student) {
                $studentAdmYear = (int) (explode('/', $student->admission_number)[1] ?? 2024);
                if ($term['year'] < $studentAdmYear) {
                    continue;
                }

                $meanScore = (float) rand(55, 92);

                $reportCardsToInsert[] = [
                    'institution_id' => $institution->id,
                    'student_id' => $student->student_id,
                    'class_id' => (int) $student->class_id,
                    'term' => $term['slug'],
                    'academic_year' => $term['year'],
                    'term_number' => $term['term_num'],
                    'mean_percentage' => $meanScore,
                    'mean_grade' => $this->calculateGrade($meanScore),
                    'status' => 'sent',
                    'pdf_path' => null,
                    'download_token' => Str::random(40),
                    'completed_at' => $term['date'],
                    'sent_at' => $term['date'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }

            foreach (array_chunk($reportCardsToInsert, 500) as $chunk) {
                DB::table('report_cards')->insertOrIgnore($chunk);
            }
            $reportCardsToInsert = [];
        }
    }
}
