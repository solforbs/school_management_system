<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Models\ExamSitting;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportSheetBuilder;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultGrader;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Actions\SaveSubject;
use Modules\Subject\Models\Subject;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function createCbcSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'CBC Academy '.uniqid(),
        'code' => 'CBC-'.uniqid(),
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

    // Setup 4 expectation bands
    GradingBand::create([
        'institution_id' => $institution->id,
        'curriculum_id' => $curriculum->id,
        'min_percentage' => 80,
        'max_percentage' => 100,
        'grade' => 'EE',
        'points' => 4,
        'remark' => 'Exceeding Expectations',
    ]);
    GradingBand::create([
        'institution_id' => $institution->id,
        'curriculum_id' => $curriculum->id,
        'min_percentage' => 50,
        'max_percentage' => 79.99,
        'grade' => 'ME',
        'points' => 3,
        'remark' => 'Meeting Expectations',
    ]);
    GradingBand::create([
        'institution_id' => $institution->id,
        'curriculum_id' => $curriculum->id,
        'min_percentage' => 30,
        'max_percentage' => 49.99,
        'grade' => 'AE',
        'points' => 2,
        'remark' => 'Approaching Expectations',
    ]);
    GradingBand::create([
        'institution_id' => $institution->id,
        'curriculum_id' => $curriculum->id,
        'min_percentage' => 0,
        'max_percentage' => 29.99,
        'grade' => 'BE',
        'points' => 1,
        'remark' => 'Below Expectations',
    ]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'curriculum_id' => $curriculum->id,
        'name' => 'Grade 7 East',
        'level' => 'Grade 7',
        'status' => 'active',
    ]);

    return [$director->refresh(), $institution, $curriculum, $class];
}

test('subject can be created and updated with CBC learning area and strand', function () {
    [$director, $institution, $curriculum, $class] = createCbcSchool();

    $subject = SaveSubject::handle([
        'name' => 'Integrated Science',
        'code' => 'SCI-701',
        'learning_area' => 'Science and Technology',
        'strand' => 'Living Things',
        'sub_strand' => 'Micro-organisms',
        'is_compulsory' => true,
    ], $institution->id);

    expect($subject->name)->toBe('Integrated Science')
        ->and($subject->learning_area)->toBe('Science and Technology')
        ->and($subject->strand)->toBe('Living Things')
        ->and($subject->sub_strand)->toBe('Micro-organisms')
        ->and($subject->hasCbcStructure())->toBeTrue();

    // Update with new strand
    $updated = SaveSubject::handle([
        'name' => 'Integrated Science',
        'code' => 'SCI-701',
        'learning_area' => 'Science and Technology',
        'strand' => 'Matter',
        'sub_strand' => 'States of Matter',
        'is_compulsory' => true,
    ], $institution->id, $subject);

    expect($updated->strand)->toBe('Matter')
        ->and($updated->sub_strand)->toBe('States of Matter');
});

test('formative assessment controller creates lightweight sitting and examination', function () {
    [$director, $institution, $curriculum, $class] = createCbcSchool();

    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'learning_area' => 'Mathematics',
        'strand' => 'Numbers',
    ]);

    $this->actingAs($director);

    $response = $this->post(route('assessment.formative.store'), [
        'title' => 'Fractions Project Task',
        'assessment_category' => 'classroom_assessment',
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'term' => 'Term 1',
        'academic_year' => 2026,
        'assessment_date' => '2026-03-15',
        'notes' => 'Manipulative materials project on fractions.',
    ]);

    $sitting = ExamSitting::where('title', 'Fractions Project Task')->first();
    expect($sitting)->not->toBeNull()
        ->and($sitting->assessment_category)->toBe('classroom_assessment')
        ->and($sitting->isFormative())->toBeTrue()
        ->and($sitting->isClassroomAssessment())->toBeTrue()
        ->and($sitting->isSummative())->toBeFalse();

    $exam = Examination::where('sitting_id', $sitting->id)->first();
    expect($exam)->not->toBeNull()
        ->and($exam->total_marks)->toBeNull()
        ->and($exam->isCbcFormative())->toBeTrue();

    $response->assertRedirect(route('assessment.band.entry', [
        'examination_id' => $exam->id,
        'class_id' => $class->id,
    ]));
});

test('result grader fromBand generates valid direct CBC band payload', function () {
    $payload = ResultGrader::fromBand('ME');

    expect($payload['marks_obtained'])->toBeNull()
        ->and($payload['cbc_band'])->toBe('ME')
        ->and($payload['grade'])->toBe('ME');
});

test('band entry controller stores bands for students on formative tasks', function () {
    [$director, $institution, $curriculum, $class] = createCbcSchool();

    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');

    $student = StudentDetails::create([
        'student_id' => $studentUser->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-001',
    ]);

    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'English',
        'is_active' => true,
    ]);

    $sitting = ExamSitting::create([
        'institution_id' => $institution->id,
        'title' => 'Reading Fluency Task',
        'assessment_category' => 'sba',
        'class_id' => $class->id,
        'academic_year' => 2026,
        'term' => 'Term 1',
    ]);

    $exam = Examination::create([
        'institution_id' => $institution->id,
        'sitting_id' => $sitting->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'title' => 'Reading Fluency Task',
        'subject_name' => $subject->name,
        'class_name' => $class->name,
        'term' => 'Term 1',
        'academic_year' => 2026,
        'exam_date' => '2026-03-20',
        'total_marks' => null,
    ]);

    $this->actingAs($director);

    $response = $this->post(route('assessment.band.entry.store'), [
        'examination_id' => $exam->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'bands' => [
            $student->student_id => 'EE',
        ],
        'remarks' => [
            $student->student_id => 'Outstanding reading expression.',
        ],
    ]);

    $response->assertRedirect(route('assessment.band.entry', [
        'examination_id' => $exam->id,
        'class_id' => $class->id,
    ]));

    $result = Result::where('examination_id', $exam->id)
        ->where('student_id', $student->student_id)
        ->first();

    expect($result)->not->toBeNull()
        ->and($result->cbc_band)->toBe('EE')
        ->and($result->grade)->toBe('EE')
        ->and($result->marks_obtained)->toBeNull()
        ->and($result->isDirectBand())->toBeTrue()
        ->and($result->remarks)->toBe('Outstanding reading expression.');
});

test('band entry controller clears and deletes results when a student is unchecked or submitted empty', function () {
    [$director, $institution, $curriculum, $class] = createCbcSchool();

    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');

    $student = StudentDetails::create([
        'student_id' => $studentUser->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-CLEAR-001',
    ]);

    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Science',
        'is_active' => true,
    ]);

    $sitting = ExamSitting::create([
        'institution_id' => $institution->id,
        'title' => 'Science SBA',
        'assessment_category' => 'sba',
        'class_id' => $class->id,
        'academic_year' => 2026,
        'term' => 'Term 1',
    ]);

    $exam = Examination::create([
        'institution_id' => $institution->id,
        'sitting_id' => $sitting->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'title' => 'Science SBA',
        'subject_name' => $subject->name,
        'class_name' => $class->name,
        'term' => 'Term 1',
        'academic_year' => 2026,
        'exam_date' => '2026-03-20',
        'total_marks' => null,
    ]);

    // First, existing result in DB (teacher checked ME1 by mistake earlier)
    Result::create([
        'institution_id' => $institution->id,
        'examination_id' => $exam->id,
        'student_id' => $student->student_id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'cbc_band' => 'ME1',
        'grade' => 'ME1',
        'marks_obtained' => null,
    ]);

    expect(Result::where('examination_id', $exam->id)->count())->toBe(1);

    $this->actingAs($director);

    // Now teacher unchecks it, submitting empty string
    $response = $this->post(route('assessment.band.entry.store'), [
        'examination_id' => $exam->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'bands' => [
            $student->student_id => '',
        ],
    ]);

    $response->assertRedirect(route('assessment.band.entry', [
        'examination_id' => $exam->id,
        'class_id' => $class->id,
    ]));

    $response->assertSessionHas('success', '1 evaluation(s) cleared successfully.');

    // Result must be deleted from DB
    $remaining = Result::where('examination_id', $exam->id)->where('student_id', $student->student_id)->first();
    expect($remaining)->toBeNull();
});

test('report sheet builder groups CBC results and determines expectation band', function () {
    [$director, $institution, $curriculum, $class] = createCbcSchool();

    $studentUser = User::factory()->create();
    $studentUser->assignRole('Student');

    $student = StudentDetails::create([
        'student_id' => $studentUser->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-CBC',
    ]);

    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Creative Arts',
        'code' => 'ART-101',
        'learning_area' => 'Creative Arts and Sports',
        'strand' => 'Visual Arts',
        'is_active' => true,
    ]);

    $sitting = ExamSitting::create([
        'institution_id' => $institution->id,
        'title' => 'Drawing Assessment',
        'assessment_category' => 'classroom_assessment',
        'class_id' => $class->id,
        'academic_year' => 2026,
        'term' => 'Term 1',
    ]);

    $exam = Examination::create([
        'institution_id' => $institution->id,
        'sitting_id' => $sitting->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'title' => 'Drawing Assessment',
        'subject_name' => $subject->name,
        'class_name' => $class->name,
        'term' => 'Term 1',
        'academic_year' => 2026,
        'exam_date' => '2026-03-25',
        'total_marks' => null,
    ]);

    Result::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'student_id' => $student->student_id,
        'examination_id' => $exam->id,
        'cbc_band' => 'ME',
        'grade' => 'ME',
        'marks_obtained' => null,
    ]);

    $reportCard = ReportCard::create([
        'institution_id' => $institution->id,
        'student_id' => $student->student_id,
        'class_id' => $class->id,
        'term' => 'Term 1',
        'academic_year' => 2026,
        'status' => 'ready',
        'completed_at' => now(),
    ]);

    $builder = new ReportSheetBuilder;
    $rows = $builder->rows($reportCard, $institution);

    expect($rows)->toHaveCount(1);
    $row = $rows->first();

    expect($row->name)->toBe('Creative Arts')
        ->and($row->isAssessed())->toBeTrue()
        ->and($row->expectationBand())->toBe('ME')
        ->and($row->grade())->toBe('ME')
        ->and($row->marks)->toBeNull()
        ->and($row->percentage)->toBeNull();

    $summary = $builder->summary($rows, $institution, $curriculum->id);
    expect($summary['subjects_assessed'])->toBe(1)
        ->and($summary['band'])->not->toBeNull()
        ->and($summary['band']->grade)->toBe('ME');
});
