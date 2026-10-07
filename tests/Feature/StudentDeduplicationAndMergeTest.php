<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Actions\ImportStudents;
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

it('merges duplicate students created across unassigned and assigned classes with parent link', function () {
    [, $institution, $class] = setupStudentMergeTestInstitution();

    $parentUser = User::create([
        'name' => 'Abdinoor Dabi',
        'email' => 'abdinoor@test.com',
        'password' => bcrypt('password123'),
    ]);
    $parentUser->assignRole('Parent');

    // Duplicate 1: Created with name only and no class (unassigned)
    $s1 = User::create(['name' => 'Sudheys Abdinoor', 'email' => 's1@test.com', 'password' => bcrypt('password')]);
    $s1->assignRole('Student');
    StudentDetails::create([
        'student_id' => $s1->id,
        'institution_id' => $institution->id,
        'class_id' => null,
        'is_active' => true,
    ]);

    // Duplicate 2: Created with parent link but no admission number
    $s2 = User::create(['name' => 'Sudheys Abdinoor', 'email' => 's2@test.com', 'password' => bcrypt('password')]);
    $s2->assignRole('Student');
    StudentDetails::create([
        'student_id' => $s2->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'parent_id' => $parentUser->id,
        'is_active' => true,
    ]);

    // Duplicate 3: Created with admission number FAHAN-015-2025
    $s3 = User::create(['name' => 'Sudheys Abdinoor', 'email' => 's3@test.com', 'password' => bcrypt('password')]);
    $s3->assignRole('Student');
    StudentDetails::create([
        'student_id' => $s3->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'FAHAN-015-2025',
        'is_active' => true,
    ]);

    // Run merge command
    Artisan::call('students:merge-duplicates', ['--institution' => $institution->id]);

    $remaining = StudentDetails::where('institution_id', $institution->id)
        ->whereHas('student', fn ($q) => $q->where('name', 'Sudheys Abdinoor'))
        ->get();

    expect($remaining)->toHaveCount(1);

    $keeper = $remaining->first();
    expect($keeper->admission_number)->toBe('FAHAN-015-2025')
        ->and((int) $keeper->class_id)->toBe($class->id)
        ->and($keeper->parent_id)->toBe($parentUser->id);
});

it('enriches existing student during subsequent bulk uploads instead of creating duplicate records', function () {
    [, $institution, $class] = setupStudentMergeTestInstitution();
    $importer = app(ImportStudents::class);

    // 1. First upload: Names alone
    $result1 = $importer->import([
        ['name' => 'Sundus Abdinoor', 'admission_number' => '', 'student_number' => '', 'gender' => '', 'guardian_name' => '', 'guardian_phone' => '', 'class_name' => $class->name, 'enrollment_status' => 'active'],
    ], $institution->id);

    expect($result1['created'])->toBe(1);
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(1);

    $originalStudent = StudentDetails::where('institution_id', $institution->id)->first();
    expect($originalStudent->admission_number)->toBeNull()
        ->and($originalStudent->parent_id)->toBeNull();

    // 2. Second upload: Same student with guardian added
    $result2 = $importer->import([
        ['name' => 'Sundus Abdinoor', 'admission_number' => '', 'student_number' => '', 'gender' => 'female', 'guardian_name' => 'Abdinoor Dabi', 'guardian_phone' => '0722971610', 'class_name' => $class->name, 'enrollment_status' => 'active'],
    ], $institution->id);

    expect($result2['created'])->toBe(1)
        ->and(StudentDetails::where('institution_id', $institution->id)->count())->toBe(1); // STILL 1!

    $originalStudent->refresh();
    expect($originalStudent->parent_id)->not->toBeNull()
        ->and($originalStudent->gender)->toBe('female');

    // 3. Third upload: Same student with admission number added
    $result3 = $importer->import([
        ['name' => 'Sundus Abdinoor', 'admission_number' => 'FAHAN-016-2025', 'student_number' => '', 'gender' => 'female', 'guardian_name' => 'Abdinoor Dabi', 'guardian_phone' => '0722971610', 'class_name' => $class->name, 'enrollment_status' => 'active'],
    ], $institution->id);

    expect($result3['created'])->toBe(1)
        ->and(StudentDetails::where('institution_id', $institution->id)->count())->toBe(1); // STILL 1!

    $originalStudent->refresh();
    expect($originalStudent->admission_number)->toBe('FAHAN-016-2025');
});
