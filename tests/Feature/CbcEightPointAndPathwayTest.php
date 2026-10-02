<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Curriculum\Services\CbcPathwayService;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Models\ExamSitting;
use Modules\Examinations\Services\CbcCumulativeSbaService;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardPdfService;
use Modules\ReportCard\Support\SubjectRow;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultGrader;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function createCbcTestEnvironment(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'KNEC Junior Academy '.uniqid(),
        'code' => 'KNEC-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    $curriculum = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC Junior Secondary',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    foreach (ResultGrader::CBC_SUB_LEVELS as $code => $info) {
        GradingBand::create([
            'institution_id' => $institution->id,
            'curriculum_id' => $curriculum->id,
            'min_percentage' => $info['min_pct'],
            'max_percentage' => $info['max_pct'],
            'grade' => $code,
            'points' => $info['points'],
            'remark' => $info['label'],
        ]);
    }

    return [$director, $institution, $curriculum];
}

test('it maps percentages to KNEC eight-level achievement scale accurately', function () {
    expect(ResultGrader::subLevelForPercentage(95.0))->toBe('EE1')
        ->and(ResultGrader::subLevelForPercentage(80.0))->toBe('EE2')
        ->and(ResultGrader::subLevelForPercentage(65.0))->toBe('ME1')
        ->and(ResultGrader::subLevelForPercentage(50.0))->toBe('ME2')
        ->and(ResultGrader::subLevelForPercentage(35.0))->toBe('AE1')
        ->and(ResultGrader::subLevelForPercentage(25.0))->toBe('AE2')
        ->and(ResultGrader::subLevelForPercentage(15.0))->toBe('BE1')
        ->and(ResultGrader::subLevelForPercentage(5.0))->toBe('BE2');

    expect(ResultGrader::pointsFor('EE1'))->toBe(8)
        ->and(ResultGrader::pointsFor('ME1'))->toBe(6)
        ->and(ResultGrader::pointsFor('BE2'))->toBe(1)
        ->and(ResultGrader::parentBand('EE1'))->toBe('EE')
        ->and(ResultGrader::parentBand('ME2'))->toBe('ME')
        ->and(ResultGrader::parentBand('BE1'))->toBe('BE');
});

test('it evaluates senior school career pathways based on learner achievement', function () {
    $service = new CbcPathwayService;

    $rows = collect([
        new SubjectRow(
            name: 'Mathematics',
            code: 'MAT',
            marks: null,
            outOf: null,
            percentage: null,
            band: null,
            teacherInitials: 'T.M',
            directBand: 'EE1', // 8 pts
        ),
        new SubjectRow(
            name: 'Integrated Science',
            code: 'SCI',
            marks: null,
            outOf: null,
            percentage: null,
            band: null,
            teacherInitials: 'T.S',
            directBand: 'EE2', // 7 pts
        ),
        new SubjectRow(
            name: 'Pre-Technical Studies',
            code: 'PTS',
            marks: null,
            outOf: null,
            percentage: null,
            band: null,
            teacherInitials: 'T.P',
            directBand: 'EE1', // 8 pts
        ),
        new SubjectRow(
            name: 'English',
            code: 'ENG',
            marks: null,
            outOf: null,
            percentage: null,
            band: null,
            teacherInitials: 'T.E',
            directBand: 'ME2', // 5 pts
        ),
        new SubjectRow(
            name: 'Kiswahili',
            code: 'KIS',
            marks: null,
            outOf: null,
            percentage: null,
            band: null,
            teacherInitials: 'T.K',
            directBand: 'ME1', // 6 pts
        ),
        new SubjectRow(
            name: 'Creative Arts and Sports',
            code: 'CAS',
            marks: null,
            outOf: null,
            percentage: null,
            band: null,
            teacherInitials: 'T.C',
            directBand: 'AE1', // 4 pts
        ),
    ]);

    $evaluation = $service->evaluate($rows);

    expect($evaluation['has_recommendations'])->toBeTrue()
        ->and($evaluation['primary']['id'])->toBe(CbcPathwayService::PATHWAY_STEM)
        ->and($evaluation['primary']['average_points'])->toBe(7.67)
        ->and($evaluation['primary']['readiness_level'])->toBe('high')
        ->and($evaluation['secondary']['id'])->toBe(CbcPathwayService::PATHWAY_SOCIAL_SCIENCES)
        ->and($evaluation['secondary']['average_points'])->toBe(5.5)
        ->and($evaluation['secondary']['readiness_level'])->toBe('medium');

    $stemStrengths = collect($evaluation['primary']['strengths'])->pluck('subject')->all();
    expect($stemStrengths)->toContain('Mathematics', 'Integrated Science', 'Pre-Technical Studies');
    expect($evaluation['statement'])->toContain('STEM');
});

test('it computes multi-year cumulative SBA (60%) and KJSEA summative (40%) scores', function () {
    $sbaService = new CbcCumulativeSbaService;

    // G7 SBA: 80% (contributes 16.0 / 20)
    // G8 SBA: 75% (contributes 15.0 / 20)
    // G9 SBA: 70% (contributes 14.0 / 20)
    // KJSEA Summative: 85% (contributes 34.0 / 40)
    // Composite: 16 + 15 + 14 + 34 = 79.0% => EE2 (Very Good, 7 pts, Band EE)
    $result = $sbaService->computeCumulativeScore(
        g7Pct: 80.0,
        g8Pct: 75.0,
        g9SbaPct: 70.0,
        summativePct: 85.0
    );

    expect($result['g7_weighted'])->toBe(16.0)
        ->and($result['g8_weighted'])->toBe(15.0)
        ->and($result['g9_sba_weighted'])->toBe(14.0)
        ->and($result['total_sba_weighted'])->toBe(45.0)
        ->and($result['summative_weighted'])->toBe(34.0)
        ->and($result['composite_percentage'])->toBe(79.0)
        ->and($result['sub_level'])->toBe('EE2')
        ->and($result['band'])->toBe('EE')
        ->and($result['points'])->toBe(7);
});

test('it builds student multi-year cumulative ledger from historical examinations', function () {
    [$director, $institution, $curriculum] = createCbcTestEnvironment();

    $student = User::factory()->create();
    $student->assignRole('Student');

    $grade7Class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 East',
        'level' => 'Grade 7',
        'curriculum_id' => $curriculum->id,
    ]);

    $grade8Class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 East',
        'level' => 'Grade 8',
        'curriculum_id' => $curriculum->id,
    ]);

    $grade9Class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 9 East',
        'level' => 'Grade 9',
        'curriculum_id' => $curriculum->id,
    ]);

    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'class_id' => $grade9Class->id,
        'admission_number' => 'ADM-'.rand(1000, 9999),
        'student_number' => 'UPI-'.rand(10000, 99999),
    ]);

    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Integrated Science',
        'code' => 'SCI-789',
        'learning_area' => 'Science and Technology',
    ]);

    // Grade 7 SBA Sitting & Paper
    $g7Sitting = ExamSitting::create([
        'institution_id' => $institution->id,
        'title' => 'Grade 7 KNEC SBA',
        'assessment_category' => 'sba',
        'academic_year' => 2024,
    ]);
    $g7Exam = Examination::create([
        'institution_id' => $institution->id,
        'title' => 'Grade 7 Science Paper',
        'sitting_id' => $g7Sitting->id,
        'class_id' => $grade7Class->id,
        'subject_id' => $subject->id,
        'subject_name' => $subject->name,
        'exam_date' => '2024-03-20',
        'total_marks' => 100,
    ]);
    Result::create([
        'institution_id' => $institution->id,
        'examination_id' => $g7Exam->id,
        'student_id' => $student->id,
        'marks_obtained' => 80,
    ]);

    // Grade 8 SBA Sitting & Paper
    $g8Sitting = ExamSitting::create([
        'institution_id' => $institution->id,
        'title' => 'Grade 8 KNEC SBA',
        'assessment_category' => 'sba',
        'academic_year' => 2025,
    ]);
    $g8Exam = Examination::create([
        'institution_id' => $institution->id,
        'title' => 'Grade 8 Science Paper',
        'sitting_id' => $g8Sitting->id,
        'class_id' => $grade8Class->id,
        'subject_id' => $subject->id,
        'subject_name' => $subject->name,
        'exam_date' => '2025-03-20',
        'total_marks' => 100,
    ]);
    Result::create([
        'institution_id' => $institution->id,
        'examination_id' => $g8Exam->id,
        'student_id' => $student->id,
        'marks_obtained' => 70,
    ]);

    // Grade 9 SBA Sitting & Paper
    $g9SbaSitting = ExamSitting::create([
        'institution_id' => $institution->id,
        'title' => 'Grade 9 Internal SBA',
        'assessment_category' => 'sba',
        'academic_year' => 2026,
    ]);
    $g9SbaExam = Examination::create([
        'institution_id' => $institution->id,
        'title' => 'Grade 9 Science SBA Paper',
        'sitting_id' => $g9SbaSitting->id,
        'class_id' => $grade9Class->id,
        'subject_id' => $subject->id,
        'subject_name' => $subject->name,
        'exam_date' => '2026-03-20',
        'total_marks' => 100,
    ]);
    Result::create([
        'institution_id' => $institution->id,
        'examination_id' => $g9SbaExam->id,
        'student_id' => $student->id,
        'marks_obtained' => 75,
    ]);

    // Grade 9 KJSEA Summative Sitting & Paper
    $kjseaSitting = ExamSitting::create([
        'institution_id' => $institution->id,
        'title' => 'Grade 9 KJSEA National Exam',
        'assessment_category' => 'summative',
        'academic_year' => 2026,
    ]);
    $kjseaExam = Examination::create([
        'institution_id' => $institution->id,
        'title' => 'Grade 9 KJSEA Science National Paper',
        'sitting_id' => $kjseaSitting->id,
        'class_id' => $grade9Class->id,
        'subject_id' => $subject->id,
        'subject_name' => $subject->name,
        'exam_date' => '2026-10-20',
        'total_marks' => 100,
    ]);
    Result::create([
        'institution_id' => $institution->id,
        'examination_id' => $kjseaExam->id,
        'student_id' => $student->id,
        'marks_obtained' => 90,
    ]);

    $service = new CbcCumulativeSbaService;
    $ledger = $service->getStudentCumulativeLedger($student->id, $institution->id);

    expect($ledger)->not->toBeEmpty();
    $science = $ledger->first();
    expect($science['subject_name'])->toBe('Integrated Science');

    $cum = $science['cumulative'];
    // G7: 80% * 0.20 = 16.0
    // G8: 70% * 0.20 = 14.0
    // G9 SBA: 75% * 0.20 = 15.0
    // Total SBA: 45.0
    // KJSEA: 90% * 0.40 = 36.0
    // Composite: 81.0% -> EE2
    expect($cum['g7_weighted'])->toBe(16.0)
        ->and($cum['g8_weighted'])->toBe(14.0)
        ->and($cum['g9_sba_weighted'])->toBe(15.0)
        ->and($cum['total_sba_weighted'])->toBe(45.0)
        ->and($cum['summative_weighted'])->toBe(36.0)
        ->and($cum['composite_percentage'])->toBe(81.0)
        ->and($cum['sub_level'])->toBe('EE2');
});

test('it renders cbc report card pdf with senior school pathway recommendations', function () {
    [$director, $institution, $curriculum] = createCbcTestEnvironment();

    $student = User::factory()->create();
    $student->assignRole('Student');

    $schoolClass = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 Blue',
        'level' => 'Grade 8',
        'curriculum_id' => $curriculum->id,
    ]);

    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'class_id' => $schoolClass->id,
        'admission_number' => 'ADM-'.rand(1000, 9999),
    ]);

    $subject1 = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'code' => 'MAT-8',
        'learning_area' => 'STEM',
    ]);
    $subject2 = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'English Language',
        'code' => 'ENG-8',
        'learning_area' => 'Languages',
    ]);

    $sitting = ExamSitting::create([
        'institution_id' => $institution->id,
        'title' => 'Term 1 Assessment',
        'term' => 'Term 1',
        'academic_year' => 2026,
        'assessment_category' => 'classroom_assessment',
    ]);

    $exam1 = Examination::create([
        'institution_id' => $institution->id,
        'title' => 'Grade 8 Mathematics',
        'sitting_id' => $sitting->id,
        'class_id' => $schoolClass->id,
        'subject_id' => $subject1->id,
        'subject_name' => $subject1->name,
        'term' => 'Term 1',
        'academic_year' => 2026,
        'exam_date' => '2026-03-20',
        'total_marks' => null,
    ]);

    $exam2 = Examination::create([
        'institution_id' => $institution->id,
        'title' => 'Grade 8 English Language',
        'sitting_id' => $sitting->id,
        'class_id' => $schoolClass->id,
        'subject_id' => $subject2->id,
        'subject_name' => $subject2->name,
        'term' => 'Term 1',
        'academic_year' => 2026,
        'exam_date' => '2026-03-21',
        'total_marks' => null,
    ]);

    Result::create([
        'institution_id' => $institution->id,
        'examination_id' => $exam1->id,
        'student_id' => $student->id,
        'cbc_band' => 'EE1',
        'grade' => 'EE1',
    ]);

    Result::create([
        'institution_id' => $institution->id,
        'examination_id' => $exam2->id,
        'student_id' => $student->id,
        'cbc_band' => 'ME1',
        'grade' => 'ME1',
    ]);

    $reportCard = ReportCard::create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'class_id' => $schoolClass->id,
        'term' => 'Term 1',
        'academic_year' => 2026,
        'status' => 'ready',
        'completed_at' => now(),
    ]);

    $pdfService = new ReportCardPdfService;
    $path = $pdfService->generate($reportCard);

    expect($path)->toBe("report-cards/{$reportCard->id}.pdf")
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

test('teacher has permission to access formative assessments, band entry, and report cards', function () {
    [$director, $institution, $curriculum] = createCbcTestEnvironment();

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    $teacher->update(['active_institution_id' => $institution->id]);

    $this->actingAs($teacher);

    $this->get(route('assessment.formative'))
        ->assertOk();

    $this->get(route('reportcard.index'))
        ->assertOk();
});
