<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;
use Modules\Teacher\Actions\SaveTeacher;
use Modules\Teacher\Models\TeacherDetails;
use Modules\Timetable\Models\TimetableEntry;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeTeacherTestSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Fahan Academy '.uniqid(),
        'code' => 'FAH-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

test('new teachers created by admin/director are not auto-verified and require email verification link', function () {
    [$director, $institution] = makeTeacherTestSchool();

    $teacher = SaveTeacher::create([
        'name' => 'Paul Kangangi',
        'email' => 'paul.'.uniqid().'@test.com',
        'password' => 'password123',
        'employee_number' => 'EMP-'.uniqid(),
    ], $institution->id);

    expect($teacher->email_verified_at)->toBeNull();
    expect($teacher->hasVerifiedEmail())->toBeFalse();
});

test('unverified teachers can have email verification link resent via livewire index', function () {
    [$director, $institution] = makeTeacherTestSchool();

    $user = User::factory()->create([
        'name' => 'Paul Unverified',
        'email' => 'paul.unverified@test.com',
        'email_verified_at' => null,
    ]);
    $user->assignRole('Teacher');

    TeacherDetails::create([
        'teacher_id' => $user->id,
        'institution_id' => $institution->id,
        'employee_number' => 'EMP-T-999',
    ]);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    Livewire::actingAs($director)
        ->test('teacher::index')
        ->call('resendVerification', $user->id)
        ->assertHasNoErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('purge-seeded-data command removes @school.test teachers and timetable entries for target school', function () {
    [$director, $institution] = makeTeacherTestSchool();

    // 1. Create a fake test teacher (@school.test)
    $fakeUser = User::factory()->create([
        'name' => 'Metalwork Teacher 32',
        'email' => 'teacher.metalwork.32@school.test',
    ]);
    $fakeUser->assignRole('Teacher');

    TeacherDetails::create([
        'teacher_id' => $fakeUser->id,
        'institution_id' => $institution->id,
        'employee_number' => 'EMP-T-730',
    ]);

    // 2. Create a legitimate unverified teacher (Paul Kangangi)
    $realUser = User::factory()->create([
        'name' => 'Paul Kangangi',
        'email' => 'pkang284@gmail.com',
        'email_verified_at' => null,
    ]);
    $realUser->assignRole('Teacher');

    TeacherDetails::create([
        'teacher_id' => $realUser->id,
        'institution_id' => $institution->id,
        'employee_number' => 'EMP-T-1144',
    ]);

    // 3. Create timetable entry for fake user
    TimetableEntry::create([
        'institution_id' => $institution->id,
        'teacher_id' => $fakeUser->id,
        'subject' => 'Metalwork',
        'day_of_week' => 'Wednesday',
        'start_time' => '11:40',
        'end_time' => '12:20',
    ]);

    expect(TeacherDetails::where('institution_id', $institution->id)->count())->toBe(2);
    expect(TimetableEntry::where('institution_id', $institution->id)->count())->toBe(1);

    // Run command
    $this->artisan('school:purge-seeded-data', [
        '--institution' => $institution->id,
        '--timetables' => true,
        '--verify-teachers' => true,
    ])->assertSuccessful();

    // Verification: Fake user and details deleted
    expect(User::where('id', $fakeUser->id)->exists())->toBeFalse();
    expect(TeacherDetails::where('teacher_id', $fakeUser->id)->exists())->toBeFalse();

    // Timetable entry deleted
    expect(TimetableEntry::where('institution_id', $institution->id)->count())->toBe(0);

    // Real teacher preserved and auto-verified
    expect(User::where('id', $realUser->id)->exists())->toBeTrue();
    expect(TeacherDetails::where('teacher_id', $realUser->id)->exists())->toBeTrue();
    expect($realUser->fresh()->hasVerifiedEmail())->toBeTrue();
});
