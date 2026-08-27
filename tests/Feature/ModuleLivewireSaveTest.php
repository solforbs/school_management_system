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

    Livewire::actingAs($director)
        ->test('teacher::form')
        ->set('name', 'Peter Otieno')
        ->set('email', 'peter@example.com')
        ->set('password', 'password123')
        ->set('department', 'Sciences')
        ->call('save')
        ->assertHasNoErrors()
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

    Livewire::actingAs($director)
        ->test('parent::form')
        ->set('name', 'Mary Wambui')
        ->set('email', 'mary@example.com')
        ->set('password', 'password123')
        ->set('parent_phone', '0700000002')
        ->set('children', [(string) $student->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $parent = User::where('email', 'mary@example.com')->firstOrFail();

    expect($parent->hasRole('Parent'))->toBeTrue()
        ->and($details->refresh()->parent_id)->toBe($parent->id);
});

test('the livewire staff form can attach a login', function () {
    [$director, $institution] = makeSavingDirector();

    Livewire::actingAs($director)
        ->test('staff::form')
        ->set('name', 'Grace Accounts')
        ->set('email', 'grace@example.com')
        ->set('job_title', 'Accountant')
        ->set('create_account', true)
        ->set('password', 'password123')
        ->set('system_role', 'Accountant')
        ->call('save')
        ->assertHasNoErrors()
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

    Livewire::actingAs($director)
        ->test('staff::payments.form')
        ->set('staff_details_id', (string) $staff->id)
        ->set('period', '2026-08')
        ->set('gross_amount', '50000')
        ->set('allowances', '5000')
        ->set('deductions', '2000')
        ->set('status', 'paid')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $payment = StaffPayment::firstOrFail();

    expect((float) $payment->net_amount)->toBe(53000.0)
        ->and($payment->paid_at)->not->toBeNull();

    // The same staff member and month again is refused.
    Livewire::actingAs($director)
        ->test('staff::payments.form')
        ->set('staff_details_id', (string) $staff->id)
        ->set('period', '2026-08')
        ->set('gross_amount', '40000')
        ->set('status', 'pending')
        ->call('save')
        ->assertHasErrors('period');

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

    Livewire::actingAs($director)
        ->test('feemanagement::form')
        ->set('student_id', (string) $student->id)
        ->set('title', 'Term 2 Tuition')
        ->set('fee_type', 'tuition')
        ->set('amount', '20000')
        ->set('amount_paid', '5000')
        ->call('save')
        ->assertHasNoErrors()
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

    Livewire::actingAs($director)
        ->test('timetable::form')
        ->set('class_id', (string) $classOne->id)
        ->set('subject', 'Mathematics')
        ->set('teacher_id', (string) $teacher->id)
        ->set('day_of_week', 'Monday')
        ->set('start_time', '08:00')
        ->set('end_time', '09:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    // Same teacher, overlapping slot, different class.
    Livewire::actingAs($director)
        ->test('timetable::form')
        ->set('class_id', (string) $classTwo->id)
        ->set('subject', 'Physics')
        ->set('teacher_id', (string) $teacher->id)
        ->set('day_of_week', 'Monday')
        ->set('start_time', '08:30')
        ->set('end_time', '09:30')
        ->call('save')
        ->assertHasErrors('teacher_id');

    expect(TimetableEntry::count())->toBe(1);
});

test('the livewire lesson grid marks a day in place', function () {
    [$director, $institution] = makeSavingDirector();

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 3 East']);

    $entry = TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject' => 'Chemistry',
        'day_of_week' => 'Monday',
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    Livewire::actingAs($director)
        ->test('lesson::index')
        ->set('classId', (string) $class->id)
        // A Monday, so the entry above is timetabled that day.
        ->set('date', '2026-08-24')
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

    Livewire::actingAs($user)
        ->test('institution::form')
        ->set('name', 'Sunrise Academy')
        ->set('code', 'SUN-'.uniqid())
        ->set('type', 'School')
        ->set('email', 'info@sunrise.example')
        ->set('phone', '0700000003')
        ->set('county', 'Nairobi')
        ->set('city', 'Nairobi')
        ->set('postal_address', 'P.O. Box 1')
        ->set('physical_address', 'Ngong Road')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $institution = Institution::where('name', 'Sunrise Academy')->firstOrFail();

    expect($institution->user_id)->toBe($user->id)
        ->and((bool) $institution->is_approved)->toBeFalse()
        // Owning a school makes you its Director.
        ->and($user->refresh()->hasRole('Director'))->toBeTrue();
});
