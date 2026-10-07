<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Actions\SaveStudent;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Cache::flush();
});

function setupStudentMergeTestInstitution(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Student Merge Academy '.uniqid(),
        'code' => 'SMA-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);
    $director->update(['active_institution_id' => $institution->id]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 4 East',
        'level' => 'Primary',
    ]);

    return [$director, $institution, $class];
}

it('does not create an auto-generated admission number when admission number is left blank', function () {
    [, $institution, $class] = setupStudentMergeTestInstitution();
    $saveStudent = app(SaveStudent::class);

    $student = $saveStudent->create([
        'name' => 'Hassan Jama',
        'admission_number' => '', // Left blank
        'class_id' => $class->id,
        'gender' => 'male',
    ], $institution->id);

    $details = StudentDetails::where('student_id', $student->id)->firstOrFail();

    expect($details->admission_number)->toBeNull()
        ->and($student->email)->toBe('hassan.jama@students.generated');
});

it('merges duplicate student accounts in the same class and retains the real admission number', function () {
    [, $institution, $class] = setupStudentMergeTestInstitution();

    // Duplicate 1: Created with an auto-generated adm number (e.g. from an earlier bulk retry)
    $studentUser1 = User::create([
        'name' => 'Amina Hassan',
        'email' => 'amina1@students.generated',
        'password' => bcrypt('password123'),
    ]);
    $studentUser1->assignRole('Student');
    $details1 = StudentDetails::create([
        'student_id' => $studentUser1->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-2026-X8Y1Z2', // Auto-generated pattern
        'gender' => 'female',
        'is_active' => true,
    ]);

    // Duplicate 2: Created with real school admission number
    $studentUser2 = User::create([
        'name' => 'Amina Hassan',
        'email' => 'amina2@students.generated',
        'password' => bcrypt('password123'),
    ]);
    $studentUser2->assignRole('Student');
    $details2 = StudentDetails::create([
        'student_id' => $studentUser2->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'GVA/2026/054', // Real school adm number
        'gender' => 'female',
        'guardian_name' => 'Hassan Mohamed',
        'guardian_phone' => '0711223344',
        'is_active' => true,
    ]);

    // Run the merge command
    Artisan::call('students:merge-duplicates', [
        '--institution' => $institution->id,
    ]);

    // Verify only 1 student record remains for Amina Hassan in this institution
    $remaining = StudentDetails::where('institution_id', $institution->id)
        ->whereHas('student', fn ($q) => $q->where('name', 'Amina Hassan'))
        ->get();

    expect($remaining)->toHaveCount(1);

    $keeper = $remaining->first();
    // Keeper should have the real school admission number and guardian info
    expect($keeper->admission_number)->toBe('GVA/2026/054')
        ->and($keeper->guardian_name)->toBe('Hassan Mohamed')
        ->and($keeper->guardian_phone)->toBe('0711223344');

    // Duplicate user was purged
    $users = User::role('Student')->where('name', 'Amina Hassan')->get();
    expect($users)->toHaveCount(1);
});
