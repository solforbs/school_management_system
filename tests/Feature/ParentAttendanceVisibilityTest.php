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
use Modules\Attendance\Services\AttendanceLogQuery;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * A school, a gate device, two families each with one enrolled child, and
 * check-in scans for both children.
 *
 * @return array{institution: Institution, parentA: User, childA: User, parentB: User, childB: User}
 */
function attendanceForTwoFamilies(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id, 'name' => 'Scan School', 'code' => 'SC-'.uniqid(),
        'type' => 'School', 'is_active' => true, 'status' => 'active', 'is_approved' => true,
    ]);
    $director->update(['active_institution_id' => $institution->id]);

    $zkteco = ZktecoDevice::create([
        'serial_number' => 'DEV-'.uniqid(), 'name' => 'Gate', 'status' => 'online',
        'last_activity_at' => now(), 'timezone' => 'UTC', 'options' => [],
    ]);
    Devices::create([
        'serial_number' => $zkteco->serial_number, 'institution_id' => $institution->id,
        'is_active' => true, 'zkteco_device_id' => $zkteco->id,
    ]);

    $family = function (string $tag) use ($institution, $zkteco): array {
        $parent = User::factory()->create();
        $parent->assignRole('Parent');

        $child = User::factory()->create(['name' => "Child {$tag}"]);
        $child->assignRole('Student');
        StudentDetails::create([
            'student_id' => $child->id, 'institution_id' => $institution->id, 'parent_id' => $parent->id,
            'admission_number' => "ADM-{$tag}-".uniqid(), 'enrollment_status' => 'active',
        ]);

        ZktecoUser::create([
            'pin' => (string) $child->id, 'name' => $child->name,
            'app_user_id' => $child->id, 'device_id' => $zkteco->id,
        ]);
        ZktecoAttendanceLog::create([
            'device_id' => $zkteco->id, 'pin' => (string) $child->id,
            'status' => AttendanceStatus::CheckIn, 'verify_mode' => VerifyMode::Fingerprint, 'work_code' => '0',
            'occurred_at' => now()->subMinutes(5), 'recorded_at' => now()->subMinutes(5),
        ]);

        return [$parent->refresh(), $child->refresh()];
    };

    [$parentA, $childA] = $family('Alpha');
    [$parentB, $childB] = $family('Bravo');

    return compact('institution', 'parentA', 'childA', 'parentB', 'childB');
}

test('a parent opening the attendance index sees only their own child', function () {
    ['parentA' => $parentA, 'childA' => $childA, 'childB' => $childB] = attendanceForTwoFamilies();

    Livewire::actingAs($parentA)
        ->test('attendance::index')
        ->assertOk()
        ->assertSee($childA->name)
        ->assertDontSee($childB->name);
});

test('a parent can load the attendance index over HTTP and sees their child', function () {
    ['parentA' => $parentA, 'childA' => $childA, 'childB' => $childB] = attendanceForTwoFamilies();

    $this->actingAs($parentA)->get(route('attendance.index'))
        ->assertOk()
        ->assertSee($childA->name)
        ->assertDontSee($childB->name);
});

test('a guardian whose Parent role was never set is still scoped to their own child', function () {
    ['parentA' => $parentA, 'childA' => $childA, 'childB' => $childB] = attendanceForTwoFamilies();

    // The account keeps its student_details.parent_id link but loses the
    // role - the production case where the log viewer showed them the
    // whole school.
    $parentA->syncRoles([]);
    $parentA->givePermissionTo('view attendance');

    Livewire::actingAs($parentA->refresh())
        ->test('attendance::index')
        ->assertOk()
        ->assertSee($childA->name)
        ->assertDontSee($childB->name);
});

test('a parent has no access to lesson attendance', function () {
    ['parentA' => $parentA] = attendanceForTwoFamilies();

    expect($parentA->can('view lesson'))->toBeFalse();

    $this->actingAs($parentA)->get(route('lesson.index'))->assertForbidden();
    $this->actingAs($parentA)->get(route('lesson.reports.index'))->assertForbidden();
});

test('the query scope narrows a guardian to their child and blocks a stranger', function () {
    ['institution' => $institution, 'parentA' => $parentA, 'childA' => $childA] = attendanceForTwoFamilies();

    // A guardian: only their child's pin, and only their child's institution.
    expect(AttendanceLogQuery::visiblePins($parentA))->toBe([(string) $childA->id])
        ->and(AttendanceLogQuery::allowedInstitutionIds($parentA)->all())->toBe([$institution->id]);

    // A stranger: no pins, no institutions, and build() refuses outright.
    $stranger = User::factory()->create();

    expect(AttendanceLogQuery::visiblePins($stranger))->toBe([])
        ->and(AttendanceLogQuery::allowedInstitutionIds($stranger)->all())->toBe([]);

    expect(fn () => AttendanceLogQuery::build($institution->id, $stranger))
        ->toThrow(HttpException::class);
});
