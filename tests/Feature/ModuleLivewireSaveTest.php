<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use Modules\Lesson\Models\Lesson;
use Modules\Staff\Models\StaffDetails;
use Modules\Staff\Models\StaffPayment;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Teacher\Models\TeacherDetails;
use Modules\Timetable\Models\TimetableEntry;

/**
 * The save paths of the module screens that became Livewire components:
 * what each form actually writes, and what it refuses.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeSavingDirector(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Saving School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

test('the livewire teacher form hires a teacher with a login', function () {
    [$director, $institution] = makeSavingDirector();

    $this->actingAs($director)
        ->post(route('teacher.store'), [
            'name' => 'Peter Otieno',
            'email' => 'peter@example.com',
            'password' => 'password123',
            'department' => 'Sciences',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $teacher = User::where('email', 'peter@example.com')->firstOrFail();

    expect($teacher->hasRole('Teacher'))->toBeTrue()
        ->and($teacher->teacherUserDetails?->institution_id)->toBe($institution->id)
        ->and($teacher->teacherUserDetails?->department)->toBe('Sciences');
});

test('the livewire parent form creates a parent and links a child', function () {
    [$director, $institution] = makeSavingDirector();

    $student = User::factory()->create(['name' => 'Child One']);
    $student->assignRole('Student');
    $details = StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
    ]);

    $this->actingAs($director)
        ->post(route('parent.store'), [
            'name' => 'Mary Wambui',
            'email' => 'mary@example.com',
            'password' => 'password123',
            'parent_phone' => '0700000002',
            'children' => [(string) $student->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $parent = User::where('email', 'mary@example.com')->firstOrFail();

    expect($parent->hasRole('Parent'))->toBeTrue()
        ->and($details->refresh()->parent_id)->toBe($parent->id);
});

test('the livewire staff form can attach a login', function () {
    [$director, $institution] = makeSavingDirector();

    $this->actingAs($director)
        ->post(route('staff.store'), [
            'name' => 'Grace Accounts',
            'email' => 'grace@example.com',
            'job_title' => 'Accountant',
            'create_account' => '1',
            'password' => 'password123',
            'system_role' => 'Accountant',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $staff = StaffDetails::where('name', 'Grace Accounts')->firstOrFail();
    $account = User::find($staff->user_id);

    expect($staff->institution_id)->toBe($institution->id)
        ->and($account)->not->toBeNull()
        ->and($account->hasRole('Accountant'))->toBeTrue();
});

test('the livewire payroll form works the net out and blocks a repeat month', function () {
    [$director, $institution] = makeSavingDirector();

    $staff = StaffDetails::create([
        'institution_id' => $institution->id,
        'name' => 'Jane Bursar',
        'job_title' => 'Bursar',
        'status' => 'active',
        'is_active' => true,
    ]);

    $this->actingAs($director)
        ->post(route('staff.payments.store'), [
            'staff_details_id' => (string) $staff->id,
            'period' => '2026-08',
            'gross_amount' => '50000',
            'allowances' => '5000',
            'deductions' => '2000',
            'payment_method' => 'cash',
            'status' => 'paid',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $payment = StaffPayment::firstOrFail();

    expect((float) $payment->net_amount)->toBe(53000.0)
        ->and($payment->paid_at)->not->toBeNull();

    // The same staff member and month again is refused.
    $this->actingAs($director)
        ->post(route('staff.payments.store'), [
            'staff_details_id' => (string) $staff->id,
            'period' => '2026-08',
            'gross_amount' => '40000',
            'payment_method' => 'cash',
            'status' => 'pending',
        ])
        ->assertSessionHas('error');

    expect(StaffPayment::count())->toBe(1);
});

test('the livewire fee form derives the school and parent from the student', function () {
    [$director, $institution] = makeSavingDirector();

    $parent = User::factory()->create();
    $parent->assignRole('Parent');

    $student = User::factory()->create(['name' => 'Fee Payer']);
    $student->assignRole('Student');
    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'parent_id' => $parent->id,
        'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
    ]);

    $this->actingAs($director)
        ->post(route('feemanagement.store'), [
            'student_id' => (string) $student->id,
            'title' => 'Term 2 Tuition',
            'fee_type' => 'tuition',
            'amount' => '20000',
            'amount_paid' => '5000',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $fee = Fee::firstOrFail();

    expect($fee->institution_id)->toBe($institution->id)
        ->and($fee->parent_id)->toBe($parent->id)
        ->and((float) $fee->balance)->toBe(15000.0)
        ->and($fee->status)->toBe('partial');
});

test('the livewire timetable form refuses to double-book a teacher', function () {
    [$director, $institution] = makeSavingDirector();

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    $classOne = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 A']);
    $classTwo = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 B']);

    $maths = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'is_active' => true,
    ]);
    $physics = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Physics',
        'is_active' => true,
    ]);

    $this->actingAs($director)
        ->post(route('timetable.store'), [
            'institution_id' => $institution->id,
            'class_id' => (string) $classOne->id,
            'subject_id' => (string) $maths->id,
            'teacher_id' => (string) $teacher->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    // Same teacher, overlapping slot, different class.
    $this->actingAs($director)
        ->post(route('timetable.store'), [
            'institution_id' => $institution->id,
            'class_id' => (string) $classTwo->id,
            'subject_id' => (string) $physics->id,
            'teacher_id' => (string) $teacher->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:30',
            'end_time' => '09:30',
        ])
        ->assertSessionHas('error');

    expect(TimetableEntry::count())->toBe(1);
});

test('the livewire lesson grid marks a day in place', function () {
    [$director, $institution] = makeSavingDirector();

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 3 East']);

    $entry = TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject' => 'Chemistry',
        'day_of_week' => now()->format('l'),
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    Livewire::actingAs($director)
        ->test('lesson::index')
        ->set('classId', (string) $class->id)
        // Today, which is the only day still open for marking.
        ->set('date', now()->toDateString())
        ->assertSee('Chemistry')
        ->set('statuses.'.$entry->id, 'attended')
        ->set('remarks.'.$entry->id, 'Covered acids and bases')
        ->call('save')
        ->assertHasNoErrors()
        // No redirect: the grid stays open.
        ->assertNoRedirect();

    $lesson = Lesson::firstOrFail();

    expect($lesson->status)->toBe('attended')
        ->and($lesson->class_id)->toBe($class->id)
        ->and($lesson->remarks)->toBe('Covered acids and bases');
});

test('the livewire institution form registers a school as unapproved', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('institution.store'), [
            'name' => 'Sunrise Academy',
            'code' => 'SUN-'.uniqid(),
            'type' => 'School',
            'email' => 'info@sunrise.example',
            'phone' => '0700000003',
            'county' => 'Nairobi',
            'city' => 'Nairobi',
            'postal_address' => 'P.O. Box 1',
            'physical_address' => 'Ngong Road',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $institution = Institution::where('name', 'Sunrise Academy')->firstOrFail();

    expect($institution->user_id)->toBe($user->id)
        ->and((bool) $institution->is_approved)->toBeFalse()
        // Owning a school makes you its Director.
        ->and($user->refresh()->hasRole('Director'))->toBeTrue();
});
