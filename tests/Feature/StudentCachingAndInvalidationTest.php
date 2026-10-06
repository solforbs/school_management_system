<?php

use App\Models\User;
use App\Services\StudentCacheService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Actions\ImportStudents;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Cache::flush();
});

function createTestInstitution(): Institution
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    return Institution::create([
        'user_id' => $director->id,
        'name' => 'Cache Test Academy '.uniqid(),
        'code' => 'CTA-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);
}

it('caches class stats and serves them without repeated db queries', function () {
    $institution = createTestInstitution();
    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 3',
        'level' => 'Primary',
    ]);

    $student1 = User::factory()->create();
    StudentDetails::create([
        'student_id' => $student1->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-001',
        'gender' => 'Male',
        'is_active' => true,
    ]);

    $student2 = User::factory()->create();
    StudentDetails::create([
        'student_id' => $student2->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-002',
        'gender' => 'Female',
        'is_active' => true,
    ]);

    $service = app(StudentCacheService::class);

    $stats = $service->getClassStats($institution->id, $class->id);
    expect($stats['total'])->toBe(2)
        ->and($stats['male'])->toBe(1)
        ->and($stats['female'])->toBe(1)
        ->and($stats['latest_admission'])->toBe('ADM-002');

    // Verify key exists in Cache
    expect(Cache::has("inst_{$institution->id}_class_{$class->id}_stats"))->toBeTrue();
});

it('automatically invalidates class stats cache when a student details record is updated or created', function () {
    $institution = createTestInstitution();
    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 3',
        'level' => 'Primary',
    ]);

    $student1 = User::factory()->create();
    $details = StudentDetails::create([
        'student_id' => $student1->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-001',
        'gender' => 'Male',
        'is_active' => true,
    ]);

    $service = app(StudentCacheService::class);
    $initialStats = $service->getClassStats($institution->id, $class->id);
    expect($initialStats['total'])->toBe(1);

    // Now add another student
    $student2 = User::factory()->create();
    StudentDetails::create([
        'student_id' => $student2->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-002',
        'gender' => 'Female',
        'is_active' => true,
    ]);

    // Model booted listener should have invalidated the cache!
    $freshStats = $service->getClassStats($institution->id, $class->id);
    expect($freshStats['total'])->toBe(2)
        ->and($freshStats['female'])->toBe(1);

    // Now update gender on student 1 to Female
    $details->update(['gender' => 'Female']);

    $afterUpdateStats = $service->getClassStats($institution->id, $class->id);
    expect($afterUpdateStats['male'])->toBe(0)
        ->and($afterUpdateStats['female'])->toBe(2);
});

it('invalidates both old and new class caches when a student changes classes', function () {
    $institution = createTestInstitution();
    $classA = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 3',
        'level' => 'Primary',
    ]);
    $classB = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 4',
        'level' => 'Primary',
    ]);

    $user = User::factory()->create();
    $details = StudentDetails::create([
        'student_id' => $user->id,
        'institution_id' => $institution->id,
        'class_id' => $classA->id,
        'admission_number' => 'ADM-100',
        'gender' => 'Male',
        'is_active' => true,
    ]);

    $service = app(StudentCacheService::class);
    expect($service->getClassStats($institution->id, $classA->id)['total'])->toBe(1)
        ->and($service->getClassStats($institution->id, $classB->id)['total'])->toBe(0);

    // Transfer student from classA to classB
    $details->update(['class_id' => $classB->id]);

    expect($service->getClassStats($institution->id, $classA->id)['total'])->toBe(0)
        ->and($service->getClassStats($institution->id, $classB->id)['total'])->toBe(1);
});

it('invalidates school class cache when classes are created or deleted', function () {
    $institution = createTestInstitution();
    $class1 = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 1',
        'level' => 'Primary',
    ]);

    $service = app(StudentCacheService::class);
    $classes = $service->getClasses($institution->id, false);
    expect($classes)->toHaveCount(1);

    // Create another class
    $class2 = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 2',
        'level' => 'Primary',
    ]);

    $freshClasses = $service->getClasses($institution->id, false);
    expect($freshClasses)->toHaveCount(2);

    // Delete a class
    $class2->delete();

    $afterDelete = $service->getClasses($institution->id, false);
    expect($afterDelete)->toHaveCount(1);
});

it('caches institution student count and invalidates when student is added or removed', function () {
    $institution = createTestInstitution();
    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 1',
        'level' => 'Primary',
    ]);

    $service = app(StudentCacheService::class);
    expect($institution->currentStudentCount())->toBe(0);

    $user = User::factory()->create();
    $student = StudentDetails::create([
        'student_id' => $user->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-200',
        'gender' => 'Male',
        'is_active' => true,
    ]);

    expect($institution->currentStudentCount())->toBe(1);

    $student->delete();
    expect($institution->currentStudentCount())->toBe(0);
});

it('serves institution-wide stats and roster for all classes and keeps them fresh', function () {
    $institution = createTestInstitution();
    $classA = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Grade 3', 'level' => 'Primary']);
    $classB = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Grade 4', 'level' => 'Primary']);

    $make = function (string $adm, $class, string $gender) use ($institution) {
        return StudentDetails::create([
            'student_id' => User::factory()->create()->id,
            'institution_id' => $institution->id,
            'class_id' => $class->id,
            'admission_number' => $adm,
            'gender' => $gender,
            'is_active' => true,
        ]);
    };

    $make('ALL-001', $classA, 'Male');
    $service = app(StudentCacheService::class);

    expect($service->getInstitutionStats($institution->id)['total'])->toBe(1)
        ->and($service->getInstitutionRoster($institution->id))->toHaveCount(1);

    $make('ALL-002', $classB, 'Female');

    $stats = $service->getInstitutionStats($institution->id);
    expect($stats['total'])->toBe(2)
        ->and($stats['female'])->toBe(1)
        ->and($service->getInstitutionRoster($institution->id))->toHaveCount(2);
});

it('creates and links a parent account from bulk-imported guardian details', function () {
    $institution = createTestInstitution();
    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Grade 3', 'level' => 'Primary']);

    $result = app(ImportStudents::class)->import([[
        'name' => 'Amina Test', 'admission_number' => 'BLK-001', 'student_number' => '',
        'class_name' => 'Grade 3', 'gender' => 'female', 'guardian_name' => 'Mama Amina',
        'guardian_phone' => '0712345678', 'enrollment_status' => 'active',
    ]], $institution->id);

    expect($result['errors'])->toBe([])->and($result['created'])->toBe(1);

    $details = StudentDetails::where('admission_number', 'BLK-001')->first();
    expect($details->parent_id)->not->toBeNull()
        ->and(User::find($details->parent_id)->name)->toBe('Mama Amina');
});
