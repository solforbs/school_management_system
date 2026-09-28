<?php

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * A Director with an active institution and one class, ready to upload into.
 *
 * @return array{0: User, 1: Institution, 2: SchoolClass}
 */
function directorForUpload(): array
{
    $plan = Plan::create([
        'name' => 'Upload '.uniqid(),
        'slug' => 'upload-'.uniqid(),
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
        'name' => 'Upload School '.uniqid(),
        'code' => 'US-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
        'subscription_plan' => $plan->id,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 3',
        'level' => 'Primary',
    ]);

    return [$director->refresh(), $institution, $class];
}

/**
 * An xlsx in the same layout the student directory export produces.
 *
 * @param  array<int, array<int, string>>  $rows
 */
function uploadSheet(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(array_merge(
        [['Student Name', 'Admission No', 'Student No', 'Class', 'Gender', 'Guardian', 'Guardian Phone', 'Status']],
        $rows,
    ));

    $path = tempnam(sys_get_temp_dir(), 'students').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return UploadedFile::fake()->createWithContent('students.xlsx', file_get_contents($path));
}

test('the upload page renders for staff who can create students', function () {
    [$director] = directorForUpload();

    $this->actingAs($director)->get(route('student.upload'))
        ->assertOk()
        ->assertSee('Upload Student Records')
        ->assertSee('Save All Students');
});

test('the upload page is refused without the create student permission', function () {
    [, $institution] = directorForUpload();

    $student = User::factory()->create(['active_institution_id' => $institution->id]);
    $student->assignRole('Student');

    $this->actingAs($student->refresh())->get(route('student.upload'))->assertForbidden();
});

test('rows typed into the sheet are saved as student records', function () {
    [$director, $institution, $class] = directorForUpload();

    Livewire::actingAs($director)
        ->test('student::upload')
        ->set('rows.0', [
            'name' => 'Student name',
            'admission_number' => 'fhn-1-2025',
            'student_number' => 'fhn-1-2025',
            'class_name' => 'Grade 3',
            'gender' => 'male',
            'guardian_name' => '',
            'guardian_phone' => '',
            'enrollment_status' => 'active',
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('student.index'));

    $details = StudentDetails::where('admission_number', 'fhn-1-2025')->firstOrFail();

    expect($details->institution_id)->toBe($institution->id)
        ->and((int) $details->class_id)->toBe($class->id)
        ->and($details->gender)->toBe('male')
        ->and($details->enrollment_status)->toBe('active')
        ->and($details->student?->name)->toBe('Student name')
        ->and($details->student?->hasRole('Student'))->toBeTrue();
});

test('an uploaded xlsx fills the sheet with its rows', function () {
    [$director] = directorForUpload();

    $file = uploadSheet([
        ['Student name', 'fhn-1-2025', 'fhn-1-2025', 'Grade 3', 'Male', 'Not Listed', 'N/A', 'Active'],
        ['Amina Yusuf', 'fhn-2-2025', '', 'Grade 3', 'Female', 'Halima Yusuf', '0700111222', 'Active'],
    ]);

    Livewire::actingAs($director)
        ->test('student::upload')
        ->set('file', $file)
        ->assertHasNoErrors()
        ->assertSet('rows.0.name', 'Student name')
        ->assertSet('rows.0.admission_number', 'fhn-1-2025')
        ->assertSet('rows.0.class_name', 'Grade 3')
        ->assertSet('rows.0.gender', 'male')
        // Export placeholders come back as blanks, not literal "N/A".
        ->assertSet('rows.0.guardian_name', '')
        ->assertSet('rows.0.guardian_phone', '')
        ->assertSet('rows.1.name', 'Amina Yusuf')
        ->assertSet('rows.1.guardian_phone', '0700111222');
});

test('an uploaded file can be saved end to end, keeping failed rows visible', function () {
    [$director, $institution] = directorForUpload();

    $file = uploadSheet([
        ['Student name', 'fhn-1-2025', 'fhn-1-2025', 'Grade 3', 'Male', 'Not Listed', 'N/A', 'Active'],
        ['Broken Row', 'fhn-9-2025', '', 'No Such Class', 'Male', '', '', 'Active'],
    ]);

    Livewire::actingAs($director)
        ->test('student::upload')
        ->set('file', $file)
        ->call('save')
        ->assertSet('created', 1)
        // The failed row stays in the grid with its reason attached.
        ->assertSet('rows.0.name', 'Broken Row')
        ->assertSee('was not found in your institution');

    expect(StudentDetails::where('institution_id', $institution->id)->count())->toBe(1)
        ->and(StudentDetails::where('admission_number', 'fhn-9-2025')->exists())->toBeFalse();
});

test('generated login emails stay unique across duplicate names', function () {
    [$director, $institution] = directorForUpload();

    $component = Livewire::actingAs($director)->test('student::upload');

    foreach ([0, 1] as $i) {
        $component->set("rows.{$i}", [
            'name' => 'Same Name',
            'admission_number' => '',
            'student_number' => '',
            'class_name' => '',
            'gender' => '',
            'guardian_name' => '',
            'guardian_phone' => '',
            'enrollment_status' => '',
        ]);
    }

    $component->call('save')->assertHasNoErrors();

    expect($component->get('rowErrors'))->toBe([]);

    $emails = StudentDetails::where('institution_id', $institution->id)
        ->with('student')
        ->get()
        ->map(fn ($d) => $d->student?->email);

    expect($emails)->toHaveCount(2)
        ->and($emails->unique())->toHaveCount(2);
});

test('student create form renders bulk upload button and quick switch link', function () {
    [$director] = directorForUpload();

    $this->actingAs($director)
        ->get(route('student.create'))
        ->assertOk()
        ->assertSee(route('student.upload'))
        ->assertSee('Bulk Upload')
        ->assertSee('Need to enroll in bulk?');
});

test('bulk upload page renders single student navigation button', function () {
    [$director] = directorForUpload();

    $this->actingAs($director)
        ->get(route('student.upload'))
        ->assertOk()
        ->assertSee(route('student.create'))
        ->assertSee('Single Student');
});

test('import rejects duplicate admission numbers in the same batch with a friendly message', function () {
    [$director] = directorForUpload();

    $component = Livewire::actingAs($director)->test('student::upload');

    $component->set('rows.0', [
        'name' => 'Alice First',
        'admission_number' => 'GVA-999-2026',
        'student_number' => '',
        'class_name' => '',
        'gender' => 'female',
        'guardian_name' => '',
        'guardian_phone' => '',
        'enrollment_status' => 'active',
    ]);

    $component->set('rows.1', [
        'name' => 'Bob Second',
        'admission_number' => 'GVA-999-2026',
        'student_number' => '',
        'class_name' => '',
        'gender' => 'male',
        'guardian_name' => '',
        'guardian_phone' => '',
        'enrollment_status' => 'active',
    ]);

    $component->call('save')->assertHasNoErrors();

    expect($component->get('created'))->toBe(1);
    $errors = $component->get('rowErrors');
    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('Admission number "GVA-999-2026" is duplicated in row 1')
        ->and($errors[0])->not->toContain('SQLSTATE')
        ->and($errors[0])->not->toContain('Integrity constraint violation');
});

test('import rejects admission number already registered in database with a friendly message', function () {
    [$director, $institution] = directorForUpload();

    // Create an existing student with the target admission number
    $existingUser = User::factory()->create();
    $existingUser->assignRole('Student');
    StudentDetails::create([
        'student_id' => $existingUser->id,
        'institution_id' => $institution->id,
        'admission_number' => 'GVA-1-2026',
        'is_active' => true,
        'enrollment_status' => 'active',
    ]);

    $component = Livewire::actingAs($director)->test('student::upload');

    $component->set('rows.0', [
        'name' => 'Duplicate Student',
        'admission_number' => 'GVA-1-2026',
        'student_number' => '',
        'class_name' => '',
        'gender' => 'male',
        'guardian_name' => '',
        'guardian_phone' => '',
        'enrollment_status' => 'active',
    ]);

    $component->call('save')->assertHasNoErrors();

    expect($component->get('created'))->toBe(0);
    $errors = $component->get('rowErrors');
    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('Admission number "GVA-1-2026" is already registered to an existing student in the system')
        ->and($errors[0])->not->toContain('SQLSTATE')
        ->and($errors[0])->not->toContain('1062')
        ->and($errors[0])->not->toContain('Integrity constraint violation');
});

test('import rejects student number already registered in database with a friendly message', function () {
    [$director, $institution] = directorForUpload();

    $existingUser = User::factory()->create();
    $existingUser->assignRole('Student');
    StudentDetails::create([
        'student_id' => $existingUser->id,
        'institution_id' => $institution->id,
        'admission_number' => 'ADM-ORIG-001',
        'student_number' => 'STU-10492',
        'is_active' => true,
        'enrollment_status' => 'active',
    ]);

    $component = Livewire::actingAs($director)->test('student::upload');

    $component->set('rows.0', [
        'name' => 'Another Student',
        'admission_number' => '',
        'student_number' => 'STU-10492',
        'class_name' => '',
        'gender' => 'female',
        'guardian_name' => '',
        'guardian_phone' => '',
        'enrollment_status' => 'active',
    ]);

    $component->call('save')->assertHasNoErrors();

    expect($component->get('created'))->toBe(0);
    $errors = $component->get('rowErrors');
    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('Student number "STU-10492" is already registered to an existing student in the system')
        ->and($errors[0])->not->toContain('SQLSTATE');
});

test('class stats and live roster calculate counts and latest admission accurately', function () {
    [$director, $institution, $class] = directorForUpload();

    // Enroll 2 students into this class
    $boy = User::factory()->create(['name' => 'Charlie Brown']);
    $boy->assignRole('Student');
    StudentDetails::create([
        'student_id' => $boy->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'GVA-10-2026',
        'gender' => 'male',
        'is_active' => true,
    ]);

    $girl = User::factory()->create(['name' => 'Diana Prince']);
    $girl->assignRole('Student');
    StudentDetails::create([
        'student_id' => $girl->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'GVA-11-2026',
        'gender' => 'female',
        'is_active' => true,
    ]);

    $component = Livewire::actingAs($director)
        ->test('student::upload')
        ->set('selectedClassId', $class->id);

    $stats = $component->get('classStats');
    expect($stats['total'])->toBe(2)
        ->and($stats['male'])->toBe(1)
        ->and($stats['female'])->toBe(1)
        ->and($stats['latest_admission'])->toBe('GVA-11-2026');

    $roster = $component->get('classRoster');
    expect($roster)->toHaveCount(2);

    // Search roster
    $component->set('rosterSearch', 'Diana');
    $filteredRoster = $component->get('classRoster');
    expect($filteredRoster)->toHaveCount(1)
        ->and($filteredRoster->first()->admission_number)->toBe('GVA-11-2026');
});

test('newly added rows inherit the active target class name', function () {
    [$director, $institution, $class] = directorForUpload();

    $component = Livewire::actingAs($director)
        ->test('student::upload')
        ->set('selectedClassId', $class->id);

    // Add 5 more rows
    $component->call('addRows', 5);

    $rows = $component->get('rows');
    expect(count($rows))->toBe(15);
    // Last row should inherit the class name
    expect($rows[14]['class_name'])->toBe($class->name);
});

test('displays helpful onboarding empty state when institution has zero classes', function () {
    [$director, $institution] = directorForUpload();
    // Delete all classes for this school
    SchoolClass::where('institution_id', $institution->id)->delete();

    $this->actingAs($director)
        ->get(route('student.upload'))
        ->assertOk()
        ->assertSee('No School Classes Found')
        ->assertSee('Create First Class');
});

test('draft rows can be restored into the grid', function () {
    [$director] = directorForUpload();

    $component = Livewire::actingAs($director)->test('student::upload');

    $draft = [
        [
            'name' => 'Restored Student',
            'admission_number' => 'RESTORED-1',
            'student_number' => '',
            'class_name' => 'Grade 3',
            'gender' => 'male',
            'guardian_name' => 'Parent Name',
            'guardian_phone' => '0711000000',
            'enrollment_status' => 'active',
        ],
    ];

    $component->call('restoreDraft', $draft);

    $rows = $component->get('rows');
    expect($rows[0]['name'])->toBe('Restored Student')
        ->and($rows[0]['admission_number'])->toBe('RESTORED-1');
});

test('switching class without unsaved entries switches immediately', function () {
    [$director, $institution, $classA] = directorForUpload();
    $classB = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 4',
        'level' => 'Primary',
    ]);

    $component = Livewire::actingAs($director)
        ->test('student::upload')
        ->assertSet('selectedClassId', $classA->id);

    // Switch to Class B with empty rows
    $component->call('onClassSelectChange', $classB->id)
        ->assertSet('selectedClassId', $classB->id)
        ->assertSet('pendingClassId', null);
});

test('attempting to switch class with unsaved student rows sets pending class', function () {
    [$director, $institution, $classA] = directorForUpload();
    $classB = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 4',
        'level' => 'Primary',
    ]);

    $component = Livewire::actingAs($director)
        ->test('student::upload')
        ->set('rows.0.name', 'Unsaved Student')
        ->set('rows.0.admission_number', 'UNSAVED-01');

    expect($component->instance()->hasUnsavedChanges())->toBeTrue();

    // Trigger class change
    $component->call('onClassSelectChange', $classB->id);

    // Should NOT have changed selectedClassId yet
    expect($component->get('selectedClassId'))->toBe($classA->id)
        ->and($component->get('pendingClassId'))->toBe($classB->id);

    // Cancel switch keeps current class
    $component->call('cancelSwitchClass');
    expect($component->get('selectedClassId'))->toBe($classA->id)
        ->and($component->get('pendingClassId'))->toBeNull();
});

test('discarding and switching class clears unsaved entries and switches target class', function () {
    [$director, $institution, $classA] = directorForUpload();
    $classB = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 4',
        'level' => 'Primary',
    ]);

    $component = Livewire::actingAs($director)
        ->test('student::upload')
        ->set('rows.0.name', 'Unsaved Student')
        ->set('rows.0.admission_number', 'UNSAVED-01')
        ->call('onClassSelectChange', $classB->id)
        ->call('discardAndSwitchClass');

    // Should now be on Class B and rows cleared
    expect($component->get('selectedClassId'))->toBe($classB->id)
        ->and($component->get('pendingClassId'))->toBeNull();

    $rows = $component->get('rows');
    expect($rows[0]['name'])->toBe('')
        ->and($rows[0]['class_name'])->toBe($classB->name);
});

test('saving and switching class saves students to current class and switches target class', function () {
    [$director, $institution, $classA] = directorForUpload();
    $classB = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 4',
        'level' => 'Primary',
    ]);

    $component = Livewire::actingAs($director)
        ->test('student::upload')
        ->set('rows.0.name', 'Saved Student')
        ->set('rows.0.admission_number', 'SAVED-01')
        ->set('rows.0.class_name', $classA->name)
        ->call('onClassSelectChange', $classB->id)
        ->call('saveAndSwitchClass');

    // Student should be saved in DB for Class A
    $details = StudentDetails::where('admission_number', 'SAVED-01')->first();
    expect($details)->not->toBeNull()
        ->and((int) $details->class_id)->toBe((int) $classA->id);

    // And component should have switched to Class B
    expect($component->get('selectedClassId'))->toBe($classB->id);
});

test('discardDraft dispatches clear-bulk-draft event', function () {
    [$director] = directorForUpload();

    Livewire::actingAs($director)->test('student::upload')
        ->call('discardDraft')
        ->assertDispatched('clear-bulk-draft');
});
