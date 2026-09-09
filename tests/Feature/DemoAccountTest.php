<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Modules\Examinations\Models\ExamSitting;
use Modules\Institution\Database\Seeders\DemoAccountSeeder;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * A minimal demo school - just the flagged institution and its director -
 * for the flow/guard tests that don't need the full seeded graph.
 *
 * @return array{0: User, 1: Institution}
 */
function minimalDemoSchool(): array
{
    $director = User::factory()->create(['email' => DemoAccountSeeder::DIRECTOR_EMAIL]);
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Demo',
        'code' => DemoAccountSeeder::INSTITUTION_CODE,
        'type' => 'School',
        'is_active' => true,
        'is_demo' => true,
        'status' => 'active',
        'is_approved' => true,
        'approved_at' => now(),
        'subscription_expires_at' => now()->addYear(),
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function onboardingDirector(): User
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    return $director->refresh();
}

test('the demo seeder builds a demo-flagged school with a full data graph', function () {
    $this->seed(DemoAccountSeeder::class);

    $director = User::where('email', DemoAccountSeeder::DIRECTOR_EMAIL)->first();
    expect($director)->not->toBeNull()
        ->and($director->hasRole('Director'))->toBeTrue()
        ->and(Hash::check(DemoAccountSeeder::DIRECTOR_EMAIL, $director->password))->toBeTrue();

    $institution = Institution::demo()->sole();
    expect($institution->is_demo)->toBeTrue()
        ->and($institution->is_approved)->toBeTrue()
        ->and($institution->subjects()->count())->toBeGreaterThan(10)
        ->and($institution->classes()->count())->toBeGreaterThan(3)
        ->and($institution->teacherDetails()->count())->toBeGreaterThan(5)
        ->and($institution->studentDetails()->count())->toBeGreaterThan(20)
        ->and($institution->fees()->count())->toBeGreaterThan(0)
        ->and($institution->results()->count())->toBeGreaterThan(0)
        ->and(ExamSitting::where('institution_id', $institution->id)->count())->toBe(1);
});

test('demo:reset throws away edits, keeps the director, and rebuilds', function () {
    $this->seed(DemoAccountSeeder::class);

    $directorId = User::where('email', DemoAccountSeeder::DIRECTOR_EMAIL)->value('id');
    $institution = Institution::demo()->sole();

    // A visitor renames a subject and adds one.
    Subject::where('institution_id', $institution->id)->first()->update(['name' => 'Tampered']);
    Subject::create(['institution_id' => $institution->id, 'name' => 'Visitor Subject', 'is_active' => true]);

    $this->artisan('demo:reset')->assertSuccessful();

    expect(User::where('email', DemoAccountSeeder::DIRECTOR_EMAIL)->value('id'))->toBe($directorId)
        ->and(Institution::demo()->count())->toBe(1)
        ->and(Subject::where('name', 'Tampered')->exists())->toBeFalse()
        ->and(Subject::where('name', 'Visitor Subject')->exists())->toBeFalse();

    $fresh = Institution::demo()->sole();
    expect($fresh->studentDetails()->count())->toBeGreaterThan(20);
});

test('an onboarding director can enter the demo and leave it', function () {
    [$demoDirector] = minimalDemoSchool();
    $newcomer = onboardingDirector();

    $this->actingAs($newcomer)
        ->post(route('demo.enter'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($demoDirector);
    expect(session('demo.return_to'))->toBe($newcomer->id);

    $this->post(route('demo.leave'))->assertRedirect(route('onboarding.plan'));
    $this->assertAuthenticatedAs($newcomer);
    expect(session()->has('demo.return_to'))->toBeFalse();
});

test('a director who already runs a school cannot enter the demo', function () {
    minimalDemoSchool();

    $director = User::factory()->create();
    $director->assignRole('Director');
    Institution::create([
        'user_id' => $director->id,
        'name' => 'Real School',
        'code' => 'REAL-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $this->actingAs($director->refresh())->post(route('demo.enter'))->assertForbidden();
});

test('billing, devices and account settings are turned off inside the demo', function () {
    [$demoDirector] = minimalDemoSchool();

    $this->actingAs($demoDirector)->get(route('billing.show'))->assertForbidden();
    $this->actingAs($demoDirector)->get(route('devices.index'))->assertForbidden();
    $this->actingAs($demoDirector)->get(route('notifications.index'))->assertForbidden();
    $this->actingAs($demoDirector)->get(route('profile.edit'))->assertForbidden();
});

test('the demo director can still do ordinary school work', function () {
    [$demoDirector, $institution] = minimalDemoSchool();

    // A route that is not on the demo block list renders fine.
    $this->actingAs($demoDirector)->get(route('student.index'))->assertOk();
});

test('a normal director is not blocked by the demo guard', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');
    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Ordinary School',
        'code' => 'ORD-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'is_demo' => false,
        'status' => 'active',
        'is_approved' => true,
        'subscription_expires_at' => now()->addYear(),
    ]);
    $director->update(['active_institution_id' => $institution->id]);

    $this->actingAs($director->refresh())->get(route('billing.show'))->assertOk();
});
