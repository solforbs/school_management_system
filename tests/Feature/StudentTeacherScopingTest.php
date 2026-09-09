<?php

use App\Models\TeacherClasses;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Teacher\Models\TeacherDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeScopingSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Scoping Academy '.uniqid(),
        'code' => 'SCOP-'.uniqid(),
        'type' => 'Primary & Secondary',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function makeScopingTeacher(Institution $institution): User
{
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    return $teacher->refresh();
}

function makeScopingStudent(Institution $institution, SchoolClass $class, string $name): User
{
    $student = User::factory()->create(['name' => $name]);
    $student->assignRole('Student');

    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
        'is_active' => true,
    ]);

    return $student->refresh();
}

test('teacher sees only students in classes assigned to them on the livewire directory', function () {
    [$director, $institution] = makeScopingSchool();
    $teacher = makeScopingTeacher($institution);

    $classAssigned = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 3 East',
    ]);

    $classUnassigned = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Form 4 West',
    ]);

    // Assign teacher to Grade 3 East via SubjectTeacher
    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'code' => 'MATH',
    ]);

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'teacher_id' => $teacher->id,
        'class_id' => $classAssigned->id,
        'subject_id' => $subject->id,
    ]);

    $studentAssigned = makeScopingStudent($institution, $classAssigned, 'Alice Assigned');
    $studentUnassigned = makeScopingStudent($institution, $classUnassigned, 'Bob Unassigned');

    Livewire::actingAs($teacher)
        ->test('student::index')
        ->assertSee('Alice Assigned')
        ->assertDontSee('Bob Unassigned')
        ->assertSee('Grade 3 East')
        ->assertDontSee('Form 4 West');
});

test('teacher class filter dropdown only contains assigned classes', function () {
    [$director, $institution] = makeScopingSchool();
    $teacher = makeScopingTeacher($institution);

    $classAssigned = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 5 North',
        'class_teacher_id' => $teacher->id, // Assigned as class teacher
    ]);

    $classUnassigned = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 South',
    ]);

    $component = Livewire::actingAs($teacher)->test('student::index');

    $classes = $component->get('classes');
    expect($classes->pluck('id')->all())->toContain($classAssigned->id)
        ->and($classes->pluck('id')->all())->not->toContain($classUnassigned->id);
});

test('teacher export only includes students from their assigned classes', function () {
    [$director, $institution] = makeScopingSchool();
    $teacher = makeScopingTeacher($institution);

    $classAssigned = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 6 West',
    ]);

    $classUnassigned = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 East',
    ]);

    TeacherClasses::create([
        'teacher_id' => $teacher->id,
        'class_id' => $classAssigned->id,
    ]);

    makeScopingStudent($institution, $classAssigned, 'Charlie InClass');
    makeScopingStudent($institution, $classUnassigned, 'Diana OtherClass');

    // Without permission, teacher cannot export
    $this->actingAs($teacher)->get(route('student.export', ['format' => 'xlsx']))
        ->assertForbidden();

    // With permission, export succeeds and is scoped
    $teacher->givePermissionTo('export student');

    $response = $this->actingAs($teacher)->get(route('student.export', ['format' => 'xlsx']));
    $response->assertSuccessful();
});

test('teacher can view dossier of student in their class but is forbidden for unassigned class', function () {
    [$director, $institution] = makeScopingSchool();
    $teacher = makeScopingTeacher($institution);

    $classAssigned = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Form 1 Alpha',
        'class_teacher_id' => $teacher->id,
    ]);

    $classUnassigned = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Form 1 Beta',
    ]);

    $studentAssigned = makeScopingStudent($institution, $classAssigned, 'Edward Assigned');
    $studentUnassigned = makeScopingStudent($institution, $classUnassigned, 'Fiona Unassigned');

    $this->actingAs($teacher)
        ->get(route('student.show', $studentAssigned->id))
        ->assertSuccessful()
        ->assertSee('Edward Assigned');

    $this->actingAs($teacher)
        ->get(route('student.show', $studentUnassigned->id))
        ->assertForbidden();
});
