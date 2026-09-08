<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * @return array{0: User, 1: Institution}
 */
function syncResponseSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Sync School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function syncResponseStudent(Institution $institution): User
{
    $parent = User::factory()->create();
    DB::table('parent_details')->insert(['id' => $parent->id, 'parent_id' => $parent->id]);

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Class '.uniqid()]);

    $student = User::factory()->create();
    $student->assignRole('Student');

    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'parent_id' => $parent->id,
        'admission_number' => 'ADM-'.uniqid(),
    ]);

    return $student->refresh();
}

test('the sync trigger is a POST and redirects back with a flash message, not JSON', function () {
    [$director, $institution] = syncResponseSchool();
    $student = syncResponseStudent($institution);

    // No devices configured for the school yet.
    $response = $this->actingAs($director)
        ->from(route('student.show', $student->id))
        ->post(route('students.sync-device', $student->id));

    $response->assertRedirect(route('student.show', $student->id));
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('hardware device');
});

test('syncing a non-student redirects back with an error, not a 422 json body', function () {
    [$director, $institution] = syncResponseSchool();

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    $this->actingAs($director)
        ->from(route('student.index'))
        ->post(route('students.sync-device', $teacher->id))
        ->assertRedirect(route('student.index'))
        ->assertSessionHas('error');
});

test('a programmatic caller that asks for json still gets json', function () {
    [$director, $institution] = syncResponseSchool();
    $student = syncResponseStudent($institution);

    // A guard path (no devices) - never touches the hardware service - but
    // an Accept: application/json request must still come back as JSON, not
    // a redirect.
    $this->actingAs($director)
        ->postJson(route('students.sync-device', $student->id))
        ->assertStatus(404)
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'message']);
});

test('the sync route no longer answers GET', function () {
    [$director] = syncResponseSchool();

    $this->actingAs($director)
        ->get('/dashboard/students/1/sync-device')
        ->assertStatus(405);
});
