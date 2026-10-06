<?php

use App\Models\Devices;
use App\Models\User;
use App\Services\ZKTecoUserSyncService;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Actions\ImportStudents;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function createBulkSyncEnvironment(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Green Valley Academy '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    $serial = 'DEV-SN-'.uniqid();

    $device = Devices::create([
        'institution_id' => $institution->id,
        'serial_number' => $serial,
        'device_name' => 'Main Gate Terminal',
        'is_active' => true,
    ]);

    $zkDevice = ZktecoDevice::create([
        'serial_number' => $serial,
        'name' => 'Main Gate Terminal',
        'status' => 'online',
    ]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 East',
        'level' => 'Junior Secondary',
    ]);

    return [$director, $institution, $device, $class, $zkDevice];
}

test('ZKTecoUserSyncService bulkSyncStudents queues device commands and marks students as synced', function () {
    [$director, $institution, $device, $class, $zkDevice] = createBulkSyncEnvironment();

    // Create 3 students
    $students = [];
    for ($i = 1; $i <= 3; $i++) {
        $student = User::factory()->create(['name' => "Learner {$i}", 'zkteco_synced' => false]);
        $student->assignRole('Student');

        StudentDetails::create([
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'class_id' => $class->id,
            'admission_number' => "ADM-00{$i}",
            'student_number' => "STU-00{$i}",
            'is_active' => true,
        ]);

        $students[] = $student;
    }

    $service = app(ZKTecoUserSyncService::class);
    $result = $service->bulkSyncStudents(collect($students)->pluck('id'), $institution->id);

    expect($result['synced'])->toBe(3);
    expect($result['devices_count'])->toBe(1);

    foreach ($students as $student) {
        expect($student->fresh()->zkteco_synced)->toBeTrue();
        expect($student->fresh()->zkteco_synced_at)->not->toBeNull();
    }

    // Verify commands queued in DB
    $commands = ZktecoDeviceCommand::where('device_id', $zkDevice->id)->get();
    expect($commands->count())->toBe(3);
    expect($commands->first()->command_content)->toContain('DATA UPDATE USERINFO');
    expect($commands->first()->command_content)->toContain('Name=Learner 1');
});

test('ImportStudents import returns created_ids for enrolled students', function () {
    [$director, $institution, $device, $class, $zkDevice] = createBulkSyncEnvironment();

    $rows = [
        [
            'name' => 'John Doe',
            'admission_number' => 'ADM-101',
            'student_number' => 'STU-101',
            'class_name' => 'Grade 7 East',
            'gender' => 'male',
            'guardian_name' => 'Jane Doe',
            'guardian_phone' => '0712345678',
            'enrollment_status' => 'active',
        ],
        [
            'name' => 'Mary Smith',
            'admission_number' => 'ADM-102',
            'student_number' => 'STU-102',
            'class_name' => 'Grade 7 East',
            'gender' => 'female',
            'guardian_name' => 'Peter Smith',
            'guardian_phone' => '0723456789',
            'enrollment_status' => 'active',
        ],
    ];

    $importer = app(ImportStudents::class);
    $result = $importer->import($rows, $institution->id);

    expect($result['created'])->toBe(2);
    expect($result['errors'])->toBeEmpty();
    expect($result['created_ids'])->toHaveCount(2);

    $createdUsers = User::whereIn('id', $result['created_ids'])->get();
    expect($createdUsers->count())->toBe(2);
});

test('Student upload Livewire component queues biometric sync when enabled', function () {
    [$director, $institution, $device, $class, $zkDevice] = createBulkSyncEnvironment();

    $this->actingAs($director);

    Livewire::test('student::upload')
        ->set('syncToBiometrics', true)
        ->set('rows', [
            [
                'name' => 'Alice Wonder',
                'admission_number' => 'ADM-201',
                'student_number' => 'STU-201',
                'class_name' => 'Grade 7 East',
                'gender' => 'female',
                'guardian_name' => 'Bob Wonder',
                'guardian_phone' => '0733333333',
                'enrollment_status' => 'active',
            ],
        ])
        ->call('save')
        ->assertRedirect(route('student.index'));

    $newStudent = User::where('name', 'Alice Wonder')->first();
    expect($newStudent)->not->toBeNull();
    expect($newStudent->zkteco_synced)->toBeTrue();

    // Verify command was queued for the active device
    $queued = ZktecoDeviceCommand::where('device_id', $zkDevice->id)
        ->where('command_content', 'like', '%Alice Wonder%')
        ->count();
    expect($queued)->toBe(1);
});

test('Student directory Livewire component filters by biometric status and allows bulk sync', function () {
    [$director, $institution, $device, $class] = createBulkSyncEnvironment();

    // Student 1: already synced
    $student1 = User::factory()->create(['name' => 'Synced Student', 'zkteco_synced' => true]);
    $student1->assignRole('Student');
    StudentDetails::create([
        'student_id' => $student1->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-901',
    ]);

    // Student 2: unsynced
    $student2 = User::factory()->create(['name' => 'Unsynced Student', 'zkteco_synced' => false]);
    $student2->assignRole('Student');
    StudentDetails::create([
        'student_id' => $student2->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-902',
    ]);

    $this->actingAs($director);

    // Test filter 'synced'
    Livewire::test('student::index')
        ->set('biometric', 'synced')
        ->assertSee('Synced Student')
        ->assertDontSee('Unsynced Student');

    // Test filter 'not_synced'
    Livewire::test('student::index')
        ->set('biometric', 'not_synced')
        ->assertSee('Unsynced Student')
        ->assertDontSee('Synced Student');

    // Test 1-click bulk sync action
    Livewire::test('student::index')
        ->call('syncUnsyncedToBiometrics');

    expect($student2->fresh()->zkteco_synced)->toBeTrue();
});

test('Student upload Livewire component restores and discards session draft rows properly', function () {
    [$director, $institution, $device, $class] = createBulkSyncEnvironment();

    $this->actingAs($director);

    $component = Livewire::test('student::upload');

    // Test restoreDraft with partial row data (as parsed from localStorage)
    $draftRows = [
        [
            'name' => 'Restored John Doe',
            'admission_number' => 'ADM-RESTORE-1',
        ],
        [
            'name' => 'Restored Jane Doe',
            'admission_number' => 'ADM-RESTORE-2',
            'gender' => 'female',
        ],
    ];

    $component->call('restoreDraft', $draftRows);

    $rows = $component->get('rows');
    expect(count($rows))->toBeGreaterThanOrEqual(10);
    expect($rows[0]['name'])->toBe('Restored John Doe');
    expect($rows[0]['admission_number'])->toBe('ADM-RESTORE-1');
    expect($rows[0])->toHaveKeys(['name', 'admission_number', 'student_number', 'class_name', 'gender', 'guardian_name', 'guardian_phone', 'enrollment_status']);
    expect($rows[1]['name'])->toBe('Restored Jane Doe');
    expect($rows[1]['gender'])->toBe('female');

    // Test discardDraft clears all rows back to blank
    $component->call('discardDraft');
    $component->assertDispatched('clear-bulk-draft');

    $clearedRows = $component->get('rows');
    expect(count($clearedRows))->toBe(10);
    expect($clearedRows[0]['name'])->toBe('');
    expect($clearedRows[1]['name'])->toBe('');
});

test('Enrolled roster Excel Bulk Edit mode allows in-place editing of admission numbers and parent details', function () {
    [$director, $institution, $device, $class] = createBulkSyncEnvironment();

    $student = User::factory()->create(['name' => 'Original Name']);
    $student->assignRole('Student');
    $details = StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-OLD-001',
        'student_number' => 'UPI-OLD-001',
        'gender' => 'male',
        'guardian_name' => 'Old Guardian',
        'guardian_phone' => '0711111111',
    ]);

    $this->actingAs($director);

    $component = Livewire::test('student::upload')
        ->set('selectedClassId', $class->id);

    // Enter Excel Bulk Edit mode
    $component->call('startRosterEditMode');
    expect($component->get('isEditingRoster'))->toBeTrue();

    $rosterEdits = $component->get('rosterEdits');
    expect($rosterEdits)->toHaveKey($details->id);
    expect($rosterEdits[$details->id]['name'])->toBe('Original Name');
    expect($rosterEdits[$details->id]['admission_number'])->toBe('ADM-OLD-001');

    // Edit fields in Excel mode: change admission number, parent name, and phone
    $rosterEdits[$details->id]['name'] = 'Updated Student Name';
    $rosterEdits[$details->id]['admission_number'] = '1042';
    $rosterEdits[$details->id]['guardian_name'] = 'Jane Doe (Mother)';
    $rosterEdits[$details->id]['guardian_phone'] = '0799887766';

    $component->set('rosterEdits', $rosterEdits)
        ->call('saveRosterEdits');

    expect($component->get('isEditingRoster'))->toBeFalse();
    expect($component->get('rosterErrors'))->toBeEmpty();

    // Verify database updates
    expect($student->fresh()->name)->toBe('Updated Student Name');
    $freshDetails = $details->fresh();
    expect($freshDetails->admission_number)->toBe('1042');
    expect($freshDetails->guardian_name)->toBe('Jane Doe (Mother)');
    expect($freshDetails->guardian_phone)->toBe('0799887766');
});

test('Enrolled roster Excel Bulk Edit mode validates duplicate admission numbers', function () {
    [$director, $institution, $device, $class] = createBulkSyncEnvironment();

    // Create another existing student with ADM-TAKEN
    $existing = User::factory()->create(['name' => 'Existing Student']);
    $existing->assignRole('Student');
    StudentDetails::create([
        'student_id' => $existing->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-TAKEN',
    ]);

    // Student to edit
    $student = User::factory()->create(['name' => 'Student To Edit']);
    $student->assignRole('Student');
    $details = StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-ORIGINAL',
    ]);

    $this->actingAs($director);

    $component = Livewire::test('student::upload')
        ->set('selectedClassId', $class->id)
        ->call('startRosterEditMode');

    $rosterEdits = $component->get('rosterEdits');
    // Try to set admission number to one that is already taken
    $rosterEdits[$details->id]['admission_number'] = 'ADM-TAKEN';

    $component->set('rosterEdits', $rosterEdits)
        ->call('saveRosterEdits');

    // Should remain in edit mode with validation error
    expect($component->get('isEditingRoster'))->toBeTrue();
    $errors = $component->get('rosterErrors');
    expect($errors)->toHaveKey($details->id);
    expect($errors[$details->id])->toHaveKey('admission_number');
    expect($details->fresh()->admission_number)->toBe('ADM-ORIGINAL');
});

test('Enrolled roster allows direct in-place field editing without action buttons', function () {
    [$director, $institution, $device, $class, $zkDevice] = createBulkSyncEnvironment();

    $student = User::factory()->create(['name' => 'Initial Student Name', 'zkteco_synced' => false]);
    $student->assignRole('Student');
    $details = StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-INPLACE-1',
        'student_number' => 'UPI-INPLACE-1',
        'gender' => 'male',
        'guardian_name' => 'Initial Guardian',
        'guardian_phone' => '0700000000',
    ]);

    $this->actingAs($director);

    $component = Livewire::test('student::upload')
        ->set('selectedClassId', $class->id)
        ->set('syncToBiometrics', true);

    // Update guardian name directly
    $component->call('updateEnrolledField', $details->id, 'guardian_name', 'Fatima Zahra');
    expect($details->fresh()->guardian_name)->toBe('Fatima Zahra');

    // Update guardian phone directly
    $component->call('updateEnrolledField', $details->id, 'guardian_phone', '0712999888');
    expect($details->fresh()->guardian_phone)->toBe('0712999888');

    // Update admission number directly
    $component->call('updateEnrolledField', $details->id, 'admission_number', 'ADM-NEW-777');
    expect($details->fresh()->admission_number)->toBe('ADM-NEW-777');

    // Update gender directly
    $component->call('updateEnrolledField', $details->id, 'gender', 'female');
    expect($details->fresh()->gender)->toBe('female');

    // Update student name directly (triggers biometric sync if enabled)
    $component->call('updateEnrolledField', $details->id, 'name', 'Fatima Mohamed');
    expect($student->fresh()->name)->toBe('Fatima Mohamed');
    expect($student->fresh()->zkteco_synced)->toBeTrue();
});
