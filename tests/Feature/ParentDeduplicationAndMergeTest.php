<?php

use App\Models\User;
use App\Services\StudentCacheService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Actions\ImportStudents;
use Modules\Student\Actions\SaveStudent;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Cache::flush();
});

function setupParentTestInstitution(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Deduplication Academy '.uniqid(),
        'code' => 'DA-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);
    $director->update(['active_institution_id' => $institution->id]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 1 East',
        'level' => 'Primary',
    ]);

    return [$director, $institution, $class];
}

it('handles corrupt cache in StudentCacheService::getClasses gracefully', function () {
    [, $institution] = setupParentTestInstitution();
    $service = app(StudentCacheService::class);

    // Corrupt the cache key with non-collection data (simulating __PHP_Incomplete_Class or serialized mismatch)
    Cache::put("inst_{$institution->id}_classes_tenant", 'corrupted_string_data', 3600);

    $classes = $service->getClasses($institution->id, false);

    expect($classes)->toBeInstanceOf(Collection::class)
        ->and($classes)->toHaveCount(1)
        ->and($classes->first()->name)->toBe('Grade 1 East');
});

it('links siblings to the same parent when parent name matches and phone is blank', function () {
    [, $institution, $class] = setupParentTestInstitution();
    $saveStudent = app(SaveStudent::class);

    // Create first student with guardian "Aisha Ali" and no phone
    $student1 = $saveStudent->create([
        'name' => 'Child One',
        'admission_number' => 'ADM-A01',
        'gender' => 'female',
        'class_id' => $class->id,
        'parent_name' => 'Aisha Ali',
        'guardian_name' => 'Aisha Ali',
    ], $institution->id);

    // Create second student with guardian "Aisha Ali" and no phone
    $student2 = $saveStudent->create([
        'name' => 'Child Two',
        'admission_number' => 'ADM-A02',
        'gender' => 'male',
        'class_id' => $class->id,
        'parent_name' => 'Aisha Ali',
        'guardian_name' => 'Aisha Ali',
    ], $institution->id);

    $detail1 = StudentDetails::where('student_id', $student1->id)->first();
    $detail2 = StudentDetails::where('student_id', $student2->id)->first();

    expect($detail1->parent_id)->not->toBeNull()
        ->and($detail1->parent_id)->toBe($detail2->parent_id);

    // Verify only 1 parent user was created
    $parents = User::role('Parent')->where('name', 'Aisha Ali')->get();
    expect($parents)->toHaveCount(1);
});

it('merges existing duplicate parents using parents:merge-duplicates command', function () {
    [, $institution, $class] = setupParentTestInstitution();

    // Create two duplicate parent accounts for "Ayaan Baashi Ali"
    $parent1 = User::create([
        'name' => 'Ayaan Baashi Ali',
        'email' => 'parent_dup1@parents.generated',
        'password' => bcrypt('password123'),
    ]);
    $parent1->assignRole('Parent');
    ParentDetails::create([
        'parent_id' => $parent1->id,
        'institution_id' => $institution->id,
        'parent_phone' => null,
    ]);

    $parent2 = User::create([
        'name' => 'Ayaan Baashi Ali',
        'email' => 'parent_dup2@parents.generated',
        'password' => bcrypt('password123'),
    ]);
    $parent2->assignRole('Parent');
    ParentDetails::create([
        'parent_id' => $parent2->id,
        'institution_id' => $institution->id,
        'parent_phone' => '0712345678',
    ]);

    // Create two students, one linked to parent1, one to parent2
    $student1 = User::factory()->create();
    StudentDetails::create([
        'student_id' => $student1->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-D1',
        'gender' => 'female',
        'parent_id' => $parent1->id,
    ]);

    $student2 = User::factory()->create();
    StudentDetails::create([
        'student_id' => $student2->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-D2',
        'gender' => 'male',
        'parent_id' => $parent2->id,
    ]);

    // Run the merge command
    Artisan::call('parents:merge-duplicates', [
        '--institution' => $institution->id,
    ]);

    // Verify that duplicate was merged into one
    $remainingParents = User::role('Parent')->where('name', 'Ayaan Baashi Ali')->get();
    expect($remainingParents)->toHaveCount(1);

    $survivingParent = $remainingParents->first();
    // The surviving parent should have inherited the phone number from parent2
    $survivingDetails = ParentDetails::where('parent_id', $survivingParent->id)->first();
    expect($survivingDetails->parent_phone)->toBe('0712345678');

    // Both students should now point to the surviving parent
    $updatedStudents = StudentDetails::whereIn('student_id', [$student1->id, $student2->id])->get();
    expect($updatedStudents->pluck('parent_id')->unique()->all())->toBe([$survivingParent->id]);
});

it('links 5 siblings to a single parent account in bulk import when sharing guardian name', function () {
    [, $institution, $class] = setupParentTestInstitution();
    $importer = app(ImportStudents::class);

    $rows = [
        [
            'name' => 'Sibling One',
            'admission_number' => 'SIB-001',
            'gender' => 'male',
            'class' => $class->name,
            'guardian_name' => 'Fatuma Mohamed',
            'guardian_phone' => '0711223344',
            'enrollment_status' => 'active',
        ],
        [
            'name' => 'Sibling Two',
            'admission_number' => 'SIB-002',
            'gender' => 'female',
            'class' => $class->name,
            'guardian_name' => 'Fatuma Mohamed',
            'guardian_phone' => '', // phone omitted in row 2
            'enrollment_status' => 'active',
        ],
        [
            'name' => 'Sibling Three',
            'admission_number' => 'SIB-003',
            'gender' => 'male',
            'class' => $class->name,
            'guardian_name' => 'fatuma mohamed', // lowercase variant
            'guardian_phone' => '0711223344',
            'enrollment_status' => 'active',
        ],
        [
            'name' => 'Sibling Four',
            'admission_number' => 'SIB-004',
            'gender' => 'female',
            'class' => $class->name,
            'guardian_name' => 'Fatuma Mohamed',
            'guardian_phone' => '0711 223 344 / 0722000000', // compound phone format
            'enrollment_status' => 'active',
        ],
        [
            'name' => 'Sibling Five',
            'admission_number' => 'SIB-005',
            'gender' => 'male',
            'class' => $class->name,
            'guardian_name' => 'Fatuma Mohamed',
            'guardian_phone' => '', // phone omitted
            'enrollment_status' => 'active',
        ],
    ];

    $result = $importer->import($rows, $institution->id);

    expect($result['errors'])->toBeEmpty()
        ->and($result['created'])->toBe(5);

    // Verify exactly ONE parent account was created for all 5 siblings
    $parents = User::role('Parent')
        ->whereRaw('LOWER(TRIM(name)) = ?', ['fatuma mohamed'])
        ->get();

    expect($parents)->toHaveCount(1);

    $parent = $parents->first();
    $details = StudentDetails::where('institution_id', $institution->id)
        ->whereIn('admission_number', ['SIB-001', 'SIB-002', 'SIB-003', 'SIB-004', 'SIB-005'])
        ->get();

    expect($details)->toHaveCount(5);
    foreach ($details as $detail) {
        expect($detail->parent_id)->toBe($parent->id);
    }
});

it('links siblings enrolled across separate import batches and single forms to the same parent', function () {
    [, $institution, $class] = setupParentTestInstitution();
    $importer = app(ImportStudents::class);
    $saver = app(SaveStudent::class);

    // Batch 1: Upload first student
    $importer->import([
        [
            'name' => 'First Child',
            'admission_number' => 'CROSS-001',
            'gender' => 'male',
            'class' => $class->name,
            'guardian_name' => 'Hassan Omar',
            'guardian_phone' => '',
            'enrollment_status' => 'active',
        ],
    ], $institution->id);

    // Batch 2: Upload second student days later in another batch
    $importer->import([
        [
            'name' => 'Second Child',
            'admission_number' => 'CROSS-002',
            'gender' => 'female',
            'class' => $class->name,
            'guardian_name' => 'Hassan Omar',
            'guardian_phone' => '',
            'enrollment_status' => 'active',
        ],
    ], $institution->id);

    // Batch 3: Single student form creation
    $student3 = $saver->create([
        'name' => 'Third Child',
        'admission_number' => 'CROSS-003',
        'gender' => 'male',
        'class_id' => $class->id,
        'parent_name' => 'Hassan Omar',
        'guardian_name' => 'Hassan Omar',
    ], $institution->id);

    // Verify all 3 point to the exact same parent user
    $parents = User::role('Parent')
        ->whereRaw('LOWER(TRIM(name)) = ?', ['hassan omar'])
        ->get();

    expect($parents)->toHaveCount(1);

    $parent = $parents->first();
    $students = StudentDetails::where('institution_id', $institution->id)
        ->whereIn('admission_number', ['CROSS-001', 'CROSS-002', 'CROSS-003'])
        ->get();

    expect($students)->toHaveCount(3);
    foreach ($students as $stu) {
        expect($stu->parent_id)->toBe($parent->id);
    }
});
