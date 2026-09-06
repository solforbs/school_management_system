<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeAdmin(string $name = 'Platform Owner'): User
{
    $admin = User::factory()->create(['name' => $name]);
    $admin->assignRole('Admin');

    return $admin;
}

function makeDirector(string $name = 'School Head', ?string $email = null): User
{
    $director = User::factory()->create(['name' => $name, 'email' => $email ?? fake()->unique()->safeEmail()]);
    $director->assignRole('Director');

    return $director;
}

test('the screen is closed to anyone who is not an admin', function () {
    $director = makeDirector();

    // A non-admin never lands on the page - bounced by the middleware
    // stack, and the component's own guard (exercised directly below)
    // would 403 them even if they did.
    $response = $this->actingAs($director)->get(route('admin.user-roles.index'));

    expect($response->status())->not->toBe(200);
});

test('an admin can open the screen and see directors listed', function () {
    $admin = makeAdmin();
    makeDirector('Grace Wanjiru');

    $this->actingAs($admin)->get(route('admin.user-roles.index'))
        ->assertOk()
        ->assertSee('Admin Access')
        ->assertSee('Promote a Director')
        ->assertSee('Grace Wanjiru');
});

test('an admin promotes a director, who becomes an admin outright', function () {
    $admin = makeAdmin();
    $director = makeDirector();

    Livewire::actingAs($admin)
        ->test('pages::admin.user-roles')
        // The row's button only stages the id; the confirm modal's button
        // runs promote() with no argument.
        ->set('confirmingPromoteId', $director->id)
        ->call('promote')
        ->assertHasNoErrors()
        ->assertSet('confirmingPromoteId', null);

    $director->refresh();

    expect($director->hasRole('Admin'))->toBeTrue()
        ->and($director->hasRole('Director'))->toBeFalse()
        // Picks up the Admin role's permission set.
        ->and($director->can('update user'))->toBeTrue()
        ->and($director->can('view billing'))->toBeTrue()
        // Directly-held permissions are cleared - access is the role only.
        ->and($director->getDirectPermissions())->toHaveCount(0);
});

test('promotion refuses an account that is not a director', function () {
    $admin = makeAdmin();
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    Livewire::actingAs($admin)
        ->test('pages::admin.user-roles')
        ->call('promote', $teacher->id)
        ->assertStatus(422);

    expect($teacher->refresh()->hasRole('Admin'))->toBeFalse();
});

test('a director cannot promote anyone even by calling the component directly', function () {
    $director = makeDirector();
    $target = makeDirector('Another Head');

    Livewire::actingAs($director)
        ->test('pages::admin.user-roles')
        ->assertForbidden();

    expect($target->refresh()->hasRole('Admin'))->toBeFalse();
});

test('an admin demotes another admin back to director', function () {
    $admin = makeAdmin();
    $promoted = makeDirector();
    $promoted->syncRoles(['Admin']);

    Livewire::actingAs($admin)
        ->test('pages::admin.user-roles')
        ->set('confirmingDemoteId', $promoted->id)
        ->call('demote')
        ->assertHasNoErrors()
        ->assertSet('confirmingDemoteId', null);

    $promoted->refresh();

    expect($promoted->hasRole('Director'))->toBeTrue()
        ->and($promoted->hasRole('Admin'))->toBeFalse()
        ->and($promoted->can('update user'))->toBeFalse();
});

test('the protected platform-owner account can never have admin revoked', function () {
    $admin = makeAdmin('Ops Admin');
    $owner = User::factory()->create(['name' => 'Real Owner', 'email' => 'jablessions76@gmail.com']);
    $owner->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test('pages::admin.user-roles')
        ->set('confirmingDemoteId', $owner->id)
        ->call('demote')
        ->assertStatus(422);

    expect($owner->refresh()->hasRole('Admin'))->toBeTrue();

    // The row shows a locked "Platform owner" badge instead of a revoke
    // trigger for that account.
    $this->actingAs($admin)->get(route('admin.user-roles.index'))
        ->assertOk()
        ->assertSee('Platform owner')
        ->assertDontSee('$set(\'confirmingDemoteId\', '.$owner->id.')', false);
});

test('the protected email is matched regardless of case', function () {
    $admin = makeAdmin();
    $owner = User::factory()->create(['email' => 'JABlessions76@GMAIL.com']);
    $owner->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test('pages::admin.user-roles')
        ->set('confirmingDemoteId', $owner->id)
        ->call('demote')
        ->assertStatus(422);

    expect($owner->refresh()->hasRole('Admin'))->toBeTrue();
});

test('an admin cannot remove their own admin access', function () {
    $admin = makeAdmin();
    makeAdmin('Second Admin'); // so the "last admin" guard is not what trips

    Livewire::actingAs($admin)
        ->test('pages::admin.user-roles')
        ->call('demote', $admin->id)
        ->assertStatus(422);

    expect($admin->refresh()->hasRole('Admin'))->toBeTrue();
});

test('the last remaining admin cannot be demoted', function () {
    $admin = makeAdmin();
    $other = makeAdmin('Only Other Admin');

    // Down to one admin.
    $other->syncRoles(['Director']);

    Livewire::actingAs($admin)
        ->test('pages::admin.user-roles')
        ->call('demote', $admin->id)
        ->assertStatus(422);
});

test('the search matches a director by name, email or id', function () {
    $admin = makeAdmin();
    $jane = makeDirector('Jane Kamau', 'jane@school.ac.ke');
    $peter = makeDirector('Peter Omondi', 'peter@academy.ac.ke');

    $component = Livewire::actingAs($admin)->test('pages::admin.user-roles');

    $component->set('search', 'Jane')
        ->assertSee('Jane Kamau')->assertDontSee('Peter Omondi');

    $component->set('search', 'academy.ac.ke')
        ->assertSee('Peter Omondi')->assertDontSee('Jane Kamau');

    $component->set('search', (string) $jane->id)
        ->assertSee('Jane Kamau')->assertDontSee('Peter Omondi');
});
