<?php

use App\Models\Devices;
use App\Models\User;
use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Enums\VerifyMode;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * A director, a device, a student mapped onto it, and $count attendance
 * scans for that student.
 *
 * @return array{0: User, 1: array<int, int>} the director and the log ids
 */
function seedAttendance(int $count = 5): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id, 'name' => 'Scan School', 'code' => 'SC-'.uniqid(),
        'type' => 'School', 'is_active' => true, 'status' => 'active', 'is_approved' => true,
    ]);
    $director->update(['active_institution_id' => $institution->id]);

    $student = User::factory()->create(['name' => 'Scanned Student']);
    $student->assignRole('Student');
    StudentDetails::create([
        'student_id' => $student->id, 'institution_id' => $institution->id,
        'admission_number' => 'ADM-'.uniqid(), 'enrollment_status' => 'active',
    ]);

    $zkteco = ZktecoDevice::create([
        'serial_number' => 'DEV-'.uniqid(), 'name' => 'Gate', 'status' => 'online',
        'last_activity_at' => now(), 'timezone' => 'UTC', 'options' => [],
    ]);
    Devices::create([
        'serial_number' => $zkteco->serial_number, 'institution_id' => $institution->id,
        'is_active' => true, 'zkteco_device_id' => $zkteco->id,
    ]);
    ZktecoUser::create([
        'pin' => (string) $student->id, 'name' => $student->name,
        'app_user_id' => $student->id, 'device_id' => $zkteco->id,
    ]);

    $ids = [];
    foreach (range(1, $count) as $i) {
        $ids[] = ZktecoAttendanceLog::create([
            'device_id' => $zkteco->id,
            'pin' => (string) $student->id,
            'status' => AttendanceStatus::CheckIn,
            'verify_mode' => VerifyMode::Fingerprint,
            'work_code' => '0',
            'occurred_at' => now()->subMinutes($i),
            'recorded_at' => now()->subMinutes($i),
        ])->id;
    }

    return [$director->refresh(), $ids];
}

test('deleting a single record removes it from the database', function () {
    [$director, $ids] = seedAttendance(4);

    Livewire::actingAs($director)
        ->test('attendance::index')
        ->call('confirmDelete', $ids[0])
        ->call('deleteLog')
        ->assertHasNoErrors();

    expect(ZktecoAttendanceLog::find($ids[0]))->toBeNull()
        ->and(ZktecoAttendanceLog::whereKey($ids)->count())->toBe(3);
});

test('deleting selected records removes exactly those rows', function () {
    [$director, $ids] = seedAttendance(6);
    $toDelete = array_slice($ids, 0, 3);

    Livewire::actingAs($director)
        ->test('attendance::index')
        ->set('selected', $toDelete)
        ->call('deleteSelected')
        ->assertHasNoErrors()
        ->assertSet('selected', []);

    expect(ZktecoAttendanceLog::whereKey($toDelete)->count())->toBe(0)
        ->and(ZktecoAttendanceLog::whereKey($ids)->count())->toBe(3);
});

test('delete all wipes every record for the institution, past the current page', function () {
    [$director, $ids] = seedAttendance(20); // more than one page of 15

    Livewire::actingAs($director)
        ->test('attendance::index')
        ->call('deleteAll')
        ->assertHasNoErrors();

    expect(ZktecoAttendanceLog::whereKey($ids)->count())->toBe(0);
});

test('delete all honours the active filters', function () {
    [$director, $ids] = seedAttendance(0);

    $student = ZktecoUser::firstOrFail();
    $old = ZktecoAttendanceLog::create([
        'device_id' => $student->device_id, 'pin' => $student->pin,
        'status' => AttendanceStatus::CheckIn, 'verify_mode' => VerifyMode::Fingerprint, 'work_code' => '0',
        'occurred_at' => now()->subDays(30), 'recorded_at' => now()->subDays(30),
    ]);
    $recent = ZktecoAttendanceLog::create([
        'device_id' => $student->device_id, 'pin' => $student->pin,
        'status' => AttendanceStatus::CheckIn, 'verify_mode' => VerifyMode::Fingerprint, 'work_code' => '0',
        'occurred_at' => now(), 'recorded_at' => now(),
    ]);

    Livewire::actingAs($director)
        ->test('attendance::index')
        ->set('from', now()->subDays(2)->toDateString())
        ->call('deleteAll')
        ->assertHasNoErrors();

    expect(ZktecoAttendanceLog::find($old->id))->not->toBeNull()
        ->and(ZktecoAttendanceLog::find($recent->id))->toBeNull();
});

test('a user who may view but not delete cannot delete', function () {
    [$director, $ids] = seedAttendance(3);

    // Keep read access, drop the rest.
    $director->syncRoles([]);
    $director->givePermissionTo('view attendance');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Livewire::actingAs($director)
        ->test('attendance::index')
        ->set('selected', $ids)
        ->call('deleteSelected')
        ->assertForbidden();

    expect(ZktecoAttendanceLog::whereKey($ids)->count())->toBe(3);
});
