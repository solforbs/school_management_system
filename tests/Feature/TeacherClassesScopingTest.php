<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Attendance\Models\Attendance;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Teacher\Models\TeacherDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeScopingFixture(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Adaptive Classes Academy '.uniqid(),
        'code' => 'ACA-'.uniqid(),
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

    // Teacher 1: Homeroom for Class A, Subject teacher for Class B
    $teacher = User::factory()->create([
        'active_institution_id' => $institution->id,
    ]);
    $teacher->assignRole('Teacher');

    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
    ]);

    // Class A (Homeroom of Teacher)
    $classA = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 East',
        'level' => 'Grade 7',
        'curriculum_id' => $curriculum->id,
        'class_teacher_id' => $teacher->id,
        'capacity' => 40,
    ]);

    // Class B (Taught class of Teacher)
    $classB = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 North',
        'level' => 'Grade 8',
        'curriculum_id' => $curriculum->id,
        'class_teacher_id' => null,
        'capacity' => 35,
    ]);

    // Class C (Unrelated class, not taught or supervised by Teacher)
    $classC = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 9 West',
        'level' => 'Grade 9',
        'curriculum_id' => $curriculum->id,
        'class_teacher_id' => null,
        'capacity' => 30,
    ]);

    // Subject
    $math = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'code' => 'MATH-7',
    ]);

    // SubjectTeacher allocation: Teacher teaches Math in Class B
    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'teacher_id' => $teacher->id,
        'class_id' => $classB->id,
        'subject_id' => $math->id,
    ]);

    // Students in Class A
    $studentUser1 = User::factory()->create(['active_institution_id' => $institution->id]);
    $studentUser1->assignRole('Student');
    StudentDetails::create([
        'student_id' => $studentUser1->id,
        'institution_id' => $institution->id,
        'class_id' => $classA->id,
        'admission_number' => 'ADM-A001',
        'gender' => 'male',
        'enrollment_status' => 'active',
        'is_active' => true,
    ]);

    // Student in Class B
    $studentUser2 = User::factory()->create(['active_institution_id' => $institution->id]);
    $studentUser2->assignRole('Student');
    StudentDetails::create([
        'student_id' => $studentUser2->id,
        'institution_id' => $institution->id,
        'class_id' => $classB->id,
        'admission_number' => 'ADM-B002',
        'gender' => 'female',
        'enrollment_status' => 'active',
        'is_active' => true,
    ]);

    // Student in Class C (unrelated)
    $studentUser3 = User::factory()->create(['active_institution_id' => $institution->id]);
    $studentUser3->assignRole('Student');
    StudentDetails::create([
        'student_id' => $studentUser3->id,
        'institution_id' => $institution->id,
        'class_id' => $classC->id,
        'admission_number' => 'ADM-C003',
        'gender' => 'male',
        'enrollment_status' => 'active',
        'is_active' => true,
    ]);

    // Attendance record for student 1 in Homeroom Class A
    Attendance::create([
        'institution_id' => $institution->id,
        'school_class_id' => $classA->id,
        'student_id' => $studentUser1->id,
        'date' => now()->toDateString(),
        'status' => 'present',
        'source' => 'manual',
        'recorded_by' => $teacher->id,
    ]);

    return compact('director', 'institution', 'teacher', 'classA', 'classB', 'classC', 'math');
}

test('director sees global administrative suite on classes.index', function () {
    $f = makeScopingFixture();

    Livewire::actingAs($f['director'])
        ->test('classes::index')
        ->assertSee('Classes & Streams')
        ->assertSee('3 Active Streams')
        ->assertSee('Manage academic levels, monitor classroom capacities, and assign class teachers.')
        ->assertSee('Add Class')
        ->assertSee('Grade 7 East')
        ->assertSee('Grade 8 North')
        ->assertSee('Grade 9 West');
});

test('teacher sees adaptive my classes workspace scoped to assigned classes', function () {
    $f = makeScopingFixture();

    Livewire::actingAs($f['teacher'])
        ->test('classes::index')
        ->assertSee('My Classes & Teaching Streams')
        ->assertSee('2 Assigned Streams')
        ->assertSee('Designated Homeroom Class Teacher')
        ->assertSee('Grade 7 East')
        ->assertSee('Grade 8 North')
        ->assertSee('Mathematics')
        ->assertSee('Roll-Call: 100% Attendance')
        // Class C is unrelated and should NOT appear in "My Classes" tab
        ->assertDontSee('Grade 9 West')
        ->assertDontSee('Manage academic levels, monitor classroom capacities, and assign class teachers.')
        ->assertDontSee('Add Class');
});

test('teacher can switch to all school streams tab to view general directory without administrative actions', function () {
    $f = makeScopingFixture();

    Livewire::actingAs($f['teacher'])
        ->test('classes::index')
        ->set('tab', 'all')
        ->assertSee('All School Streams Directory')
        ->assertSee('Grade 7 East')
        ->assertSee('Grade 8 North')
        ->assertSee('Grade 9 West')
        // Edit / Delete actions remain hidden from teachers
        ->assertDontSee('Add Class')
        ->assertDontSee('Edit Class');
});

test('classes.show renders homeroom supervisor alert for designated class teacher', function () {
    $f = makeScopingFixture();

    $this->actingAs($f['teacher'])
        ->get(route('classes.show', $f['classA']->id))
        ->assertOk()
        ->assertSee('Your Homeroom Stream')
        ->assertSee('Supervising Teacher')
        ->assertSee('Take Roll-Call')
        ->assertSee('Learners Roster');
});

test('classes.show renders subject teaching stream alert for subject instructor', function () {
    $f = makeScopingFixture();

    $this->actingAs($f['teacher'])
        ->get(route('classes.show', $f['classB']->id))
        ->assertOk()
        ->assertSee('Subject Teaching Stream')
        ->assertSee('Subject Instructor')
        ->assertSee('Mathematics')
        ->assertSee('Record Assessment');
});
