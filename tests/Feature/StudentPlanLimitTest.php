<?php

use App\Livewire\Admin\PlanFeatureManager;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;
use Modules\Student\Actions\SaveStudent;
use Modules\Student\Exceptions\StudentLimitException;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('public');
});

/**
 * A Director whose active institution sits on the given plan.
 *
 * @return array{0: User, 1: Institution, 2: Plan}
 */
function directorOnPlan(?int $maxStudents): array
{
    $plan = Plan::create([
        'name' => 'Test '.uniqid(),
        'slug' => 'test-'.uniqid(),
        'price' => 1000,
        'billing_cycle' => 'termly',
        'modules' => ['Student'],
        'features' => [],
        'max_students' => $maxStudents,
        'is_active' => true,
    ]);

    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Limit School '.uniqid(),
        'code' => 'LS-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
        'subscription_plan' => $plan->id,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution, $plan];
}

function enrolViaController(User $director, string $email): TestResponse
{
    return test()->actingAs($director)->post(route('student.store'), [
        'name' => 'Learner '.$email,
        'admission_number' => 'ADM-'.uniqid(),
        'email' => $email,
        'password' => 'password123',
        'profile_image' => UploadedFile::fake()->image('p.jpg', 100, 100),
        'enrollment_status' => 'active',
        'is_active' => '1',
    ]);
}

test('a plan caps how many students a director can enrol', function () {
    [$director, $institution] = directorOnPlan(2);

    enrolViaController($director, 'a@example.com')->assertSessionHasNoErrors()->assertRedirect();
    enrolViaController($director, 'b@example.com')->assertSessionHasNoErrors()->assertRedirect();

    expect($institution->currentStudentCount())->toBe(2);

    // The third crosses the ceiling: refused, with the reason on the form,
    // and nothing written.
    $response = enrolViaController($director, 'c@example.com');
    $response->assertSessionHasErrors('name');

    expect($institution->currentStudentCount())->toBe(2)
        ->and(User::where('email', 'c@example.com')->exists())->toBeFalse()
        ->and(session('errors')->first('name'))->toContain('supports up to 2 students');
});

test('an unlimited plan never blocks enrolment', function () {
    [$director, $institution] = directorOnPlan(null);

    foreach (['x@example.com', 'y@example.com', 'z@example.com'] as $email) {
        enrolViaController($director, $email)->assertSessionHasNoErrors();
    }

    expect($institution->currentStudentCount())->toBe(3);
});

test('the limit cannot be sidestepped by calling the enrolment action directly', function () {
    [, $institution] = directorOnPlan(1);

    StudentDetails::create([
        'student_id' => User::factory()->create()->id,
        'institution_id' => $institution->id,
        'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
    ]);

    expect(fn () => app(SaveStudent::class)->create([
        'name' => 'Over The Line',
        'email' => 'over@example.com',
        'password' => 'password123',
    ], $institution->id))->toThrow(StudentLimitException::class);

    expect(User::where('email', 'over@example.com')->exists())->toBeFalse();
});

test('the student directory shows how many the plan supports', function () {
    [$director, $institution] = directorOnPlan(500);

    StudentDetails::create([
        'student_id' => User::factory()->create()->id,
        'institution_id' => $institution->id,
        'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
    ]);

    $this->actingAs($director)->get(route('student.index'))
        ->assertOk()
        ->assertSee('supports up to 500 students')
        ->assertSee('1 enrolled');
});

test('the enrolment form warns when the plan is full', function () {
    [$director, $institution] = directorOnPlan(1);

    StudentDetails::create([
        'student_id' => User::factory()->create()->id,
        'institution_id' => $institution->id,
        'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
    ]);

    $this->actingAs($director)->get(route('student.create'))
        ->assertOk()
        ->assertSee('reached that limit');
});

test('the plan feature manager ticks, adds and detaches features', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test(PlanFeatureManager::class, ['planId' => null, 'features' => ['ai_receipt_scanning']])
        ->assertSet('selected', ['ai_receipt_scanning'])
        // Tick an existing catalogue feature.
        ->call('toggle', 'bulk_sms')
        ->assertSet('selected', ['ai_receipt_scanning', 'bulk_sms'])
        // Untick it.
        ->call('toggle', 'bulk_sms')
        ->assertSet('selected', ['ai_receipt_scanning'])
        // Define a brand-new one - it registers in the catalogue and is
        // ticked on.
        ->set('newLabel', 'Parent Portal')
        ->call('addFeature')
        ->assertSet('selected', ['ai_receipt_scanning', 'parent_portal'])
        ->assertSet('newLabel', '');

    expect(PlanFeature::where('key', 'parent_portal')->value('label'))->toBe('Parent Portal');
});

test('the feature manager deletes a custom feature through its confirm dialog', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    PlanFeature::create(['key' => 'custom_thing', 'label' => 'Custom Thing', 'is_active' => true]);

    Livewire::actingAs($admin)
        ->test(PlanFeatureManager::class, ['planId' => null, 'features' => ['custom_thing']])
        ->assertSet('selected', ['custom_thing'])
        // The trigger only stages the key; the modal's confirm button runs
        // the delete with no argument.
        ->set('confirmingDeleteKey', 'custom_thing')
        ->call('deleteFeature')
        ->assertSet('selected', [])
        ->assertSet('confirmingDeleteKey', null);

    expect(PlanFeature::where('key', 'custom_thing')->exists())->toBeFalse();
});

test('the feature manager will not delete a built-in feature', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test(PlanFeatureManager::class, ['planId' => null, 'features' => ['ai_receipt_scanning']])
        ->set('confirmingDeleteKey', 'ai_receipt_scanning')
        ->call('deleteFeature');

    // Still catalogued - only the per-plan tick was cleared.
    expect(PlanFeature::where('key', 'ai_receipt_scanning')->exists())->toBeTrue();
});

test('the admin plan create and edit screens render the feature manager', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)->get(route('admin.plans.create'))
        ->assertOk()
        ->assertSee('Pro features')
        ->assertSee('Student limit')
        ->assertSee('AI Receipt Scanning (Fee Management)');

    $plan = Plan::create([
        'name' => 'Editable',
        'slug' => 'editable-'.uniqid(),
        'price' => 1000,
        'billing_cycle' => 'termly',
        'modules' => ['Student'],
        'features' => ['bulk_sms'],
        'max_students' => 300,
        'is_active' => true,
    ]);

    $this->actingAs($admin)->get(route('admin.plans.edit', $plan))
        ->assertOk()
        ->assertSee('Bulk SMS')
        ->assertSee('value="300"', false);
});

test('the admin plan form stores the student limit and the chosen features', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)->post(route('admin.plans.store'), [
        'name' => 'Growth',
        'price' => 25000,
        'billing_cycle' => 'termly',
        'max_students' => 750,
        'modules' => ['Student', 'FeeManagement'],
        'features' => ['ai_receipt_scanning', 'bulk_sms', 'ai_receipt_scanning'],
        'is_active' => '1',
    ])->assertRedirect(route('admin.plans.index'))->assertSessionHasNoErrors();

    $plan = Plan::where('name', 'Growth')->firstOrFail();

    expect($plan->max_students)->toBe(750)
        ->and($plan->studentLimitLabel())->toBe('Up to 750 students')
        // De-duplicated on the way in.
        ->and($plan->features)->toBe(['ai_receipt_scanning', 'bulk_sms']);
});

test('a blank student limit on the admin form means unlimited', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)->post(route('admin.plans.store'), [
        'name' => 'Enterprise',
        'price' => 0,
        'billing_cycle' => 'termly',
        'modules' => ['Student'],
        'is_active' => '1',
        'is_custom_priced' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $plan = Plan::where('name', 'Enterprise')->firstOrFail();

    expect($plan->max_students)->toBeNull()
        ->and($plan->hasStudentLimit())->toBeFalse()
        ->and($plan->studentLimitLabel())->toBe('Unlimited students');
});
