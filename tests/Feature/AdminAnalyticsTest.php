<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Testing\TestResponse;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use PhpOffice\PhpSpreadsheet\IOFactory;

// These controllers gate on permissions ('view report', 'export report'),
// not just role names, so the real roles and permissions have to exist.
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeAnalyticsAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    return $user;
}

function makeInstitution(array $overrides = []): Institution
{
    $owner = User::factory()->create();

    return Institution::create(array_merge([
        'user_id' => $owner->id,
        'name' => 'Test Institution '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ], $overrides));
}

test('the admin sees a system-wide analytics dashboard', function () {
    $admin = makeAnalyticsAdmin();
    $this->actingAs($admin);

    makeInstitution(['name' => 'Riverside Academy']);
    makeInstitution(['name' => 'Lakeside College', 'type' => 'College', 'is_approved' => false]);

    $response = $this->get(route('report.index'));

    $response->assertOk();
    $response->assertSee('Reports & Analytics');
    $response->assertSee('Riverside Academy');
    $response->assertSee('Needs Your Attention');
    $response->assertSee('Lakeside College');
    $response->assertSee('Growth');
    $response->assertSee('Global Fee Collection Velocity');
    $response->assertSee('Institutions by Type');
});

test('a director only sees their own institution, not system-wide stats', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = makeInstitution(['user_id' => $director->id]);
    $otherInstitution = makeInstitution(['name' => 'Someone Elses School']);

    $this->actingAs($director);

    $response = $this->get(route('report.index'));

    $response->assertOk();
    $response->assertDontSee('Needs Your Attention');
    $response->assertDontSee('Someone Elses School');
});

test('the admin can approve a pending institution from the dashboard', function () {
    $admin = makeAnalyticsAdmin();
    $this->actingAs($admin);

    $institution = makeInstitution(['is_approved' => false]);

    $response = $this->post(route('institution.approve', $institution));

    $response->assertRedirect();
    // Not cast to boolean on the model, so the driver hands back int 1.
    expect($institution->fresh()->is_approved)->toBeTruthy();
});

test("the admin's dashboard redirects straight to the analytics page", function () {
    $admin = makeAnalyticsAdmin();
    $this->actingAs($admin);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('report.index'));
});

test('a director sees a robust analytics dashboard for their own institution', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = makeInstitution(['user_id' => $director->id, 'name' => 'Riverside Academy']);
    // Reports don't sit behind HasInstitution, so nothing picks the active
    // institution here the way landing on /dashboard first would.
    $director->update(['active_institution_id' => $institution->id]);

    Fee::create([
        'institution_id' => $institution->id,
        'student_id' => User::factory()->create()->id,
        'title' => 'Term 1 Tuition',
        'amount' => 10000,
        'amount_paid' => 4000,
    ]);

    $this->actingAs($director);

    $response = $this->get(route('report.index'));

    $response->assertOk();
    $response->assertSee('Riverside Academy');
    $response->assertSee('Fee Collection Velocity Trend');
    $response->assertSee('Student Enrollment');
    $response->assertSee('Enrollment Status');
    $response->assertSee('Recent Students');
    $response->assertSee('Term 1 Tuition');
    $response->assertDontSee('Institutions by Type');
});

test('the admin export contains every entity, system-wide', function () {
    $admin = makeAnalyticsAdmin();
    $this->actingAs($admin);

    makeInstitution(['name' => 'Riverside Academy']);

    $response = $this->get(route('report.export'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $spreadsheet = IOFactory::load(
        streamedResponseToTempFile($response)
    );

    expect($spreadsheet->getSheetNames())->toEqual([
        'Institutions', 'Users', 'Students', 'Parents', 'Fees', 'Devices', 'Attendance (30 days)', 'Contact Messages',
    ]);
});

test("a director's export only covers their own institution", function () {
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = makeInstitution(['user_id' => $director->id, 'name' => 'Riverside Academy']);
    $otherInstitution = makeInstitution(['name' => 'Someone Elses School']);

    Fee::create([
        'institution_id' => $institution->id,
        'student_id' => User::factory()->create()->id,
        'title' => 'Term 1 Tuition',
        'amount' => 10000,
        'amount_paid' => 4000,
    ]);
    Fee::create([
        'institution_id' => $otherInstitution->id,
        'student_id' => User::factory()->create()->id,
        'title' => 'Someone Elses Fee',
        'amount' => 5000,
        'amount_paid' => 0,
    ]);

    $this->actingAs($director);

    $response = $this->get(route('report.export'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $spreadsheet = IOFactory::load(
        streamedResponseToTempFile($response)
    );

    expect($spreadsheet->getSheetNames())->toEqual(['Students', 'Parents', 'Fees', 'Devices', 'Attendance (30 days)']);

    $feesSheet = $spreadsheet->getSheetByName('Fees')->toArray();
    $titles = array_column($feesSheet, 1);
    expect($titles)->toContain('Term 1 Tuition');
    expect($titles)->not->toContain('Someone Elses Fee');
});

function streamedResponseToTempFile(TestResponse $response): string
{
    $path = tempnam(sys_get_temp_dir(), 'export-test-').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    return $path;
}
