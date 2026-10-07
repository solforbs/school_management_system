<?php

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Actions\SaveStudent;
use Modules\Student\Models\StudentDetails;
use Modules\Teacher\Models\TeacherDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('public');
});

function makeEnrollmentSchool(): array
{
    $plan = Plan::create([
        'name' => 'Unlimited Plan '.uniqid(),
        'slug' => 'unlimited-'.uniqid(),
        'price' => 1000,
        'billing_cycle' => 'termly',
        'modules' => ['Student'],
        'features' => [],
        'max_students' => null,
        'is_active' => true,
    ]);

    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Enrollment Academy '.uniqid(),
        'code' => 'EA-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
        'subscription_plan' => $plan->id,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    $class = SchoolClass::create([
        'name' => 'Grade 1 East',
        'level' => 'Grade 1',
        'institution_id' => $institution->id,
    ]);

    return [$director->refresh(), $institution, $class];
}

test('a student can be enrolled with only name, auto-generating email, password, and admission number', function () {
    [$director, $institution, $class] = makeEnrollmentSchool();

    $response = $this->actingAs($director)->post(route('student.store'), [
        'name' => 'Minimal Student',
        'class_id' => $class->id,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('student.index'));

    $user = User::where('name', 'Minimal Student')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('Student'))->toBeTrue()
        ->and($user->email)->toContain('@students.generated');

    $details = StudentDetails::where('student_id', $user->id)->first();
    expect($details)->not->toBeNull()
        ->and($details->institution_id)->toBe($institution->id)
        ->and((int) $details->class_id)->toBe($class->id)
        ->and($details->admission_number)->toBeNull();
});

test('a student enrolled via bulk or minimal form can be edited later to add medical and guardian details', function () {
    [$director, $institution, $class] = makeEnrollmentSchool();

    // Student created minimally
    $saveStudent = app(SaveStudent::class);
    $student = $saveStudent->create([
        'name' => 'Alex Smith',
        'class_id' => $class->id,
    ], $institution->id);

    expect($student->studentUserDetails->medical_conditions)->toBeNull();

    // Now edit the student adding medical, notes, parent, and updated name
    $response = $this->actingAs($director)->put(route('student.update', $student->id), [
        'name' => 'Alex Alexander Smith',
        'email' => 'alex.smith@realdomain.com',
        'medical_conditions' => 'Asthma',
        'allergies' => 'Peanuts',
        'special_needs' => 'Requires inhaler during physical education',
        'parent_name' => 'Martha Smith',
        'parent_phone' => '0712345678',
        'notes' => 'Transferred from Hill School',
        'class_id' => $class->id,
    ]);

    $response->assertSessionHasNoErrors();

    $student->refresh();
    expect($student->name)->toBe('Alex Alexander Smith')
        ->and($student->email)->toBe('alex.smith@realdomain.com')
        ->and($student->studentUserDetails->medical_conditions)->toBe('Asthma')
        ->and($student->studentUserDetails->allergies)->toBe('Peanuts')
        ->and($student->studentUserDetails->special_needs)->toBe('Requires inhaler during physical education')
        ->and($student->studentUserDetails->notes)->toBe('Transferred from Hill School')
        ->and($student->studentUserDetails->parent_id)->not->toBeNull();
});

test('a teacher with can_enroll_students permission can enroll students via student.store', function () {
    [$director, $institution, $class] = makeEnrollmentSchool();

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    $teacher->update(['active_institution_id' => $institution->id]);

    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'can_enroll_students' => true,
    ]);

    $response = $this->actingAs($teacher)->post(route('student.store'), [
        'name' => 'Teacher Enrolled Student',
        'class_id' => $class->id,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('student.index'));

    expect(User::where('name', 'Teacher Enrolled Student')->exists())->toBeTrue();
});

test('editing student with a parent phone number that already belongs to another parent links to that parent without unique_phone SQL collision', function () {
    [$director, $institution, $class] = makeEnrollmentSchool();
    $saveStudent = app(SaveStudent::class);

    // 1. Enroll first child with parent phone +254 796905322
    $child1 = $saveStudent->create([
        'name' => 'First Child',
        'class_id' => $class->id,
        'parent_name' => 'Existing Parent',
        'parent_phone' => '+254 796905322',
        'parent_occupation' => 'Architect',
    ], $institution->id);

    $originalParentId = $child1->studentUserDetails->parent_id;
    expect($originalParentId)->not->toBeNull();

    // 2. Enroll second child minimally
    $child2 = $saveStudent->create([
        'name' => 'Second Child',
        'class_id' => $class->id,
    ], $institution->id);

    // 3. Edit second child to add the same parent phone number
    $response = $this->actingAs($director)->put(route('student.update', $child2->id), [
        'name' => 'Second Child',
        'parent_name' => 'Existing Parent',
        'parent_phone' => '+254 796905322',
        'parent_occupation' => 'Software Engineer',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');

    $child2->refresh();
    // Both children now link to the exact same parent without any SQL collision
    expect($child2->studentUserDetails->parent_id)->toBe($originalParentId);
});
