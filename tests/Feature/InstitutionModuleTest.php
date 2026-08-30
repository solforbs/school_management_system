<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeAdminUser(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    return $admin;
}

test('a user creates an institution requiring admin approval', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('institution.store'), [
        'name' => 'Highland Preparatory',
        'code' => 'HP',
        'type' => 'School',
        'email' => 'info@highland.edu',
        'phone' => '+254700112233',
        'county' => 'Nairobi',
        'city' => 'Nairobi',
        'postal_address' => 'P.O. Box 100',
        'physical_address' => 'Kilimani Road',
    ])->assertRedirect(route('institution.index'));

    expect(Institution::count())->toBe(1);
    $institution = Institution::first();
    expect($institution->name)->toBe('Highland Preparatory')
        ->and($institution->is_approved)->toBeFalse()
        ->and($user->hasRole('Director'))->toBeTrue();
});

test('an admin approves an institution', function () {
    $admin = makeAdminUser();
    $director = User::factory()->create();

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Apex High',
        'code' => 'APEX',
        'type' => 'School',
        'email' => 'contact@apex.edu',
        'phone' => '+254700998877',
        'is_approved' => false,
    ]);

    $this->actingAs($admin)->post(route('institution.approve', $institution->id))
        ->assertRedirect();

    expect($institution->refresh()->is_approved)->toBeTrue()
        ->and($institution->approved_by_id)->toBe($admin->id);
});

test('a director switches active institution', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');

    $inst1 = Institution::create([
        'user_id' => $director->id,
        'name' => 'Campus Alpha',
        'code' => 'ALPHA',
        'type' => 'School',
        'is_approved' => true,
    ]);

    $inst2 = Institution::create([
        'user_id' => $director->id,
        'name' => 'Campus Beta',
        'code' => 'BETA',
        'type' => 'School',
        'is_approved' => true,
    ]);

    $this->actingAs($director)->post(route('institution.choose', $inst2->id))
        ->assertRedirect(route('dashboard'));

    expect($director->refresh()->active_institution_id)->toBe($inst2->id);
});

test('a director updates institution details', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Original Name',
        'code' => 'ORIG',
        'type' => 'School',
        'email' => 'orig@example.com',
        'phone' => '+254700000000',
        'is_approved' => true,
        'is_active' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    $this->actingAs($director)->put(route('institution.update', $institution->id), [
        'name' => 'Updated Name',
        'code' => 'ORIG',
        'type' => 'School',
        'email' => 'updated@example.com',
        'phone' => '+254700000000',
        'timezone' => 'Africa/Nairobi',
        'min_electives' => 1,
        'max_electives' => 3,
        'is_active' => 1,
    ])->assertRedirect(route('institution.edit', $institution->id));

    expect($institution->refresh()->name)->toBe('Updated Name')
        ->and($institution->email)->toBe('updated@example.com')
        ->and($institution->min_electives)->toBe(1);
});

test('livewire institution index handles choose action', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Livewire Campus',
        'code' => 'LWC',
        'type' => 'School',
        'is_approved' => true,
        'is_active' => true,
    ]);

    Livewire::actingAs($director)
        ->test('institution::index')
        ->assertSee('Livewire Campus')
        ->call('choose', $institution->id)
        ->assertRedirect(route('dashboard'));

    expect($director->refresh()->active_institution_id)->toBe($institution->id);
});
