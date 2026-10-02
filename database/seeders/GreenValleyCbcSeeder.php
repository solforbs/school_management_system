<?php

namespace Database\Seeders;

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

class GreenValleyCbcSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::where('code', 'GVA001')->first()
            ?? Institution::where('name', 'like', '%Green Valley%')->first();

        if (! $institution) {
            return;
        }

        // 1. Create or retrieve CBC Curriculum
        $curriculum = Curriculum::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'name' => 'Competency-Based Curriculum (CBC)',
            ],
            [
                'system' => 'cbc',
                'status' => 'active',
            ]
        );

        if (! $curriculum->isCbc()) {
            $curriculum->update(['system' => 'cbc']);
        }

        // 2. Ensure 8-level grading bands exist for this curriculum
        foreach (ResultGrader::CBC_SUB_LEVELS as $code => $info) {
            GradingBand::updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'curriculum_id' => $curriculum->id,
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

        // Also add the 4 broad bands if not present
        $broadBands = [
            'EE' => ['min' => 75.0, 'max' => 100.0, 'points' => 8, 'remark' => 'Exceeding Expectations'],
            'ME' => ['min' => 41.0, 'max' => 74.99, 'points' => 6, 'remark' => 'Meeting Expectations'],
            'AE' => ['min' => 21.0, 'max' => 40.99, 'points' => 4, 'remark' => 'Approaching Expectations'],
            'BE' => ['min' => 1.0, 'max' => 20.99, 'points' => 2, 'remark' => 'Below Expectations'],
        ];
        foreach ($broadBands as $code => $info) {
            GradingBand::updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'curriculum_id' => $curriculum->id,
                    'grade' => $code,
                ],
                [
                    'min_percentage' => $info['min'],
                    'max_percentage' => $info['max'],
                    'points' => $info['points'],
                    'remark' => $info['remark'],
                ]
            );
        }

        // 3. Link Junior School classes to CBC curriculum
        $cbcClassNames = ['Grade 7 East', 'Grade 7 West', 'Grade 8 East', 'Grade 8 West', 'Grade 9 East'];
        $cbcClasses = collect();

        foreach ($cbcClassNames as $className) {
            $level = str_contains($className, '7') ? 'Grade 7' : (str_contains($className, '8') ? 'Grade 8' : 'Grade 9');
            $cls = SchoolClass::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'name' => $className,
                ],
                [
                    'level' => $level,
                    'capacity' => 35,
                    'curriculum_id' => $curriculum->id,
                ]
            );
            $cls->update(['curriculum_id' => $curriculum->id]);
            $cbcClasses->push($cls);
        }

        $grade7East = $cbcClasses->firstWhere('name', 'Grade 7 East');
        $grade8East = $cbcClasses->firstWhere('name', 'Grade 8 East');

        // 4. Find teacher1 (John Mwangi)
        $teacher = User::where('email', 'teacher1@greenvalley.ac.ke')->first()
            ?? User::role('Teacher')->where('active_institution_id', $institution->id)->first();

        if ($teacher) {
            $teacher->update(['active_institution_id' => $institution->id]);
        }

        // 5. Ensure core CBC subjects exist with learning areas & strands
        $subjectsData = [
            'Mathematics' => [
                'code' => 'MATH-CBC',
                'learning_area' => 'Mathematics & Logic',
                'strand' => 'Numbers and Algebra',
            ],
            'Integrated Science' => [
                'code' => 'ISCI-CBC',
                'learning_area' => 'Science and Technology',
                'strand' => 'Living Things and Ecology',
            ],
            'Pre-Technical Studies' => [
                'code' => 'PTS-CBC',
                'learning_area' => 'Technical & Pre-Career Education',
                'strand' => 'Materials, Tools and Safety',
            ],
            'English Language' => [
                'code' => 'ENG-CBC',
                'learning_area' => 'Languages & Communication',
                'strand' => 'Reading Fluency and Composition',
            ],
            'Kiswahili' => [
                'code' => 'KISW-CBC',
                'learning_area' => 'Languages & Communication',
                'strand' => 'Kusikiliza na Kuzungumza',
            ],
            'Creative Arts & Sports' => [
                'code' => 'CAS-CBC',
                'learning_area' => 'Arts and Sports Science',
                'strand' => 'Visual Arts and Physical Fitness',
            ],
            'Social Studies' => [
                'code' => 'SST-CBC',
                'learning_area' => 'Social Sciences',
                'strand' => 'Citizenship and Governance',
            ],
        ];

        $cbcSubjects = collect();

        foreach ($subjectsData as $name => $meta) {
            $subj = Subject::updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'name' => $name,
                ],
                [
                    'code' => $meta['code'],
                    'learning_area' => $meta['learning_area'],
                    'strand' => $meta['strand'],
                    'is_compulsory' => true,
                    'is_active' => true,
                ]
            );
            $cbcSubjects->put($name, $subj);

            // Assign teacher to this subject for Grade 7 East & Grade 8 East
            if ($teacher) {
                SubjectTeacher::firstOrCreate([
                    'institution_id' => $institution->id,
                    'class_id' => $grade7East->id,
                    'subject_id' => $subj->id,
                    'teacher_id' => $teacher->id,
                ]);

                SubjectTeacher::firstOrCreate([
                    'institution_id' => $institution->id,
                    'class_id' => $grade8East->id,
                    'subject_id' => $subj->id,
                    'teacher_id' => $teacher->id,
                ]);
            }
        }

        // Set class teacher
        if ($teacher) {
            $grade7East->update(['class_teacher_id' => $teacher->id]);
            $grade8East->update(['class_teacher_id' => $teacher->id]);
        }

        // 6. Ensure learners exist in Grade 7 East
        $studentDetails = StudentDetails::where('class_id', $grade7East->id)
            ->where('institution_id', $institution->id)
            ->get();

        if ($studentDetails->count() < 8) {
            $unassignedOrOther = StudentDetails::where('institution_id', $institution->id)
                ->take(12)
                ->get();

            foreach ($unassignedOrOther as $sd) {
                $sd->update([
                    'class_id' => $grade7East->id,
                    'enrollment_status' => 'active',
                ]);
            }

            $studentDetails = StudentDetails::where('class_id', $grade7East->id)
                ->where('institution_id', $institution->id)
                ->get();
        }

        // 7. Create CBC Continuous Assessment Sitting (Formative / SBA)
        $term = 'Term 1';
        $year = 2026;

        $sbaSitting = ExamSitting::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'title' => 'Grade 7 Continuous SBA & Practical Tasks',
                'term' => $term,
                'academic_year' => $year,
            ],
            [
                'assessment_category' => 'sba',
                'class_id' => $grade7East->id,
                'notes' => 'Internal CBC School-Based Assessment for Grade 7 Junior Secondary.',
            ]
        );

        // Pre-create examinations for subjects in this sitting
        $exams = collect();
        foreach ($cbcSubjects as $subjName => $subject) {
            $exam = Examination::firstOrCreate(
                [
                    'institution_id' => $institution->id,
                    'sitting_id' => $sbaSitting->id,
                    'class_id' => $grade7East->id,
                    'subject_id' => $subject->id,
                ],
                [
                    'title' => "Grade 7 {$subjName} SBA Task",
                    'subject_name' => $subjName,
                    'term' => $term,
                    'academic_year' => $year,
                    'exam_date' => now()->toDateString(),
                    'total_marks' => null,
                ]
            );
            $exams->push($exam);
        }

        // 8. Seed initial student 8-point bands for demonstrations
        $sampleBands = [
            'Mathematics' => ['EE1', 'EE2', 'ME1', 'EE1', 'ME2', 'EE2', 'ME1', 'AE1'],
            'Integrated Science' => ['EE1', 'EE2', 'ME1', 'EE2', 'ME1', 'EE1', 'ME2', 'ME1'],
            'Pre-Technical Studies' => ['EE2', 'EE1', 'ME1', 'EE1', 'ME1', 'EE2', 'ME2', 'AE1'],
            'English Language' => ['ME1', 'ME2', 'EE2', 'ME1', 'EE2', 'ME2', 'AE1', 'BE1'],
            'Kiswahili' => ['ME2', 'ME1', 'ME1', 'ME2', 'ME1', 'ME2', 'AE2', 'BE1'],
            'Creative Arts & Sports' => ['AE1', 'ME2', 'EE1', 'AE1', 'ME1', 'AE2', 'EE2', 'ME2'],
            'Social Studies' => ['ME1', 'ME1', 'ME2', 'ME1', 'ME2', 'ME1', 'AE1', 'BE1'],
        ];

        foreach ($exams as $exam) {
            $subjName = $exam->subject_name;
            $bandsList = $sampleBands[$subjName] ?? ['ME1', 'ME2', 'EE2'];

            foreach ($studentDetails->take(8)->values() as $idx => $student) {
                $band = $bandsList[$idx % count($bandsList)];
                Result::updateOrCreate(
                    [
                        'examination_id' => $exam->id,
                        'student_id' => $student->student_id,
                    ],
                    [
                        'institution_id' => $institution->id,
                        'class_id' => $grade7East->id,
                        'subject_id' => $exam->subject_id,
                        'marks_obtained' => null,
                        'cbc_band' => $band,
                        'grade' => $band,
                    ]
                );
            }
        }

        // 9. Generate Report Cards so teacher can preview / download them immediately
        try {
            app(ReportCardGenerationService::class)->forClass($grade7East, $term, $year);
        } catch (\Throwable) {
            // Keep going if DomPDF environment is skipped in CLI seeder
        }
    }
}
