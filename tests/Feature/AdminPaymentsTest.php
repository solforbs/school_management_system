<?php

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makePaymentsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    return $admin;
}

function makePaymentsInstitution(array $overrides = []): Institution
{
    $owner = User::factory()->create();

    return Institution::create(array_merge([
        'user_id' => $owner->id,
        'name' => 'Payments Test School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ], $overrides));
}

function makePaymentsPlan(): Plan
{
    return Plan::create([
        'name' => 'Growth '.uniqid(),
        'slug' => 'growth-'.uniqid(),
        'price' => 5000,
        'billing_cycle' => 'monthly',
        'is_active' => true,
        'modules' => ['Student'],
    ]);
}

function makeTestPayment(array $overrides = []): Payment
{
    return Payment::create(array_merge([
        'plan_id' => $overrides['plan_id'] ?? makePaymentsPlan()->id,
        'purpose' => Payment::PURPOSE_SUBSCRIPTION,
        'reference' => 'ref-'.uniqid(),
        'amount' => 1000,
        'currency' => 'KES',
        'status' => 'successful',
        'paid_at' => now(),
    ], $overrides));
}

test('a non-admin cannot view the payments page', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');
    // An onboarded Director, not a brand-new sign-up: HasInstitution would
    // otherwise redirect a zero-institution user before this page's own
    // isAdmin() gate ever runs.
    $institution = makePaymentsInstitution(['user_id' => $director->id]);
    $director->update(['active_institution_id' => $institution->id]);

    Livewire::actingAs($director)
        ->test('pages::admin.payments')
        ->assertForbidden();

    $this->actingAs($director)
        ->get(route('admin.payments.index'))
        ->assertForbidden();
});

test('the admin sees every payment across every institution', function () {
    $admin = makePaymentsAdmin();
    $plan = makePaymentsPlan();
    $riverside = makePaymentsInstitution(['name' => 'Riverside Academy']);
    $lakeside = makePaymentsInstitution(['name' => 'Lakeside College']);

    makeTestPayment(['institution_id' => $riverside->id, 'plan_id' => $plan->id, 'amount' => 15000]);
    makeTestPayment(['institution_id' => $lakeside->id, 'plan_id' => $plan->id, 'purpose' => Payment::PURPOSE_SETUP, 'amount' => 2000]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertSee('Riverside Academy')
        ->assertSee('Lakeside College')
        ->assertSee('KES 17,000.00'); // total revenue KPI
});

test('an unclaimed setup fee shows without an institution rather than crashing', function () {
    $admin = makePaymentsAdmin();
    makeTestPayment(['institution_id' => null, 'purpose' => Payment::PURPOSE_SETUP, 'amount' => 2500]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertSee('Not yet claimed');
});

test('filtering by institution narrows the table and the summary together', function () {
    $admin = makePaymentsAdmin();
    $riverside = makePaymentsInstitution(['name' => 'Riverside Academy']);
    $lakeside = makePaymentsInstitution(['name' => 'Lakeside College']);

    makeTestPayment(['institution_id' => $riverside->id, 'amount' => 4000]);
    makeTestPayment(['institution_id' => $lakeside->id, 'amount' => 9000]);

    Livewire::actingAs($admin)
        ->test('pages::admin.payments')
        ->assertSee('Riverside Academy')
        ->assertSee('Lakeside College')
        ->set('institutionId', (string) $riverside->id)
        ->assertSee('Riverside Academy')
        ->assertDontSee('Lakeside College');
});

test('filtering by purpose only shows setup fees', function () {
    $admin = makePaymentsAdmin();
    $institution = makePaymentsInstitution();

    makeTestPayment(['institution_id' => $institution->id, 'purpose' => Payment::PURPOSE_SETUP, 'reference' => 'setup-ref']);
    makeTestPayment(['institution_id' => $institution->id, 'purpose' => Payment::PURPOSE_SUBSCRIPTION, 'reference' => 'sub-ref']);

    Livewire::actingAs($admin)
        ->test('pages::admin.payments')
        ->assertSee('setup-ref')
        ->assertSee('sub-ref')
        ->set('purpose', 'setup')
        ->assertSee('setup-ref')
        ->assertDontSee('sub-ref');
});

test('pending and failed payments do not count toward settled revenue', function () {
    $admin = makePaymentsAdmin();
    $institution = makePaymentsInstitution();

    makeTestPayment(['institution_id' => $institution->id, 'status' => 'successful', 'amount' => 3000]);
    makeTestPayment(['institution_id' => $institution->id, 'status' => 'pending', 'amount' => 5000, 'paid_at' => null]);
    makeTestPayment(['institution_id' => $institution->id, 'status' => 'failed', 'amount' => 7000, 'paid_at' => null]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertSee('KES 3,000.00') // total revenue - successful only
        ->assertSee('1 pending')
        ->assertSee('1 failed');
});

test('the admin dashboard shows platform revenue and links to the payments page', function () {
    $admin = makePaymentsAdmin();
    $institution = makePaymentsInstitution(['name' => 'Riverside Academy']);
    makeTestPayment(['institution_id' => $institution->id, 'amount' => 12000]);

    $this->actingAs($admin)
        ->get(route('report.index'))
        ->assertOk()
        ->assertSee('Platform Revenue')
        ->assertSee('KES 12,000') // the dashboard's $currency() helper drops decimals on a whole number
        ->assertSee(route('admin.payments.index'));
});

test('a director does not see platform revenue on their own dashboard', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = makePaymentsInstitution(['user_id' => $director->id]);
    $director->update(['active_institution_id' => $institution->id]);

    $this->actingAs($director)
        ->get(route('report.index'))
        ->assertOk()
        ->assertDontSee('Platform Revenue');
});
