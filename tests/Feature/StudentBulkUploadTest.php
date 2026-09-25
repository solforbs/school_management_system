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
        'name' => 'Upload ' . uniqid(),
        'slug' => 'upload-' . uniqid(),
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
        'name' => 'Upload School ' . uniqid(),
        'code' => 'US-' . uniqid(),
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

    $path = tempnam(sys_get_temp_dir(), 'students') . '.xlsx';
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
        ->map(fn($d) => $d->student?->email);

    expect($emails)->toHaveCount(2)
        ->and($emails->unique())->toHaveCount(2);
});
