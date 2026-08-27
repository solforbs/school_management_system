<?php

use App\Models\Plan;
use Database\Seeders\PremiumPlanSeeder;

function makePlan(array $overrides = []): Plan
{
    return Plan::create(array_merge([
        'name' => 'Starter',
        'slug' => 'starter-'.uniqid(),
        'description' => 'For a school finding its feet.',
        'price' => 15000,
        'billing_cycle' => 'monthly',
        'modules' => ['Student', 'FeeManagement', 'ReportCard'],
        'features' => ['ai_receipt_scanning'],
        'is_active' => true,
    ], $overrides));
}

test('anyone can list the plans on sale, without signing in', function () {
    $growth = makePlan(['name' => 'Growth', 'price' => 35000]);
    $starter = makePlan(['name' => 'Starter', 'price' => 15000]);

    $response = $this->getJson('/api/plans');

    $response->assertOk();

    // Cheapest first, so a pricing page can render them in order as given.
    expect($response->json('data.*.id'))->toBe([$starter->id, $growth->id])
        ->and($response->json('data.*.name'))->toBe(['Starter', 'Growth']);
});

test('a plan carries its price, cycle, modules and features, each labelled', function () {
    $plan = makePlan();

    $data = $this->getJson('/api/plans')->assertOk()->json('data.0');

    expect($data['id'])->toBe($plan->id)
        ->and($data['slug'])->toBe($plan->slug)
        ->and($data['name'])->toBe('Starter')
        ->and($data['description'])->toBe('For a school finding its feet.')
        // A number rather than the decimal string the column holds, so a
        // frontend can format it without parsing it first.
        ->and(is_string($data['price']))->toBeFalse()
        ->and((float) $data['price'])->toBe(15000.0)
        ->and($data['currency'])->toBe('KES')
        ->and($data['billing_cycle'])->toBe('monthly')
        // Each entry pairs the stable key with wording fit to print.
        ->and($data['modules'])->toBe([
            ['key' => 'Student', 'label' => 'Student'],
            ['key' => 'FeeManagement', 'label' => 'Fee Management'],
            ['key' => 'ReportCard', 'label' => 'Report Card'],
        ])
        ->and($data['features'])->toBe([
            ['key' => 'ai_receipt_scanning', 'label' => 'AI Receipt Scanning (Fee Management)'],
        ]);
});

test('the public shape carries nothing beyond what a pricing page needs', function () {
    makePlan();

    $data = $this->getJson('/api/plans')->assertOk()->json('data.0');

    // Named explicitly: a column added to the plans table later must not
    // appear here until someone puts it in PlanResource on purpose.
    expect(array_keys($data))->toBe([
        'id', 'slug', 'name', 'description', 'price', 'currency', 'is_custom_priced', 'billing_cycle', 'is_featured', 'modules', 'features',
    ]);
});

test('a plan withdrawn from sale is absent from the list and unreachable directly', function () {
    $retired = makePlan(['name' => 'Retired', 'is_active' => false]);
    makePlan(['name' => 'Live']);

    expect($this->getJson('/api/plans')->assertOk()->json('data.*.name'))->toBe(['Live']);

    // A 404 rather than a 403: the public has no business learning it exists.
    $this->getJson('/api/plans/'.$retired->slug)->assertNotFound();
    $this->getJson('/api/plans/'.$retired->id)->assertNotFound();
});

test('a single plan is fetchable by slug or by id', function () {
    $plan = makePlan();

    $bySlug = $this->getJson('/api/plans/'.$plan->slug)->assertOk()->json('data');
    $byId = $this->getJson('/api/plans/'.$plan->id)->assertOk()->json('data');

    expect($bySlug)->toBe($byId)
        ->and($bySlug['name'])->toBe('Starter')
        ->and($bySlug['modules'])->toHaveCount(3);
});

test('an unknown plan is a json 404, not an html error page', function () {
    $response = $this->getJson('/api/plans/no-such-plan');

    $response->assertNotFound();
    $response->assertHeader('content-type', 'application/json');
});

test('a plan with no modules or features returns empty lists rather than null', function () {
    // The columns are nullable, and a frontend mapping over them shouldn't
    // have to guard against null.
    makePlan(['modules' => [], 'features' => null]);

    $data = $this->getJson('/api/plans')->assertOk()->json('data.0');

    expect($data['modules'])->toBe([])
        ->and($data['features'])->toBe([]);
});

test('the endpoints answer cross-origin, which is the point of them', function () {
    makePlan();

    $response = $this->getJson('/api/plans', ['Origin' => 'https://marketing.example.com']);

    $response->assertOk();
    $response->assertHeader('access-control-allow-origin', '*');
});

test('the public pricing page is rendered from the plans table, not from the template', function () {
    makePlan([
        'name' => 'Growth',
        'description' => 'For a school running everything in one place.',
        'price' => 35000,
        'billing_cycle' => 'monthly',
        'modules' => ['FeeManagement', 'Timetable'],
        'features' => ['ai_receipt_scanning'],
        'is_featured' => true,
    ]);
    makePlan(['name' => 'Retired', 'is_active' => false]);

    $response = $this->get(route('plans'));

    $response->assertOk()
        ->assertSee('Growth')
        ->assertSee('For a school running everything in one place.')
        ->assertSee('KES 35,000')
        ->assertSee('/month')
        // What the plan grants, spelled out from its own modules/features.
        ->assertSee('Fee Management')
        ->assertSee('Timetable')
        ->assertSee('AI Receipt Scanning (Fee Management)')
        // The badge follows the flag rather than the card's position.
        ->assertSee('Most popular')
        // A plan withdrawn from sale is off the page entirely.
        ->assertDontSee('Retired')
        // And none of the copy the template used to hardcode survives.
        ->assertDontSee('Up to 300 students')
        ->assertDontSee('Everything in Starter');
});

test('a free plan reads as free, and a page with no plans says so', function () {
    makePlan(['name' => 'Basic', 'price' => 0]);

    $this->get(route('plans'))->assertOk()->assertSee('Free')->assertDontSee('KES 0');

    Plan::query()->update(['is_active' => false]);

    $this->get(route('plans'))
        ->assertOk()
        ->assertSee("We're putting together pricing for the coming term.", false);
});

test('a quoted plan reads Custom, even though its price sits at zero like a free one', function () {
    // The crux: both rows are 0.00. Only the flag separates a tier nobody
    // has priced yet from a tier that genuinely costs nothing.
    $enterprise = makePlan([
        'name' => 'Enterprise',
        'price' => 0,
        'is_custom_priced' => true,
        'billing_cycle' => 'monthly',
    ]);
    $basic = makePlan(['name' => 'Basic', 'price' => 0]);

    expect($enterprise->priceLabel())->toBe('Custom')
        ->and($basic->priceLabel())->toBe('Free')
        // No published rate means nothing for a period to be "per".
        ->and($enterprise->periodLabel())->toBeNull()
        ->and($basic->periodLabel())->toBe('/month')
        ->and($enterprise->isSelfServe())->toBeFalse()
        ->and($basic->isSelfServe())->toBeTrue();
});

test('the pricing page sends a quoted plan to contact, and a free one to register', function () {
    makePlan(['name' => 'Enterprise', 'price' => 0, 'is_custom_priced' => true]);
    makePlan(['name' => 'Basic', 'price' => 0]);

    $html = $this->get(route('plans'))->assertOk()->getContent();

    expect($html)->toContain('Custom')
        ->toContain('Free')
        // Never promise a free Enterprise tier.
        ->and(substr_count($html, 'Free'))->toBe(1)
        ->and($html)->toContain('Talk to us')
        ->and($html)->toContain('Get started')
        ->and($html)->toContain(route('contact'));
});

test('the api says whether a price is quoted, so a frontend can render Custom too', function () {
    makePlan(['name' => 'Enterprise', 'price' => 0, 'is_custom_priced' => true]);

    $data = $this->getJson('/api/plans')->assertOk()->json('data.0');

    expect($data['is_custom_priced'])->toBeTrue()
        ->and($data['price'])->toBe(0);
});

test('the premium seeder grants every module and feature, and never duplicates itself', function () {
    $this->seed(PremiumPlanSeeder::class);

    $premium = Plan::where('name', 'Premium')->firstOrFail();
    $originalSlug = $premium->slug;

    expect($premium->modules)->toBe(Plan::MODULES)
        ->and($premium->features)->toBe(array_keys(Plan::FEATURES))
        ->and($premium->is_active)->toBeTrue()
        // Priced on application rather than at an invented figure.
        ->and($premium->priceLabel())->toBe('Custom')
        ->and($premium->isSelfServe())->toBeFalse();

    $this->seed(PremiumPlanSeeder::class);

    expect(Plan::where('name', 'Premium')->count())->toBe(1)
        // Re-seeding must not move the slug a public URL is built from.
        ->and(Plan::where('name', 'Premium')->firstOrFail()->slug)->toBe($originalSlug);
});

test('re-seeding leaves a price the business has since set alone', function () {
    $this->seed(PremiumPlanSeeder::class);

    // Someone fixes a real rate on the admin screen.
    Plan::where('name', 'Premium')->firstOrFail()->update([
        'price' => 9000,
        'is_custom_priced' => false,
        'is_featured' => true,
    ]);

    $this->seed(PremiumPlanSeeder::class);

    $premium = Plan::where('name', 'Premium')->firstOrFail();

    expect($premium->priceLabel())->toBe('KES 9,000')
        ->and($premium->is_custom_priced)->toBeFalse()
        ->and($premium->is_featured)->toBeTrue()
        // Inclusions still refresh, so a new module joins on the next run.
        ->and($premium->modules)->toBe(Plan::MODULES);
});
