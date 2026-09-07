<?php

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\PaystackService;
use Database\Seeders\PermissionSeeder;
use Modules\Institution\Models\Institution;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->mock(PaystackService::class, function ($mock) {
        $mock->shouldReceive('initializeTransaction')->andReturn([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.paystack.test/x'],
        ]);
        $mock->shouldReceive('verifyTransaction')->andReturn([
            'data' => ['status' => 'success', 'id' => 'PS-1', 'channel' => 'card'],
        ]);
    });
});

function plan(string $name, float $price, string $cycle = 'termly'): Plan
{
    return Plan::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.uniqid(),
        'price' => $price,
        'billing_cycle' => $cycle,
        'modules' => ['Student'],
        'features' => [],
        'is_active' => true,
    ]);
}

/**
 * A school mid-way through a paid window on $current: window started
 * $startedDaysAgo days ago and ends $endsInDays days from now.
 *
 * @return array{0: User, 1: Institution}
 */
function subscribedSchool(Plan $current, int $startedDaysAgo, int $endsInDays): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Prorate School '.uniqid(),
        'code' => 'PS-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
        'subscription_plan' => $current->id,
        'subscription_started_at' => now()->subDays($startedDaysAgo),
        'subscription_expires_at' => now()->addDays($endsInDays),
    ]);

    // The payment that opened the window.
    Payment::create([
        'institution_id' => $institution->id,
        'plan_id' => $current->id,
        'reference' => 'seed-'.uniqid(),
        'amount' => $current->price,
        'status' => 'successful',
        'paid_at' => now()->subDays($startedDaysAgo),
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution->refresh()];
}

test('mid-window, an upgrade only charges the difference after crediting the unused days', function () {
    $standard = plan('Standard', 12000);        // 120-day window
    $premium = plan('Premium', 30000);

    // Halfway through a 120-day term: 60 days left -> half the plan's value
    // is still unused = KES 6,000 credit.
    [, $institution] = subscribedSchool($standard, startedDaysAgo: 60, endsInDays: 60);

    expect($institution->subscriptionWindowDays())->toBe(120)
        ->and($institution->subscriptionDaysRemaining())->toBe(60)
        ->and($institution->unusedPlanCredit())->toBe(6000.0)
        // 30,000 desired - 6,000 credit = 24,000 to pay.
        ->and($institution->amountDueForPlan($premium))->toBe(24000.0)
        // Nothing owed on the plan they're already on.
        ->and($institution->amountDueForPlan($standard))->toBe(0.0);
});

test('with the window almost up, the upgrade costs almost the full new price', function () {
    $standard = plan('Standard', 12000);
    $premium = plan('Premium', 30000);

    // 3 of 120 days left -> credit is a sliver.
    [, $institution] = subscribedSchool($standard, startedDaysAgo: 117, endsInDays: 3);

    expect($institution->unusedPlanCredit())->toBe(round(12000 * 3 / 120, 2)) // 300
        ->and($institution->amountDueForPlan($premium))->toBe(29700.0);
});

test('a school with no active subscription owes the full plan price', function () {
    $premium = plan('Premium', 30000);

    $director = User::factory()->create();
    $director->assignRole('Director');
    $institution = Institution::create([
        'user_id' => $director->id, 'name' => 'Fresh', 'code' => 'F-'.uniqid(),
        'type' => 'School', 'is_approved' => true,
    ]);

    expect($institution->unusedPlanCredit())->toBe(0.0)
        ->and($institution->amountDueForPlan($premium))->toBe(30000.0);
});

test('paying the prorated difference swaps the plan but keeps the existing renewal date', function () {
    $standard = plan('Standard', 12000);
    $premium = plan('Premium', 30000);
    [$director, $institution] = subscribedSchool($standard, startedDaysAgo: 60, endsInDays: 60);

    $originalExpiry = $institution->subscription_expires_at->toDateString();
    $originalStart = $institution->subscription_started_at->toDateString();

    $this->actingAs($director)->post(route('billing.initiate'), ['plan_id' => $premium->id])
        ->assertRedirect();

    $payment = Payment::where('plan_id', $premium->id)->firstOrFail();
    expect((float) $payment->amount)->toBe(24000.0);

    // Complete it.
    $this->get(route('billing.callback', ['reference' => $payment->reference]))->assertRedirect();

    $institution->refresh();
    expect($institution->subscription_plan)->toBe($premium->id)
        ->and($institution->subscription_expires_at->toDateString())->toBe($originalExpiry)
        ->and($institution->subscription_started_at->toDateString())->toBe($originalStart);
});

test('paying the full price near expiry opens a brand-new window', function () {
    $standard = plan('Standard', 12000, 'termly');
    $premium = plan('Premium', 30000, 'termly');
    [$director, $institution] = subscribedSchool($standard, startedDaysAgo: 118, endsInDays: 2);

    $this->actingAs($director)->post(route('billing.initiate'), ['plan_id' => $premium->id])->assertRedirect();

    $payment = Payment::where('plan_id', $premium->id)->firstOrFail();
    // Near-full price (credit was only ~200).
    expect((float) $payment->amount)->toBeGreaterThan(29000.0);

    $this->get(route('billing.callback', ['reference' => $payment->reference]))->assertRedirect();

    $institution->refresh();
    // A fresh termly window - four months out, not the 2 days it had.
    expect($institution->subscription_plan)->toBe($premium->id)
        ->and($institution->subscription_expires_at->isAfter(now()->addMonths(3)))->toBeTrue()
        ->and($institution->subscription_started_at->isToday())->toBeTrue();
});

test('the billing page shows the credit and the discounted amount', function () {
    $standard = plan('Standard', 12000);
    plan('Premium', 30000);
    [$director, $institution] = subscribedSchool($standard, startedDaysAgo: 60, endsInDays: 60);

    $this->actingAs($director)->get(route('billing.show'))
        ->assertOk()
        ->assertSee('unused value on your current plan')
        ->assertSee('KES 6,000.00')
        ->assertSee('Pay KES 24,000.00');
});
