<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardGenerationService;
use Modules\ReportCard\Support\GradingScaleDefaults;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Teacher\Models\TeacherDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('public');
});

function setupBulkPrintSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Green Valley Academy '.uniqid(),
        'code' => 'GVA-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    $curriculum = Curriculum::firstOrCreate(
        ['code' => 'CBC'],
        ['name' => 'Competency Based Curriculum', 'is_active' => true]
    );

    $teacher = User::factory()->create(['name' => 'Teacher John Mwangi']);
    $teacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 East',
        'level' => 'Grade 7',
        'curriculum_id' => $curriculum->id,
        'class_teacher_id' => $teacher->id,
    ]);

    foreach (GradingScaleDefaults::cbc() as $band) {
        GradingBand::create($band + ['institution_id' => $institution->id, 'curriculum_id' => $curriculum->id]);
    }

    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'code' => 'MATH-7',
        'is_active' => true,
    ]);

    $exam = Examination::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'title' => 'Opener Formative Assessment',
        'term' => 'Term 1',
        'academic_year' => 2026,
        'exam_date' => '2026-01-10',
    ]);

    // Create 2 learners in this class with CBC bands
    $learner1 = User::factory()->create(['name' => 'Alice Wanjiku']);
    $learner1->assignRole('Student');
    StudentDetails::create([
        'student_id' => $learner1->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'enrollment_status' => 'active',
        'admission_number' => 'GVA-001',
    ]);

    Result::create([
        'institution_id' => $institution->id,
        'student_id' => $learner1->id,
        'examination_id' => $exam->id,
        'subject_id' => $subject->id,
        'cbc_band' => 'EE1',
    ]);

    $learner2 = User::factory()->create(['name' => 'Brian Kiprono']);
    $learner2->assignRole('Student');
    StudentDetails::create([
        'student_id' => $learner2->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'enrollment_status' => 'active',
        'admission_number' => 'GVA-002',
    ]);

    Result::create([
        'institution_id' => $institution->id,
        'student_id' => $learner2->id,
        'examination_id' => $exam->id,
        'subject_id' => $subject->id,
        'cbc_band' => 'ME1',
    ]);

    return compact('director', 'institution', 'curriculum', 'teacher', 'class', 'subject', 'exam', 'learner1', 'learner2');
}

test('class bundle PDF streams multi-page PDF for entire class to teacher', function () {
    $data = setupBulkPrintSchool();

    $response = $this->actingAs($data['teacher'])
        ->get(route('reportcard.class.bulk-pdf', [
            'schoolClass' => $data['class']->id,
            'term' => 'Term 1',
            'academic_year' => 2026,
        ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('grade-7-east-term-1-report-cards.pdf');
});

test('class bundle PDF auto-generates missing report cards and streams PDF', function () {
    $data = setupBulkPrintSchool();

    // Verify 0 report cards exist initially
    expect(ReportCard::count())->toBe(0);

    $response = $this->actingAs($data['director'])
        ->get(route('reportcard.class.bulk-pdf', [
            'schoolClass' => $data['class']->id,
            'term' => 'Term 1',
            'academic_year' => 2026,
        ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');

    // Report cards should now be generated for both students
    expect(ReportCard::where('class_id', $data['class']->id)->count())->toBe(2);
});

test('teacher from another institution cannot bulk print report cards', function () {
    $data = setupBulkPrintSchool();

    $otherInstitution = Institution::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Other Academy',
        'code' => 'OTH-01',
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $foreignTeacher = User::factory()->create();
    $foreignTeacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $foreignTeacher->id,
        'institution_id' => $otherInstitution->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($foreignTeacher)
        ->get(route('reportcard.class.bulk-pdf', [
            'schoolClass' => $data['class']->id,
            'term' => 'Term 1',
            'academic_year' => 2026,
        ]));

    $response->assertForbidden();
});

test('livewire reportcard index shows class bundle print shortcut', function () {
    $data = setupBulkPrintSchool();

    // Pre-generate report cards
    app(ReportCardGenerationService::class)
        ->forClass($data['class'], 'Term 1', 2026);

    // As Homeroom Teacher
    Livewire::actingAs($data['teacher'])
        ->test('reportcard::index')
        ->assertSee('Print Grade 7 East Bundle')
        ->set('class_id', (string) $data['class']->id)
        ->assertSee('Print Class Bundle (PDF)')
        ->assertSee('Print Entire Class Report Cards (PDF)');
});

test('classes index shows print report cards button in homeroom spotlight', function () {
    $data = setupBulkPrintSchool();

    Livewire::actingAs($data['teacher'])
        ->test('classes::index')
        ->assertSee('Print Report Cards');
});
