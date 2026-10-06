<?php

namespace Database\Seeders;

use App\Models\TeacherClasses;
use App\Models\TeacherSubjects;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Models\ExamSitting;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Services\ReportCardGenerationService;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultGrader;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Models\TimetableEntry;

class GreenValleyAcademicsSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = Institution::where('name', 'like', '%Green Valley%')
            ->orWhereIn('id', [1, 7])
            ->get();

        foreach ($institutions as $institution) {
            $this->seedInstitution($institution);
        }
    }

    private function seedInstitution(Institution $institution): void
    {

        // 1. Curriculum: 100% Competency-Based Curriculum (CBC)
        $cbcCurriculum = Curriculum::where('institution_id', $institution->id)
            ->where('system', 'cbc')
            ->first()
            ?? Curriculum::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'name' => 'Competency-Based Curriculum (CBC)',
                ],
                [
                    'system' => 'cbc',
                    'status' => 'active',
                ]
            );

        if (! $cbcCurriculum->isCbc()) {
            $cbcCurriculum->update(['system' => 'cbc']);
        }

        // Set all junior secondary and primary classes at this institution to CBC
        SchoolClass::where('institution_id', $institution->id)
            ->where(function ($q) {
                $q->where('name', 'like', 'Grade%')
                    ->orWhere('level', 'like', 'Grade%')
                    ->orWhere('level', 'like', '%Junior%')
                    ->orWhere('level', 'like', '%Primary%');
            })
            ->update(['curriculum_id' => $cbcCurriculum->id]);

        // CBC 8-level grading bands
        foreach (ResultGrader::CBC_SUB_LEVELS as $code => $info) {
            GradingBand::updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'curriculum_id' => $cbcCurriculum->id,
                    'grade' => $code,
                ],
                [
                    'min_percentage' => $info['min_pct'],
                    'max_percentage' => $info['max_pct'],
                    'points' => $info['points'],
                    'remark' => $info['label'],
                ]
            );
        }

        // 2. Identify Teacher John Mwangi
        $teacher = User::where('email', 'teacher1@greenvalley.ac.ke')->first()
            ?? User::where('name', 'like', '%John Mwangi%')->first();

        if ($teacher) {
            $teacher->update(['active_institution_id' => $institution->id]);
        }

        // 3. CBC Classes Setup
        $grade7East = SchoolClass::updateOrCreate(
            [
                'institution_id' => $institution->id,
                'name' => 'Grade 7 East',
            ],
            [
                'level' => 'Grade 7',
                'capacity' => 40,
                'curriculum_id' => $cbcCurriculum->id,
                'class_teacher_id' => $teacher?->id,
            ]
        );

        $grade8East = SchoolClass::updateOrCreate(
            [
                'institution_id' => $institution->id,
                'name' => 'Grade 8 East',
            ],
            [
                'level' => 'Grade 8',
                'capacity' => 40,
                'curriculum_id' => $cbcCurriculum->id,
            ]
        );

        $grade9East = SchoolClass::updateOrCreate(
            [
                'institution_id' => $institution->id,
                'name' => 'Grade 9 East',
            ],
            [
                'level' => 'Grade 9',
                'capacity' => 40,
                'curriculum_id' => $cbcCurriculum->id,
            ]
        );

        // 4. Clean up any legacy 8-4-4 class allocations for Teacher John Mwangi
        if ($teacher) {
            $legacyClasses = SchoolClass::where('institution_id', $institution->id)
                ->where(function ($q) {
                    $q->where('name', 'like', 'Form%')
                        ->orWhere('level', 'like', 'Form%')
                        ->orWhere('level', 'Secondary');
                })
                ->pluck('id');

            SubjectTeacher::where('teacher_id', $teacher->id)
                ->whereIn('class_id', $legacyClasses)
                ->delete();

            TeacherClasses::where('teacher_id', $teacher->id)
                ->whereIn('class_id', $legacyClasses)
                ->delete();

            // Reassign any learners previously in Form classes over to CBC Grade 8 or Grade 9
            StudentDetails::where('institution_id', $institution->id)
                ->whereIn('class_id', $legacyClasses)
                ->update(['class_id' => $grade8East->id]);
        }

        // 5. CBC Subjects for Institution 1
        $subjectsData = [
            'Mathematics' => [
                'code' => 'MATH-CBC',
                'learning_area' => 'Mathematics & Logic',
                'strand' => 'Numbers and Algebra',
                'compulsory' => true,
            ],
            'Integrated Science' => [
                'code' => 'ISCI-CBC',
                'learning_area' => 'Science and Technology',
                'strand' => 'Living Things and Ecology',
                'compulsory' => true,
            ],
            'English Language' => [
                'code' => 'ENG-CBC',
                'learning_area' => 'Languages & Communication',
                'strand' => 'Reading Fluency and Composition',
                'compulsory' => true,
            ],
            'Kiswahili' => [
                'code' => 'KISW-CBC',
                'learning_area' => 'Languages & Communication',
                'strand' => 'Ufahamu na Mawasiliano',
                'compulsory' => true,
            ],
            'Social Studies' => [
                'code' => 'SST-CBC',
                'learning_area' => 'Social Sciences',
                'strand' => 'Citizenship and Governance',
                'compulsory' => true,
            ],
            'Agriculture & Nutrition' => [
                'code' => 'AGRI',
                'learning_area' => 'Agriculture and Food Systems',
                'strand' => 'Crop Production and Conservation',
                'compulsory' => true,
            ],
            'Creative Arts & Sports' => [
                'code' => 'CAS-CBC',
                'learning_area' => 'Arts and Sports Science',
                'strand' => 'Visual Arts and Physical Fitness',
                'compulsory' => true,
            ],
            'Pre-Technical Studies' => [
                'code' => 'PTS-CBC',
                'learning_area' => 'Technical & Pre-Career Education',
                'strand' => 'Materials, Tools and Safety',
                'compulsory' => true,
            ],
            'Religious Education' => [
                'code' => 'CRE',
                'learning_area' => 'Social Sciences & Ethics',
                'strand' => 'Values and Moral Education',
                'compulsory' => true,
            ],
            'Computer Science' => [
                'code' => 'COMP',
                'learning_area' => 'Science and Technology',
                'strand' => 'Digital Skills and Logic',
                'compulsory' => false,
            ],
        ];

        $subjectModels = [];
        foreach ($subjectsData as $name => $meta) {
            $s = Subject::updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'name' => $name,
                ],
                [
                    'code' => $meta['code'],
                    'learning_area' => $meta['learning_area'],
                    'strand' => $meta['strand'],
                    'is_compulsory' => $meta['compulsory'],
                    'is_active' => true,
                ]
            );
            $subjectModels[$name] = $s;
        }

        // 6. Allocate Teacher John Mwangi to CBC Classes & Subjects
        if ($teacher) {
            $allocations = [
                ['class' => $grade7East, 'subject' => $subjectModels['Mathematics']],
                ['class' => $grade7East, 'subject' => $subjectModels['Integrated Science']],
                ['class' => $grade8East, 'subject' => $subjectModels['Mathematics']],
                ['class' => $grade9East, 'subject' => $subjectModels['Mathematics']],
            ];

            foreach ($allocations as $alloc) {
                SubjectTeacher::updateOrCreate(
                    [
                        'institution_id' => $institution->id,
                        'class_id' => $alloc['class']->id,
                        'subject_id' => $alloc['subject']->id,
                        'teacher_id' => $teacher->id,
                    ]
                );

                TeacherSubjects::firstOrCreate([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $alloc['subject']->id,
                ]);

                TeacherClasses::firstOrCreate([
                    'teacher_id' => $teacher->id,
                    'class_id' => $alloc['class']->id,
                ]);
            }
        }

        // 7. Ensure learners are in Grade 7 East and Grade 8 East
        $grade7Learners = StudentDetails::where('class_id', $grade7East->id)->get();
        if ($grade7Learners->count() < 20) {
            $existing = StudentDetails::where('institution_id', $institution->id)->take(25)->get();
            foreach ($existing as $l) {
                $l->update(['class_id' => $grade7East->id]);
            }
        }

        $grade8Learners = StudentDetails::where('class_id', $grade8East->id)->get();
        if ($grade8Learners->count() < 15) {
            $existingG8 = StudentDetails::where('institution_id', $institution->id)
                ->where('class_id', '!=', $grade7East->id)
                ->take(20)
                ->get();
            foreach ($existingG8 as $l) {
                $l->update(['class_id' => $grade8East->id]);
            }
        }

        // 8. Seed 100% CBC Timetable for John Mwangi
        if ($teacher) {
            TimetableEntry::where('teacher_id', $teacher->id)->delete();

            $schedule = [
                ['day' => 'Monday', 'start' => '08:00', 'end' => '08:40', 'class' => $grade7East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 7A'],
                ['day' => 'Monday', 'start' => '09:20', 'end' => '10:00', 'class' => $grade7East, 'subj' => $subjectModels['Integrated Science'], 'room' => 'Science Lab 1'],
                ['day' => 'Monday', 'start' => '11:20', 'end' => '12:00', 'class' => $grade8East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 8A'],

                ['day' => 'Tuesday', 'start' => '08:40', 'end' => '09:20', 'class' => $grade8East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 8A'],
                ['day' => 'Tuesday', 'start' => '10:20', 'end' => '11:00', 'class' => $grade7East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 7A'],
                ['day' => 'Tuesday', 'start' => '14:00', 'end' => '14:40', 'class' => $grade7East, 'subj' => $subjectModels['Integrated Science'], 'room' => 'Science Lab 1'],

                ['day' => 'Wednesday', 'start' => '08:00', 'end' => '08:40', 'class' => $grade7East, 'subj' => $subjectModels['Integrated Science'], 'room' => 'Science Lab 1'],
                ['day' => 'Wednesday', 'start' => '09:20', 'end' => '10:00', 'class' => $grade8East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 8A'],
                ['day' => 'Wednesday', 'start' => '11:20', 'end' => '12:00', 'class' => $grade9East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 9A'],

                ['day' => 'Thursday', 'start' => '08:40', 'end' => '09:20', 'class' => $grade7East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 7A'],
                ['day' => 'Thursday', 'start' => '10:20', 'end' => '11:00', 'class' => $grade8East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 8A'],
                ['day' => 'Thursday', 'start' => '14:00', 'end' => '14:40', 'class' => $grade9East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 9A'],

                ['day' => 'Friday', 'start' => '08:00', 'end' => '08:40', 'class' => $grade7East, 'subj' => $subjectModels['Integrated Science'], 'room' => 'Science Lab 1'],
                ['day' => 'Friday', 'start' => '09:20', 'end' => '10:00', 'class' => $grade7East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 7A'],
                ['day' => 'Friday', 'start' => '11:20', 'end' => '12:00', 'class' => $grade8East, 'subj' => $subjectModels['Mathematics'], 'room' => 'Room 8A'],
            ];

            foreach ($schedule as $s) {
                TimetableEntry::create([
                    'institution_id' => $institution->id,
                    'class_id' => $s['class']->id,
                    'class_name' => $s['class']->name,
                    'teacher_id' => $teacher->id,
                    'subject_id' => $s['subj']->id,
                    'subject' => $s['subj']->name,
                    'day_of_week' => $s['day'],
                    'start_time' => $s['start'],
                    'end_time' => $s['end'],
                    'room' => $s['room'],
                ]);
            }
        }

        // 9. CBC Formative & SBA Assessment Sittings
        $term = 'Term 1';
        $year = 2026;

        // Grade 7 Formative Sitting
        $g7FormativeSitting = ExamSitting::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'title' => 'Grade 7 Continuous Classroom Assessment',
                'term' => $term,
                'academic_year' => $year,
            ],
            [
                'assessment_category' => 'classroom_assessment',
                'class_id' => $grade7East->id,
                'notes' => 'Continuous formative rubric evaluation based on KNEC guidelines.',
            ]
        );

        // Grade 7 SBA Sitting
        $g7SbaSitting = ExamSitting::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'title' => 'Grade 7 School-Based Assessment (SBA)',
                'term' => $term,
                'academic_year' => $year,
            ],
            [
                'assessment_category' => 'sba',
                'class_id' => $grade7East->id,
                'notes' => 'Junior School summative SBA for portal compilation.',
            ]
        );

        // Grade 8 Formative Sitting
        $g8FormativeSitting = ExamSitting::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'title' => 'Grade 8 Continuous Classroom Assessment',
                'term' => $term,
                'academic_year' => $year,
            ],
            [
                'assessment_category' => 'classroom_assessment',
                'class_id' => $grade8East->id,
                'notes' => 'Grade 8 continuous formative rubric evaluation.',
            ]
        );

        // Grade 9 KJSEA Preparation Assessment Sitting
        $g9SbaSitting = ExamSitting::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'title' => 'Grade 9 KJSEA Mock School-Based Assessment',
                'term' => $term,
                'academic_year' => $year,
            ],
            [
                'assessment_category' => 'sba',
                'class_id' => $grade9East->id,
                'notes' => 'Grade 9 national summative preparation assessment.',
            ]
        );

        // 10. CBC Examination Papers
        $examMathG7 = Examination::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'sitting_id' => $g7FormativeSitting->id,
                'class_id' => $grade7East->id,
                'subject_id' => $subjectModels['Mathematics']->id,
            ],
            [
                'title' => 'Grade 7 Mathematics - Algebra & Number Systems',
                'subject_name' => 'Mathematics',
                'term' => $term,
                'academic_year' => $year,
                'exam_date' => now()->subDays(2)->toDateString(),
            ]
        );

        $examSciG7 = Examination::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'sitting_id' => $g7FormativeSitting->id,
                'class_id' => $grade7East->id,
                'subject_id' => $subjectModels['Integrated Science']->id,
            ],
            [
                'title' => 'Grade 7 Integrated Science - Ecology & Systems',
                'subject_name' => 'Integrated Science',
                'term' => $term,
                'academic_year' => $year,
                'exam_date' => now()->subDay()->toDateString(),
            ]
        );

        $examMathG8 = Examination::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'sitting_id' => $g8FormativeSitting->id,
                'class_id' => $grade8East->id,
                'subject_id' => $subjectModels['Mathematics']->id,
            ],
            [
                'title' => 'Grade 8 Mathematics - Proportions & Measurements',
                'subject_name' => 'Mathematics',
                'term' => $term,
                'academic_year' => $year,
                'exam_date' => now()->subDays(3)->toDateString(),
            ]
        );

        $examMathG9 = Examination::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'sitting_id' => $g9SbaSitting->id,
                'class_id' => $grade9East->id,
                'subject_id' => $subjectModels['Mathematics']->id,
            ],
            [
                'title' => 'Grade 9 Mathematics - KJSEA Formative Trial',
                'subject_name' => 'Mathematics',
                'term' => $term,
                'academic_year' => $year,
                'exam_date' => now()->subDays(4)->toDateString(),
            ]
        );

        // 11. CBC Assessment Results (Rubric Bands EE1–BE2)
        $cbcBandsPattern = ['EE1', 'EE2', 'ME1', 'ME2', 'EE1', 'ME1', 'EE2', 'AE1'];

        // Grade 7 Results
        $learnersG7 = StudentDetails::where('class_id', $grade7East->id)->get();
        foreach ($learnersG7 as $idx => $student) {
            $bandMath = $cbcBandsPattern[$idx % count($cbcBandsPattern)];
            $bandSci = $cbcBandsPattern[($idx + 2) % count($cbcBandsPattern)];

            Result::updateOrCreate(
                [
                    'examination_id' => $examMathG7->id,
                    'student_id' => $student->student_id,
                ],
                [
                    'institution_id' => $institution->id,
                    'class_id' => $grade7East->id,
                    'subject_id' => $subjectModels['Mathematics']->id,
                    'cbc_band' => $bandMath,
                    'grade' => $bandMath,
                ]
            );

            Result::updateOrCreate(
                [
                    'examination_id' => $examSciG7->id,
                    'student_id' => $student->student_id,
                ],
                [
                    'institution_id' => $institution->id,
                    'class_id' => $grade7East->id,
                    'subject_id' => $subjectModels['Integrated Science']->id,
                    'cbc_band' => $bandSci,
                    'grade' => $bandSci,
                ]
            );
        }

        // Grade 8 Results (CBC Rubric Bands)
        $learnersG8 = StudentDetails::where('class_id', $grade8East->id)->get();
        foreach ($learnersG8 as $idx => $student) {
            $bandMath = $cbcBandsPattern[($idx + 1) % count($cbcBandsPattern)];

            Result::updateOrCreate(
                [
                    'examination_id' => $examMathG8->id,
                    'student_id' => $student->student_id,
                ],
                [
                    'institution_id' => $institution->id,
                    'class_id' => $grade8East->id,
                    'subject_id' => $subjectModels['Mathematics']->id,
                    'cbc_band' => $bandMath,
                    'grade' => $bandMath,
                ]
            );
        }

        // 12. Clean up any obsolete Form 1 results or examinations
        $legacyExams = Examination::where('institution_id', $institution->id)
            ->where(function ($q) {
                $q->where('title', 'like', 'Form%')->orWhere('class_id', 145);
            })->pluck('id');

        if ($legacyExams->isNotEmpty()) {
            Result::whereIn('examination_id', $legacyExams)->delete();
            Examination::whereIn('id', $legacyExams)->delete();
        }

        // 13. Generate 100% CBC Report Cards for Grade 7 East
        try {
            app(ReportCardGenerationService::class)->forClass($grade7East, $term, $year);
        } catch (\Throwable $e) {
            // Ignore generation issues during seeder
        }
    }
}
