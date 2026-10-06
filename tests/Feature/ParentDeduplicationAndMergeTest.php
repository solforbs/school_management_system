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
