<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Expenditure\Models\Expenditure;
use Modules\Expenditure\Models\ExpenditureCategory;
use Modules\Institution\Models\Institution;
use Modules\Staff\Models\StaffDetails;
use Modules\Teacher\Models\TeacherDetails;

// Every expenditure screen gates on permissions, so the real roles have to
// exist.
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeSchool(User $owner, string $name = 'Test School'): Institution
{
    return Institution::create([
        'user_id' => $owner->id,
        'name' => $name.' '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);
}

function makeSchoolDirector(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = makeSchool($director);
    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

/**
 * An Accountant is a staff member with a login - the same shape the Staff
 * module creates, and how currentInstitution() finds their school.
 */
function makeSchoolAccountant(Institution $institution): User
{
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');

    StaffDetails::create([
        'institution_id' => $institution->id,
        'user_id' => $accountant->id,
        'name' => $accountant->name,
        'email' => $accountant->email,
        'job_title' => 'Accountant',
        'status' => 'active',
        'is_active' => true,
    ]);

    return $accountant->refresh();
}

test('an accountant records an expenditure against their own school', function () {
    [, $institution] = makeSchoolDirector();
    $accountant = makeSchoolAccountant($institution);

    $category = ExpenditureCategory::create([
        'institution_id' => $institution->id,
        'name' => 'Utilities',
        'is_active' => true,
    ]);

    $this->actingAs($accountant)->post(route('expenditure.store'), [
        'expenditure_category_id' => $category->id,
        'title' => 'Term 2 electricity',
        'payee' => 'Kenya Power',
        'amount' => 12500.50,
        'spent_on' => '2026-08-01',
        'payment_method' => 'bank_transfer',
        'status' => 'paid',
    ])->assertRedirect(route('expenditure.index'));

    $expenditure = Expenditure::firstOrFail();

    expect($expenditure->institution_id)->toBe($institution->id)
        ->and($expenditure->recorded_by)->toBe($accountant->id)
        ->and((float) $expenditure->amount)->toBe(12500.50)
        // Marking it paid stamps when the money actually left.
        ->and($expenditure->paid_at)->not->toBeNull();
});

test('moving an expenditure back off paid clears the paid stamp', function () {
    [$director, $institution] = makeSchoolDirector();

    $expenditure = Expenditure::create([
        'institution_id' => $institution->id,
        'title' => 'Bus repair',
        'amount' => 8000,
        'spent_on' => '2026-08-02',
        'payment_method' => 'cash',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    $this->actingAs($director)->put(route('expenditure.update', $expenditure->id), [
        'title' => 'Bus repair',
        'amount' => 8000,
        'spent_on' => '2026-08-02',
        'payment_method' => 'cash',
        'status' => 'pending',
    ])->assertRedirect(route('expenditure.show', $expenditure->id));

    expect($expenditure->refresh()->paid_at)->toBeNull();
});

test('an accountant never sees or touches another school\'s spending', function () {
    [, $ourSchool] = makeSchoolDirector();
    [, $otherSchool] = makeSchoolDirector();

    $accountant = makeSchoolAccountant($ourSchool);

    $theirs = Expenditure::create([
        'institution_id' => $otherSchool->id,
        'title' => 'Their staff party',
        'amount' => 5000,
        'spent_on' => '2026-08-03',
        'payment_method' => 'cash',
        'status' => 'paid',
    ]);

    $this->actingAs($accountant)->get(route('expenditure.index'))
        ->assertOk()
        ->assertDontSee('Their staff party');

    $this->actingAs($accountant)->get(route('expenditure.show', $theirs->id))->assertNotFound();
});

test('a category from another school cannot be used to file a spend', function () {
    [, $ourSchool] = makeSchoolDirector();
    [, $otherSchool] = makeSchoolDirector();

    $accountant = makeSchoolAccountant($ourSchool);

    $theirCategory = ExpenditureCategory::create([
        'institution_id' => $otherSchool->id,
        'name' => 'Their Utilities',
        'is_active' => true,
    ]);

    $this->actingAs($accountant)->post(route('expenditure.store'), [
        'expenditure_category_id' => $theirCategory->id,
        'title' => 'Sneaky',
        'amount' => 100,
        'spent_on' => '2026-08-04',
        'payment_method' => 'cash',
        'status' => 'pending',
    ])->assertForbidden();

    expect(Expenditure::count())->toBe(0);
});

test('loading the standard categories is additive and never duplicates', function () {
    [, $institution] = makeSchoolDirector();
    $accountant = makeSchoolAccountant($institution);

    ExpenditureCategory::create([
        'institution_id' => $institution->id,
        'name' => 'Utilities',
        'is_active' => true,
    ]);

    $this->actingAs($accountant)->post(route('expenditure.categories.defaults'))
        ->assertRedirect(route('expenditure.categories.index'));

    $this->actingAs($accountant)->post(route('expenditure.categories.defaults'))
        ->assertRedirect(route('expenditure.categories.index'));

    expect(ExpenditureCategory::where('institution_id', $institution->id)->count())
        ->toBe(count(ExpenditureCategory::DEFAULTS))
        ->and(ExpenditureCategory::where('institution_id', $institution->id)->where('name', 'Utilities')->count())
        ->toBe(1);
});

test('a category with spending filed under it is retired rather than deleted', function () {
    [$director, $institution] = makeSchoolDirector();

    $category = ExpenditureCategory::create([
        'institution_id' => $institution->id,
        'name' => 'Transport & Fuel',
        'is_active' => true,
    ]);

    Expenditure::create([
        'institution_id' => $institution->id,
        'expenditure_category_id' => $category->id,
        'title' => 'Diesel',
        'amount' => 9000,
        'spent_on' => '2026-08-05',
        'payment_method' => 'cash',
        'status' => 'paid',
    ]);

    $this->actingAs($director)->delete(route('expenditure.categories.destroy', $category->id))
        ->assertRedirect(route('expenditure.categories.index'));

    expect($category->refresh()->is_active)->toBeFalse();
});

test('a teacher has no access to the school\'s expenditure', function () {
    [, $institution] = makeSchoolDirector();

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    $this->actingAs($teacher->refresh())->get(route('expenditure.index'))->assertForbidden();
});

test('the expenditure screens render for an accountant', function () {
    [, $institution] = makeSchoolDirector();
    $accountant = makeSchoolAccountant($institution);

    ExpenditureCategory::create([
        'institution_id' => $institution->id,
        'name' => 'Utilities',
        'is_active' => true,
    ]);

    $expenditure = Expenditure::create([
        'institution_id' => $institution->id,
        'title' => 'Water bill',
        'amount' => 3200,
        'spent_on' => '2026-08-06',
        'payment_method' => 'mobile_money',
        'status' => 'paid',
    ]);

    $this->actingAs($accountant)->get(route('expenditure.index'))->assertOk()->assertSee('Water bill');
    $this->actingAs($accountant)->get(route('expenditure.create'))->assertOk();
    $this->actingAs($accountant)->get(route('expenditure.edit', $expenditure->id))->assertOk();
    $this->actingAs($accountant)->get(route('expenditure.show', $expenditure->id))->assertOk();
    $this->actingAs($accountant)->get(route('expenditure.categories.index'))->assertOk()->assertSee('Utilities');
});

test('a director records and reads expenditure just as an accountant does', function () {
    [$director, $institution] = makeSchoolDirector();
    $accountant = makeSchoolAccountant($institution);

    $category = ExpenditureCategory::create([
        'institution_id' => $institution->id,
        'name' => 'Repairs & Maintenance',
        'is_active' => true,
    ]);

    $this->actingAs($director)->post(route('expenditure.store'), [
        'expenditure_category_id' => $category->id,
        'title' => 'Roof repair',
        'amount' => 45000,
        'spent_on' => '2026-08-07',
        'payment_method' => 'cheque',
        'status' => 'approved',
    ])->assertRedirect(route('expenditure.index'));

    $expenditure = Expenditure::firstOrFail();

    expect($expenditure->institution_id)->toBe($institution->id)
        ->and($expenditure->recorded_by)->toBe($director->id);

    // Both roles work the same book: each sees what the other recorded.
    $this->actingAs($director)->get(route('expenditure.index'))->assertOk()->assertSee('Roof repair');
    $this->actingAs($accountant)->get(route('expenditure.index'))->assertOk()->assertSee('Roof repair');
    $this->actingAs($director)->get(route('expenditure.categories.index'))->assertOk();

    $this->actingAs($director)->delete(route('expenditure.destroy', $expenditure->id))
        ->assertRedirect(route('expenditure.index'));
});

test('the expenditure list filters live without a page reload', function () {
    [, $institution] = makeSchoolDirector();
    $accountant = makeSchoolAccountant($institution);

    $utilities = ExpenditureCategory::create([
        'institution_id' => $institution->id,
        'name' => 'Utilities',
        'is_active' => true,
    ]);

    Expenditure::create([
        'institution_id' => $institution->id,
        'expenditure_category_id' => $utilities->id,
        'title' => 'Water bill',
        'amount' => 3200,
        'spent_on' => '2026-08-06',
        'payment_method' => 'mobile_money',
        'status' => 'paid',
    ]);

    Expenditure::create([
        'institution_id' => $institution->id,
        'title' => 'Bus diesel',
        'amount' => 9000,
        'spent_on' => '2026-08-07',
        'payment_method' => 'cash',
        'status' => 'pending',
    ]);

    Livewire::actingAs($accountant)
        ->test('expenditure::index')
        ->assertSee('Water bill')
        ->assertSee('Bus diesel')
        ->set('search', 'diesel')
        ->assertSee('Bus diesel')
        ->assertDontSee('Water bill')
        ->set('search', '')
        ->set('status', 'paid')
        ->assertSee('Water bill')
        ->assertDontSee('Bus diesel');
});

test('deleting from the expenditure list happens in place', function () {
    [$director, $institution] = makeSchoolDirector();

    $expenditure = Expenditure::create([
        'institution_id' => $institution->id,
        'title' => 'Wrong entry',
        'amount' => 100,
        'spent_on' => '2026-08-08',
        'payment_method' => 'cash',
        'status' => 'pending',
    ]);

    Livewire::actingAs($director)
        ->test('expenditure::index')
        ->call('delete', $expenditure->id)
        ->assertHasNoErrors()
        ->assertDontSee('Wrong entry');

    expect(Expenditure::count())->toBe(0);
});

test('the livewire form records a spend and redirects without a reload', function () {
    [, $institution] = makeSchoolDirector();
    $accountant = makeSchoolAccountant($institution);

    Livewire::actingAs($accountant)
        ->test('expenditure::form')
        ->set('title', 'Staff tea')
        ->set('amount', '1500')
        ->set('spent_on', '2026-08-09')
        ->set('payment_method', 'cash')
        ->set('status', 'paid')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $expenditure = Expenditure::firstOrFail();

    expect($expenditure->title)->toBe('Staff tea')
        ->and($expenditure->institution_id)->toBe($institution->id)
        ->and($expenditure->recorded_by)->toBe($accountant->id)
        ->and($expenditure->paid_at)->not->toBeNull();
});

test('the livewire form refuses an incomplete spend', function () {
    [, $institution] = makeSchoolDirector();
    $accountant = makeSchoolAccountant($institution);

    Livewire::actingAs($accountant)
        ->test('expenditure::form')
        ->set('title', '')
        ->set('amount', '')
        ->call('save')
        ->assertHasErrors(['title', 'amount']);

    expect(Expenditure::count())->toBe(0);
});

test('categories are managed in place with toasts', function () {
    [, $institution] = makeSchoolDirector();
    $accountant = makeSchoolAccountant($institution);

    Livewire::actingAs($accountant)
        ->test('expenditure::categories')
        ->call('loadDefaults')
        ->assertHasNoErrors();

    expect(ExpenditureCategory::where('institution_id', $institution->id)->count())
        ->toBe(count(ExpenditureCategory::DEFAULTS));

    Livewire::actingAs($accountant)
        ->test('expenditure::categories')
        ->set('newName', 'Utilities')
        ->call('add')
        ->assertHasErrors('newName');
});
