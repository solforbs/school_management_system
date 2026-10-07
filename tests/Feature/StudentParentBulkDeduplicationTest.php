<?php

use App\Models\User;
use App\Services\ParentMatchingService;
use Database\Seeders\PermissionSeeder;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Actions\ImportStudents;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function createTestSchoolForParent(string $name): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => $name.' '.uniqid(),
        'code' => 'SCH-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 1 East',
        'level' => 'Primary',
    ]);

    return [$director, $institution, $class];
}

it('creates only one parent record when multiple students in the same import share the same guardian phone', function () {
    [, $institution, $class] = createTestSchoolForParent('Sibling Academy');
    $importer = app(ImportStudents::class);

    $rows = [
        [
            'name' => 'Ali Hassan',
            'admission_number' => 'ADM-01',
            'student_number' => '',
            'class_name' => $class->name,
            'gender' => 'male',
            'guardian_name' => 'Ahmed Hassan',
            'guardian_phone' => '0712345678',
            'enrollment_status' => 'active',
        ],
        [
            'name' => 'Amina Hassan',
            'admission_number' => 'ADM-02',
            'student_number' => '',
            'class_name' => $class->name,
            'gender' => 'female',
            'guardian_name' => 'Ahmed Hassan',
            'guardian_phone' => '0712345678',
            'enrollment_status' => 'active',
        ],
    ];

    $result = $importer->import($rows, $institution->id);

    expect($result['created'])->toBe(2)
        ->and($result['errors'])->toBeEmpty();

    // Verify only ONE parent user was created
    $parents = User::role('Parent')
        ->whereHas('parent', fn ($q) => $q->where('institution_id', $institution->id))
        ->get();

    expect($parents)->toHaveCount(1)
        ->and($parents->first()->name)->toBe('Ahmed Hassan');

    // Verify both students point to this parent
    $children = StudentDetails::where('institution_id', $institution->id)->get();
    expect($children)->toHaveCount(2)
        ->and($children[0]->parent_id)->toBe($parents->first()->id)
        ->and($children[1]->parent_id)->toBe($parents->first()->id);
});

it('links students to existing parent record when phone matches an already registered guardian', function () {
    [, $institution, $class] = createTestSchoolForParent('Reused Parent School');
    $importer = app(ImportStudents::class);

    // Pre-create existing parent in the school
    $existingParent = User::create([
        'name' => 'Ahmed Hassan',
        'email' => 'ahmed.parent@school.test',
        'password' => bcrypt('password123'),
    ]);
    $existingParent->assignRole('Parent');

    ParentDetails::create([
        'parent_id' => $existingParent->id,
        'institution_id' => $institution->id,
        'parent_phone' => '0712345678',
    ]);

    // Import 2 new students specifying the same guardian phone
    $rows = [
        [
            'name' => 'Omar Hassan',
            'admission_number' => 'ADM-10',
            'student_number' => '',
            'class_name' => $class->name,
            'gender' => 'male',
            'guardian_name' => 'Ahmed Hassan',
            'guardian_phone' => '0712345678',
            'enrollment_status' => 'active',
        ],
        [
            'name' => 'Zahra Hassan',
            'admission_number' => 'ADM-11',
            'student_number' => '',
            'class_name' => $class->name,
            'gender' => 'female',
            'guardian_name' => 'Ahmed Hassan',
            'guardian_phone' => '0712345678',
            'enrollment_status' => 'active',
        ],
    ];

    $result = $importer->import($rows, $institution->id);

    expect($result['created'])->toBe(2);

    // No new parent accounts should have been created
    $totalParents = User::role('Parent')
        ->whereHas('parent', fn ($q) => $q->where('institution_id', $institution->id))
        ->count();
    expect($totalParents)->toBe(1);

    // Both new students should be linked to the existing parent
    $students = StudentDetails::whereIn('admission_number', ['ADM-10', 'ADM-11'])->get();
    expect($students[0]->parent_id)->toBe($existingParent->id)
        ->and($students[1]->parent_id)->toBe($existingParent->id);
});

it('normalizes Kenyan phone numbers across different formatting conventions', function () {
    $matcher = app(ParentMatchingService::class);

    expect($matcher->normalizePhone('+254 712 345 678'))->toBe('0712345678')
        ->and($matcher->normalizePhone('254712345678'))->toBe('0712345678')
        ->and($matcher->normalizePhone('712345678'))->toBe('0712345678')
        ->and($matcher->normalizePhone('0712 345 678'))->toBe('0712345678')
        ->and($matcher->normalizePhone('0712-345-678'))->toBe('0712345678');
});

it('creates distinct parents when names are identical but phone numbers are different', function () {
    [, $institution, $class] = createTestSchoolForParent('Distinct Parents School');
    $importer = app(ImportStudents::class);

    $rows = [
        [
            'name' => 'Child A',
            'admission_number' => 'ADM-01',
            'student_number' => '',
            'class_name' => $class->name,
            'gender' => 'male',
            'guardian_name' => 'Mohamed Ali',
            'guardian_phone' => '0711111111',
            'enrollment_status' => 'active',
        ],
        [
            'name' => 'Child B',
            'admission_number' => 'ADM-02',
            'student_number' => '',
            'class_name' => $class->name,
            'gender' => 'male',
            'guardian_name' => 'Mohamed Ali',
            'guardian_phone' => '0722222222',
            'enrollment_status' => 'active',
        ],
    ];

    $result = $importer->import($rows, $institution->id);

    expect($result['created'])->toBe(2);

    // Should create 2 separate parents because the phone numbers are different
    $parents = User::role('Parent')
        ->whereHas('parent', fn ($q) => $q->where('institution_id', $institution->id))
        ->get();

    expect($parents)->toHaveCount(2);
});

it('flags name-only guardian matches as ambiguous for admin confirmation (Option A)', function () {
    [, $institution] = createTestSchoolForParent('Ambiguity School');
    $matcher = app(ParentMatchingService::class);

    // Pre-create existing parent "Fatima Omar" with no phone
    $existingParent = User::create([
        'name' => 'Fatima Omar',
        'email' => 'fatima@school.test',
        'password' => bcrypt('password123'),
    ]);
    $existingParent->assignRole('Parent');

    ParentDetails::create([
        'parent_id' => $existingParent->id,
        'institution_id' => $institution->id,
        'parent_phone' => null,
    ]);

    // Batch has a student with guardian "Fatima Omar" and NO phone
    $rows = [
        [
            'name' => 'Maryam Noor',
            'guardian_name' => 'Fatima Omar',
            'guardian_phone' => '',
        ],
    ];

    $analysis = $matcher->analyzeBatch($institution->id, $rows);

    expect($analysis['ambiguous_matches'])->not->toBeEmpty()
        ->and($analysis['ambiguous_matches']['name:fatima omar']['guardian_name'])->toBe('Fatima Omar')
        ->and($analysis['ambiguous_matches']['name:fatima omar']['candidate_parents'][0]['id'])->toBe($existingParent->id);
});

it('applies admin decisions for ambiguous name-only guardian matches', function () {
    [, $institution, $class] = createTestSchoolForParent('Decision School');
    $importer = app(ImportStudents::class);

    // Existing parent
    $existingParent = User::create([
        'name' => 'Fatima Omar',
        'email' => 'fatima@school.test',
        'password' => bcrypt('password123'),
    ]);
    $existingParent->assignRole('Parent');

    ParentDetails::create([
        'parent_id' => $existingParent->id,
        'institution_id' => $institution->id,
        'parent_phone' => null,
    ]);

    $rows = [
        [
            'name' => 'Child One',
            'admission_number' => 'ADM-X1',
            'student_number' => '',
            'class_name' => $class->name,
            'gender' => 'female',
            'guardian_name' => 'Fatima Omar',
            'guardian_phone' => '',
            'enrollment_status' => 'active',
        ],
    ];

    // Admin decision: link to existing parent
    $result = $importer->import($rows, $institution->id, [
        'name:fatima omar' => $existingParent->id,
    ]);

    expect($result['created'])->toBe(1);

    $child = StudentDetails::where('admission_number', 'ADM-X1')->first();
    expect($child->parent_id)->toBe($existingParent->id);
});
