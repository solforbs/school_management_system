<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

/**
 * Smoke coverage for the module screens converted to Livewire: each one
 * renders, filters in place, and saves without a page reload.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeLivewireDirector(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Livewire School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

test('the livewire student form enrols a student with a login and a photo', function () {
    Storage::fake('public');

    [$director, $institution] = makeLivewireDirector();

    Livewire::actingAs($director)
        ->test('student::form')
        ->set('name', 'Amina Yusuf')
        ->set('email', 'amina@example.com')
        ->set('password', 'password123')
        ->set('profile_image', UploadedFile::fake()->image('amina.jpg'))
        ->set('admission_number', 'ADM-100')
        ->set('gender', 'female')
        ->set('parent_name', 'Yusuf Ali')
        ->set('parent_phone', '0700000001')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $student = User::where('email', 'amina@example.com')->firstOrFail();
    $details = StudentDetails::where('student_id', $student->id)->firstOrFail();

    expect($student->hasRole('Student'))->toBeTrue()
        ->and($details->institution_id)->toBe($institution->id)
        ->and($details->admission_number)->toBe('ADM-100')
        ->and($details->profile_photo)->not->toBeNull()
        // A parent account was created alongside and linked.
        ->and($details->parent_id)->not->toBeNull()
        ->and(User::find($details->parent_id)->hasRole('Parent'))->toBeTrue();
});

test('the livewire student form refuses an incomplete enrolment', function () {
    [$director] = makeLivewireDirector();

    Livewire::actingAs($director)
        ->test('student::form')
        ->set('name', '')
        ->set('email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['name', 'email', 'password', 'profile_image']);

    expect(StudentDetails::count())->toBe(0);
});

test('the livewire student list searches and deletes in place', function () {
    [$director, $institution] = makeLivewireDirector();

    $student = User::factory()->create(['name' => 'Brian Otieno']);
    $student->assignRole('Student');
    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'admission_number' => 'ADM-200',
        'enrollment_status' => 'active',
        'is_active' => true,
    ]);

    Livewire::actingAs($director)
        ->test('student::index')
        ->assertSee('Brian Otieno')
        ->set('search', 'zzz-nobody')
        ->assertDontSee('Brian Otieno')
        ->set('search', 'ADM-200')
        ->assertSee('Brian Otieno')
        ->call('delete', $student->id)
        ->assertHasNoErrors();

    expect(StudentDetails::count())->toBe(0)
        ->and(User::find($student->id))->toBeNull();
});

test('a student from another school is out of reach of the livewire form', function () {
    [$director] = makeLivewireDirector();
    [, $otherSchool] = makeLivewireDirector();

    $student = User::factory()->create();
    $student->assignRole('Student');
    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $otherSchool->id,
        'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
    ]);

    Livewire::actingAs($director)
        ->test('student::form', ['studentId' => $student->id])
        ->assertForbidden();
});

/**
 * Every module screen that became a Livewire page has to still render for a
 * Director. This is the net that catches a component that no longer
 * resolves, a route pointing at the wrong name, or a view referencing
 * something the class no longer provides.
 */
test('every converted module screen renders', function (string $route) {
    [$director] = makeLivewireDirector();

    $this->actingAs($director)->get(route($route))->assertOk();
})->with([
    'expenditure.index',
    'expenditure.create',
    'expenditure.categories.index',
    'result.index',
    'result.create',
    'result.entry.create',
    'subject.index',
    'subject.create',
    'subject.teachers.index',
    'classes.index',
    'classes.create',
    'curriculum.index',
    'curriculum.create',
    'examinations.index',
    'examinations.create',
    'examinations.timetable',
    'student.index',
    'student.create',
    'feemanagement.index',
    'feemanagement.create',
    'teacher.index',
    'teacher.create',
    'parent.index',
    'parent.create',
    'staff.index',
    'staff.create',
    'staff.payments.index',
    'staff.payments.create',
    'timetable.index',
    'timetable.create',
    'attendance.index',
    'lesson.index',
    'lesson.reports.index',
    'institution.index',
    'institution.create',
    'reportcard.index',
    'reportcard.settings',
    'report.index',
]);

test('the student subject picker renders for a student', function () {
    [, $institution] = makeLivewireDirector();

    $student = User::factory()->create();
    $student->assignRole('Student');
    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
    ]);

    $this->actingAs($student->refresh())->get(route('selections.index'))->assertOk();
});
