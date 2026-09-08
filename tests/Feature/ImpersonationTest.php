<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Modules\Teacher\Models\TeacherDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function impAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    return $admin->refresh();
}

/**
 * @return array{0: User, 1: Institution}
 */
function impDirector(array $institutionOverrides = []): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create(array_merge([
        'user_id' => $director->id,
        'name' => 'Imp School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ], $institutionOverrides));

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function impTeacher(Institution $institution): User
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

function impStudent(Institution $institution): User
{
    $parent = User::factory()->create();
    DB::table('parent_details')->insert(['id' => $parent->id, 'parent_id' => $parent->id]);

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Class '.uniqid()]);

    $student = User::factory()->create();
    $student->assignRole('Student');

    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'parent_id' => $parent->id,
        'admission_number' => 'ADM-'.uniqid(),
    ]);

    return $student->refresh();
}

function impParent(Institution $institution): User
{
    $parent = User::factory()->create();
    $parent->assignRole('Parent');
    DB::table('parent_details')->insert(['id' => $parent->id, 'parent_id' => $parent->id]);

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Class '.uniqid()]);
    $child = User::factory()->create();

    StudentDetails::create([
        'student_id' => $child->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'parent_id' => $parent->id,
        'admission_number' => 'ADM-'.uniqid(),
    ]);

    return $parent->refresh();
}

test('an admin logs in as a director and back out', function () {
    $admin = impAdmin();
    [$director] = impDirector();

    $this->actingAs($admin)
        ->post(route('impersonate.start', $director))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($director);
    expect(session('impersonator_id'))->toBe($admin->id);

    $this->post(route('impersonate.leave'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($admin);
    expect(session()->has('impersonator_id'))->toBeFalse();
});

test('an admin cannot impersonate another admin', function () {
    $admin = impAdmin();
    $other = impAdmin();

    $this->actingAs($admin)->post(route('impersonate.start', $other))->assertForbidden();
    $this->assertAuthenticatedAs($admin);
});

test('an admin cannot impersonate a teacher directly', function () {
    $admin = impAdmin();
    [, $institution] = impDirector();
    $teacher = impTeacher($institution);

    $this->actingAs($admin)->post(route('impersonate.start', $teacher))->assertForbidden();
});

test('a protected admin is never a target', function () {
    config(['app.protected_admin_emails' => 'owner@platform.test']);

    $admin = impAdmin();
    $protectedDirector = User::factory()->create(['email' => 'owner@platform.test']);
    $protectedDirector->assignRole('Director');

    $this->actingAs($admin)->post(route('impersonate.start', $protectedDirector))->assertForbidden();
});

test('a director logs in as their own teacher, student and parent', function () {
    [$director, $institution] = impDirector();

    foreach ([impTeacher($institution), impStudent($institution), impParent($institution)] as $target) {
        $this->actingAs($director)
            ->post(route('impersonate.start', $target))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($target);

        $this->post(route('impersonate.leave'));
        $this->assertAuthenticatedAs($director);
    }
});

test('a director cannot impersonate a user from another school', function () {
    [$director] = impDirector();
    [, $otherSchool] = impDirector();
    $outsideTeacher = impTeacher($otherSchool);

    $this->actingAs($director)->post(route('impersonate.start', $outsideTeacher))->assertForbidden();
});

test('a director cannot impersonate another director or an admin', function () {
    [$director, $institution] = impDirector();
    [$otherDirector] = impDirector();
    $admin = impAdmin();

    $this->actingAs($director)->post(route('impersonate.start', $otherDirector))->assertForbidden();
    $this->actingAs($director)->post(route('impersonate.start', $admin))->assertForbidden();
});

test('impersonation cannot nest', function () {
    $admin = impAdmin();
    [$director, $institution] = impDirector();
    $teacher = impTeacher($institution);

    $this->actingAs($admin)->post(route('impersonate.start', $director))->assertRedirect();
    $this->assertAuthenticatedAs($director);

    // Already impersonating - a second hop is refused.
    $this->post(route('impersonate.start', $teacher))->assertForbidden();
    $this->assertAuthenticatedAs($director);
});

test('the way back works even when the impersonated school is unapproved', function () {
    $admin = impAdmin();
    [$director] = impDirector(['is_approved' => false]);

    $this->actingAs($admin)->post(route('impersonate.start', $director))->assertRedirect();
    $this->assertAuthenticatedAs($director);

    $this->post(route('impersonate.leave'))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($admin);
});

test('the login-as button shows for a director but not for a teacher', function () {
    [$director, $institution] = impDirector();
    $teacher = impTeacher($institution);

    Livewire::actingAs($director)->test('teacher::index')
        ->assertSee(route('impersonate.start', $teacher), escape: false);

    Livewire::actingAs($teacher)->test('teacher::index')
        ->assertDontSee(route('impersonate.start', $teacher), escape: false);
});
