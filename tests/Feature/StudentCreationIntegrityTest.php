<?php

use App\Models\Plan;
use App\Models\User;
use App\Services\AdmissionNumberPatternService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Actions\ImportStudents;
use Modules\Student\Actions\SaveStudent;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Cache::flush();
});

function createIntegrityTestSchool(): array
{
    $plan = Plan::create([
        'name' => 'Integrity Plan '.uniqid(),
        'slug' => 'integrity-'.uniqid(),
        'price' => 1000,
        'billing_cycle' => 'termly',
        'modules' => ['Student'],
        'features' => [],
        'max_students' => null,
        'is_active' => true,
    ]);

    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Fahan Academy '.uniqid(),
        'code' => 'FAHAN-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
        'subscription_plan' => $plan->id,
    ]);
    $director->update(['active_institution_id' => $institution->id]);

    $classA = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 East',
        'level' => 'Junior Secondary',
    ]);

    $classB = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 East',
        'level' => 'Junior Secondary',
    ]);

    return [$director, $institution, $classA, $classB];
}

test('1. Creating a student with a supplied admission number', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    $student = $saver->create([
        'name' => 'Anfal Mohamed',
        'admission_number' => 'FAHAN-014-2025',
        'class_id' => $classA->id,
    ], $institution->id);

    $details = StudentDetails::where('student_id', $student->id)->firstOrFail();
    expect($details->admission_number)->toBe('FAHAN-014-2025')
        ->and($details->institution_id)->toBe($institution->id);
});

test('2. Admission number is required and student details cannot be saved without it', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    // Attempting to create without admission number throws exception
    expect(fn () => $saver->create([
        'name' => 'Ascad Mohamed',
        'admission_number' => '', // omitted
        'class_id' => $classA->id,
    ], $institution->id))->toThrow(InvalidArgumentException::class, 'Admission number is required');

    // Validation rule also fails
    $rules = SaveStudent::createRules($institution->id);
    $validator = validator([
        'name' => 'Ascad Mohamed',
        'admission_number' => '',
        'class_id' => $classA->id,
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('admission_number'))->toBeTrue();
});

test('3 & 4. Student email is left NULL when not supplied, never inventing @students.generated addresses', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    $student = $saver->create([
        'name' => 'Hanad Abdirahman',
        'email' => '',
        'admission_number' => 'FAHAN-015-2025',
        'class_id' => $classA->id,
    ], $institution->id);

    expect($student->email)->toBeNull();

    $studentWithEmail = $saver->create([
        'name' => 'Hanad Duale',
        'email' => 'hanad.duale@realdomain.com',
        'admission_number' => 'FAHAN-016-2025',
        'class_id' => $classA->id,
    ], $institution->id);

    expect($studentWithEmail->email)->toBe('hanad.duale@realdomain.com');
});

test('5. Duplicate normalized name detection handles whitespace, casing, and spaces', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    $student1 = $saver->create([
        'name' => 'Anfal Mohamed',
        'admission_number' => 'FAHAN-014-2025',
        'class_id' => $classA->id,
    ], $institution->id);

    // Variation with extra whitespace and different casing
    $match = $saver->findExistingStudentMatch($institution->id, [
        'name' => '  anfal   MOHAMED  ',
        'admission_number' => 'FAHAN-014-2025',
        'class_id' => $classA->id,
    ]);

    expect($match)->not->toBeNull()
        ->and($match->student_id)->toBe($student1->id);
});

test('6. Same student submitted twice returns existing record without creating duplicate User or StudentDetails', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    // Pass 1: Student created with name and admission number
    $first = $saver->create([
        'name' => 'Jabir Abdirahman',
        'admission_number' => 'FAHAN-015-2025',
        'class_id' => $classA->id,
    ], $institution->id);

    // Pass 2: Re-submitting same student with same admission number and updated guardian
    $second = $saver->create([
        'name' => 'Jabir Abdirahman',
        'admission_number' => 'FAHAN-015-2025',
        'guardian_name' => 'Abdirahman Ali',
        'guardian_phone' => '0712345678',
        'class_id' => $classA->id,
    ], $institution->id);

    expect($second->id)->toBe($first->id);
    expect(User::role('Student')->where('name', 'Jabir Abdirahman')->count())->toBe(1);
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(1);

    $details = StudentDetails::where('student_id', $first->id)->firstOrFail();
    expect($details->admission_number)->toBe('FAHAN-015-2025')
        ->and($details->guardian_name)->toBe('Abdirahman Ali')
        ->and($details->guardian_phone)->toBe('0712345678');
});

test('7. Same import row processed twice is idempotent and does not create duplicate student', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $importer = app(ImportStudents::class);

    $rows = [
        ['name' => 'Sucdi Abdinoor', 'admission_number' => 'FAHAN-060-2025', 'student_number' => '', 'class_name' => $classA->name, 'gender' => 'female', 'guardian_name' => 'Abdinoor Dabi', 'guardian_phone' => '0722971610', 'enrollment_status' => 'active'],
    ];

    // First import
    $res1 = $importer->import($rows, $institution->id);
    expect($res1['created'])->toBe(1);
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(1);

    // Same import executed second time
    $res2 = $importer->import($rows, $institution->id);
    expect($res2['created'])->toBe(1); // Enriched
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(1); // Still exactly 1!
    expect(User::role('Student')->where('name', 'Sucdi Abdinoor')->count())->toBe(1);
});

test('8. Duplicate admission number within same institution is prevented', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    $saver->create([
        'name' => 'Student One',
        'admission_number' => 'FAHAN-020-2025',
        'class_id' => $classA->id,
    ], $institution->id);

    // Another student using the same admission number
    $rules = SaveStudent::createRules($institution->id);
    $validator = validator([
        'name' => 'Student Two',
        'admission_number' => 'FAHAN-020-2025',
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('admission_number'))->toBeTrue();
});

test('9. Two genuinely different students with the same name are preserved as two distinct students', function () {
    [, $institution, $classA, $classB] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    // Student A: In Grade 7 with DOB 2012-05-10
    $studentA = $saver->create([
        'name' => 'Anfal Mohamed',
        'date_of_birth' => '2012-05-10',
        'class_id' => $classA->id,
        'admission_number' => 'FAHAN-001-2025',
    ], $institution->id);

    // Student B: In Grade 8 with DOB 2010-09-15 and guardian phone
    $studentB = $saver->create([
        'name' => 'Anfal Mohamed',
        'date_of_birth' => '2010-09-15',
        'class_id' => $classB->id,
        'admission_number' => 'FAHAN-002-2025',
        'guardian_phone' => '0799887766',
    ], $institution->id);

    expect($studentA->id)->not->toBe($studentB->id);
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(2);
});

test('10. Double-click or rapid repeated request does not create two students', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    $payload = [
        'name' => 'Sudheys Abdinoor',
        'admission_number' => 'FAHAN-064-2025',
        'class_id' => $classA->id,
        'guardian_name' => 'Abdinoor Dabi',
        'guardian_phone' => '0722971610',
    ];

    $u1 = $saver->create($payload, $institution->id);
    $u2 = $saver->create($payload, $institution->id);

    expect($u1->id)->toBe($u2->id);
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(1);
});

test('11. Suggesting next admission number does not consume new numbers for matching existing students', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    // Student already enrolled with FAHAN-014-2025
    $saver->create([
        'name' => 'Sundus Abdinoor',
        'admission_number' => 'FAHAN-014-2025',
        'class_id' => $classA->id,
    ], $institution->id);

    $patternService = app(AdmissionNumberPatternService::class);
    $rows = [
        ['name' => 'Sundus Abdinoor', 'admission_number' => ''],
        ['name' => 'New Learner', 'admission_number' => ''],
    ];

    $suggested = $patternService->suggestForRows($institution->id, $rows);

    // Sundus reuses existing FAHAN-014-2025 instead of advancing to FAHAN-015-2025
    expect($suggested[0]['admission_number'])->toBe('FAHAN-014-2025');
    // New learner gets the next sequential number FAHAN-015-2025
    expect($suggested[1]['admission_number'])->toBe('FAHAN-015-2025');
});

test('12. One student cannot receive two active admissions in the same institution', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    $student = $saver->create([
        'name' => 'Kafiya Hussein',
        'class_id' => $classA->id,
        'admission_number' => 'FAHAN-030-2025',
    ], $institution->id);

    // Re-creating or re-enrolling this student
    $reEnrolled = $saver->create([
        'name' => 'Kafiya Hussein',
        'class_id' => $classA->id,
        'admission_number' => 'FAHAN-030-2025',
    ], $institution->id);

    expect($reEnrolled->id)->toBe($student->id);
    expect(StudentDetails::where('institution_id', $institution->id)->where('student_id', $student->id)->count())->toBe(1);
});

test('13. Merge command audit displays complete reconciliation report without modifying data', function () {
    [, $institution, $classA] = createIntegrityTestSchool();

    // Create 3 duplicate records for Anfal Mohamed with different admission numbers
    $s1 = User::create(['name' => 'Anfal Mohamed', 'email' => null, 'password' => bcrypt('123')]);
    $s1->assignRole('Student');
    $d1 = StudentDetails::create(['student_id' => $s1->id, 'institution_id' => $institution->id, 'class_id' => $classA->id, 'admission_number' => 'Fahan-037-2025']);

    $s2 = User::create(['name' => 'Anfal Mohamed', 'email' => null, 'password' => bcrypt('123')]);
    $s2->assignRole('Student');
    $d2 = StudentDetails::create(['student_id' => $s2->id, 'institution_id' => $institution->id, 'class_id' => $classA->id, 'admission_number' => 'Fahan-038-2025']);

    $s3 = User::create(['name' => 'Anfal Mohamed', 'email' => null, 'password' => bcrypt('123')]);
    $s3->assignRole('Student');
    $d3 = StudentDetails::create(['student_id' => $s3->id, 'institution_id' => $institution->id, 'class_id' => $classA->id, 'admission_number' => 'Fahan-039-2025']);

    // Run in audit mode
    Artisan::call('students:merge-duplicates', [
        '--institution' => $institution->id,
        '--audit' => true,
    ]);

    // Database remains untouched in audit mode
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(3);
});

test('14. Merge command merges duplicate cluster, reassigns records, and deactivates duplicate user', function () {
    [, $institution, $classA] = createIntegrityTestSchool();

    $s1 = User::create(['name' => 'Anfal Mohamed', 'email' => null, 'password' => bcrypt('123'), 'is_active' => true]);
    $s1->assignRole('Student');
    $d1 = StudentDetails::create(['student_id' => $s1->id, 'institution_id' => $institution->id, 'class_id' => $classA->id, 'admission_number' => 'Fahan-037-2025']);

    $s2 = User::create(['name' => 'Anfal Mohamed', 'email' => null, 'password' => bcrypt('123'), 'is_active' => true]);
    $s2->assignRole('Student');
    $d2 = StudentDetails::create(['student_id' => $s2->id, 'institution_id' => $institution->id, 'class_id' => $classA->id, 'admission_number' => 'Fahan-038-2025']);

    // Add an attendance record to s2 to test relational reassignment
    if (DB::getSchemaBuilder()->hasTable('attendances')) {
        DB::table('attendances')->insert([
            'student_id' => $s2->id,
            'institution_id' => $institution->id,
            'school_class_id' => $classA->id,
            'date' => '2026-10-06',
            'status' => 'present',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Run merge
    Artisan::call('students:merge-duplicates', [
        '--institution' => $institution->id,
    ]);

    // Exactly 1 active student remains
    $remaining = StudentDetails::where('institution_id', $institution->id)->get();
    expect($remaining)->toHaveCount(1);

    $keeper = $remaining->first();
    // Keeper should have the attendance reassigned
    if (DB::getSchemaBuilder()->hasTable('attendances')) {
        expect(DB::table('attendances')->where('student_id', $keeper->student_id)->count())->toBe(1);
    }

    // Duplicate user was purged
    $dupUserId = $s1->id === $keeper->student_id ? $s2->id : $s1->id;
    expect(User::find($dupUserId))->toBeNull();
});

test('15. Soft-deleted student details does not collide with active records', function () {
    [, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    $student = $saver->create([
        'name' => 'Legacy Student',
        'admission_number' => 'FAHAN-999-2025',
        'class_id' => $classA->id,
    ], $institution->id);

    $details = StudentDetails::where('student_id', $student->id)->firstOrFail();
    $details->delete(); // Soft delete

    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(0);
    expect(StudentDetails::withTrashed()->where('institution_id', $institution->id)->count())->toBe(1);
});

test('16. Bulk upload removeEmptyRows removes blank rows from entry table', function () {
    [$director] = createIntegrityTestSchool();

    $component = Livewire::actingAs($director)->test('student::upload');
    $component->call('addRows', 10);
    expect(count($component->get('rows')))->toBe(20);

    // Set 1 row with data
    $component->set('rows.0.name', 'Student With Name');

    $component->call('removeEmptyRows');
    expect(count($component->get('rows')))->toBe(1);
    expect($component->get('rows.0.name'))->toBe('Student With Name');
});

test('17. Bulk upload removeRows removes trailing rows', function () {
    [$director] = createIntegrityTestSchool();

    $component = Livewire::actingAs($director)->test('student::upload');
    $component->call('addRows', 15);
    expect(count($component->get('rows')))->toBe(25);

    $component->call('removeRows', 5);
    expect(count($component->get('rows')))->toBe(20);
});

test('18. Bulk upload deleteEnrolledStudent permanently deletes student from database', function () {
    [$director, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    $studentUser = $saver->create([
        'name' => 'To Be Deleted',
        'class_id' => $classA->id,
        'admission_number' => 'DEL-001',
    ], $institution->id);

    $details = StudentDetails::where('student_id', $studentUser->id)->firstOrFail();
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(1);

    $component = Livewire::actingAs($director)->test('student::upload');
    $component->set('studentToDeleteId', $details->id);
    $component->call('deleteEnrolledStudent');

    // Deleted from database
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(0);
    expect(User::find($studentUser->id))->toBeNull();
});

test('19. Bulk upload bulkDeleteEnrolledStudents deletes all selected students from database', function () {
    [$director, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    $s1 = $saver->create(['name' => 'Bulk Del 1', 'class_id' => $classA->id, 'admission_number' => 'DEL-101'], $institution->id);
    $s2 = $saver->create(['name' => 'Bulk Del 2', 'class_id' => $classA->id, 'admission_number' => 'DEL-102'], $institution->id);

    $d1 = StudentDetails::where('student_id', $s1->id)->firstOrFail();
    $d2 = StudentDetails::where('student_id', $s2->id)->firstOrFail();

    $component = Livewire::actingAs($director)->test('student::upload');
    $component->set('selectedEnrolledStudents', [$d1->id, $d2->id]);
    $component->call('bulkDeleteEnrolledStudents');

    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(0);
    expect(User::find($s1->id))->toBeNull();
    expect(User::find($s2->id))->toBeNull();
    expect($component->get('selectedEnrolledStudents'))->toBe([]);
});

test('20. Bulk upload deleteRowStudentFromDb deletes existing database student from draft row', function () {
    [$director, $institution, $classA] = createIntegrityTestSchool();
    $saver = app(SaveStudent::class);

    $s1 = $saver->create(['name' => 'Row Match Student', 'class_id' => $classA->id, 'admission_number' => 'ROW-001'], $institution->id);
    $d1 = StudentDetails::where('student_id', $s1->id)->firstOrFail();

    $component = Livewire::actingAs($director)->test('student::upload');
    $component->set('rows.0.name', 'Row Match Student');
    $component->set('rows.0.admission_number', 'ROW-001');

    // Calling removeRow triggers the check and finds existing DB student
    $component->call('removeRow', 0);
    expect($component->get('studentToDeleteId'))->toBe($d1->id);

    // Confirm deletion from DB
    $component->call('deleteRowStudentFromDb');
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(0);
    expect(User::find($s1->id))->toBeNull();
});

test('21. students:merge-duplicates with --class option merges duplicate names in that class even with different admission numbers', function () {
    [, $institution, $classA, $classB] = createIntegrityTestSchool();

    // 3 duplicate records for Sucdi Abdinoor in Grade 7 East (classA) with different admission numbers
    $s1 = User::create(['name' => 'Sucdi Abdinoor', 'email' => null, 'password' => bcrypt('123'), 'is_active' => true]);
    $s1->assignRole('Student');
    $d1 = StudentDetails::create(['student_id' => $s1->id, 'institution_id' => $institution->id, 'class_id' => $classA->id, 'admission_number' => 'Fahan-060-2025']);

    $s2 = User::create(['name' => 'Sucdi Abdinoor', 'email' => null, 'password' => bcrypt('123'), 'is_active' => true]);
    $s2->assignRole('Student');
    $d2 = StudentDetails::create(['student_id' => $s2->id, 'institution_id' => $institution->id, 'class_id' => $classA->id, 'admission_number' => 'Fahan-061-2025']);

    $s3 = User::create(['name' => 'Sucdi Abdinoor', 'email' => null, 'password' => bcrypt('123'), 'is_active' => true]);
    $s3->assignRole('Student');
    $d3 = StudentDetails::create(['student_id' => $s3->id, 'institution_id' => $institution->id, 'class_id' => $classA->id, 'admission_number' => 'Fahan-063-2025']);

    // Another student in Grade 8 East (classB) should not be affected
    $other = User::create(['name' => 'Other Student', 'email' => null, 'password' => bcrypt('123'), 'is_active' => true]);
    $other->assignRole('Student');
    StudentDetails::create(['student_id' => $other->id, 'institution_id' => $institution->id, 'class_id' => $classB->id, 'admission_number' => 'Fahan-070-2025']);

    // Run merge with --class option
    Artisan::call('students:merge-duplicates', [
        '--institution' => $institution->id,
        '--class' => $classA->name,
    ]);

    // Class A now has exactly 1 student with the earliest admission number Fahan-060-2025
    $classAStudents = StudentDetails::where('institution_id', $institution->id)->where('class_id', $classA->id)->get();
    expect($classAStudents)->toHaveCount(1)
        ->and($classAStudents->first()->admission_number)->toBe('Fahan-060-2025');

    // Total active students in institution is now 2 (Sucdi and Other)
    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(2);
});

test('22. Livewire upload component merges duplicate student names in selected class roster directly from UI', function () {
    [$director, $institution, $classA] = createIntegrityTestSchool();

    // 2 duplicate records for Sundus Abdinoor in classA with different admission numbers
    $s1 = User::create(['name' => 'Sundus Abdinoor', 'email' => null, 'password' => bcrypt('123'), 'is_active' => true]);
    $s1->assignRole('Student');
    $d1 = StudentDetails::create(['student_id' => $s1->id, 'institution_id' => $institution->id, 'class_id' => $classA->id, 'admission_number' => 'Fahan-059-2025']);

    $s2 = User::create(['name' => 'Sundus Abdinoor', 'email' => null, 'password' => bcrypt('123'), 'is_active' => true]);
    $s2->assignRole('Student');
    $d2 = StudentDetails::create(['student_id' => $s2->id, 'institution_id' => $institution->id, 'class_id' => $classA->id, 'admission_number' => 'Fahan-062-2025', 'guardian_phone' => '0722971610']);

    $component = Livewire::actingAs($director)->test('student::upload');
    $component->set('selectedClassId', $classA->id);

    // Detected duplicate count should be 1
    expect($component->get('totalDuplicatesInRoster'))->toBe(1);

    // Call UI merge action
    $component->call('mergeClassDuplicates');

    // Duplicates consolidated
    expect(StudentDetails::where('institution_id', $institution->id)->where('class_id', $classA->id)->count())->toBe(1);
    $keeper = StudentDetails::where('institution_id', $institution->id)->where('class_id', $classA->id)->firstOrFail();
    expect($keeper->admission_number)->toBe('Fahan-059-2025')
        ->and($keeper->guardian_phone)->toBe('0722971610'); // inherited from duplicate
    expect(User::find($s2->id))->toBeNull();
});
