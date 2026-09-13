<?php

use App\Jobs\SendParentNotificationJob;
use App\Models\ParentNotification;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Examinations\Models\ExamSitting;
use Modules\Institution\Database\Seeders\DemoAccountSeeder;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;
use Spatie\Permission\Models\Role;

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

if (! function_exists('onboardingDirector')) {
    function onboardingDirector(): User
    {
        $director = User::factory()->create();
        $director->assignRole('Director');

        return $director->refresh();
    }
}

test('the demo seeder builds a demo-flagged school with a full data graph', function () {
    $this->seed(DemoAccountSeeder::class);

    $director = User::where('email', DemoAccountSeeder::DIRECTOR_EMAIL)->first();
    expect($director)->not->toBeNull()
        ->and($director->hasRole('Director'))->toBeTrue()
        ->and(Hash::check(DemoAccountSeeder::DIRECTOR_EMAIL, $director->password))->toBeTrue();

    $institution = Institution::demo()->sole();
    $scoped = fn (string $table) => DB::table($table)->where('institution_id', $institution->id)->count();

    expect($institution->is_demo)->toBeTrue()
        ->and($institution->is_approved)->toBeTrue()
        ->and($institution->subjects()->count())->toBeGreaterThan(10)
        ->and($institution->classes()->count())->toBeGreaterThan(3)
        ->and($institution->teacherDetails()->count())->toBeGreaterThan(5)
        ->and($institution->studentDetails()->count())->toBeGreaterThan(20)
        ->and($institution->fees()->count())->toBeGreaterThan(0)
        ->and($institution->results()->count())->toBeGreaterThan(0)
        ->and(ExamSitting::where('institution_id', $institution->id)->count())->toBeGreaterThan(1)
        // Every module has demo data.
        ->and($scoped('curricula'))->toBeGreaterThan(0)
        ->and($scoped('subject_selections'))->toBeGreaterThan(0)
        ->and($scoped('timetable_entries'))->toBeGreaterThan(0)
        ->and($scoped('report_cards'))->toBeGreaterThan(0)
        ->and($scoped('lessons'))->toBeGreaterThan(0)
        ->and($scoped('lesson_reports'))->toBeGreaterThan(0)
        ->and($scoped('expenditures'))->toBeGreaterThan(0)
        ->and($scoped('staff_payments'))->toBeGreaterThan(0);
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

test('paying for a plan and editing the shared login are turned off inside the demo', function () {
    [$demoDirector] = minimalDemoSchool();

    $this->actingAs($demoDirector)->post(route('billing.initiate'))->assertForbidden();
    $this->actingAs($demoDirector)->get(route('profile.edit'))->assertForbidden();
    $this->actingAs($demoDirector)->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertForbidden();
});

test('the demo director reaches every director page, including the ones that used to be blocked', function () {
    [$demoDirector] = minimalDemoSchool();

    foreach (['student.index', 'billing.show', 'devices.index', 'sync-devices.index', 'notifications.index'] as $route) {
        $this->actingAs($demoDirector)->get(route($route))->assertOk();
    }
});

test('the demo school has every module and feature even with no plan attached', function () {
    [, $institution] = minimalDemoSchool();

    expect($institution->subscription_plan)->toBeNull();

    foreach (array_map('basename', glob(base_path('Modules/*'), GLOB_ONLYDIR)) as $module) {
        expect($institution->hasModule($module))->toBeTrue("demo school is missing the {$module} module");
    }

    expect($institution->hasFeature('ai_receipt_scanning'))->toBeTrue()
        ->and($institution->hasFeature('bulk_sms'))->toBeTrue();
});

test('the demo seeder puts the demo school on the enterprise plan', function () {
    $this->seed(PlanSeeder::class);
    $this->seed(DemoAccountSeeder::class);

    $institution = Institution::demo()->sole();

    expect($institution->subscription_plan)->toBe(Plan::where('slug', 'enterprise')->value('id'))
        ->and($institution->hasActiveSubscription())->toBeTrue()
        ->and($institution->hasModule('FeeManagement'))->toBeTrue();
});

test('the demo director has every permission the director role carries', function () {
    [$demoDirector] = minimalDemoSchool();

    $rolePermissions = Role::findByName('Director')->permissions->pluck('name');

    expect($rolePermissions)->not->toBeEmpty()
        ->and($demoDirector->getAllPermissions()->pluck('name')->sort()->values()->all())
        ->toBe($rolePermissions->sort()->values()->all());
});

test('a notice sent from the demo school is recorded as delivered but never actually sent', function () {
    Queue::fake();
    [$demoDirector, $institution] = minimalDemoSchool();

    Livewire::actingAs($demoDirector)
        ->test('pages::notifications')
        ->set('subject', 'Mid-term break')
        ->set('body', 'School closes on Friday.')
        ->set('channels', ['email'])
        ->set('mode', 'now')
        ->call('send')
        ->assertHasNoErrors();

    $notice = ParentNotification::where('institution_id', $institution->id)->sole();

    expect($notice->status)->toBe('sent')
        ->and($notice->sent_at)->not->toBeNull();
    Queue::assertNotPushed(SendParentNotificationJob::class);
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
