<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Modules\Institution\Models\Institution;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('an onboarded user can visit the dashboard', function () {
    // The dashboard gates on the 'view dashboard' permission, and
    // HasInstitution sends anyone who might own a school through onboarding
    // first - a Parent is the simplest role that clears both.
    $this->seed(PermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Parent');
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('a user who has not verified their email is sent to verify it', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
});

test('a suspended user is logged out', function () {
    $user = User::factory()->suspended()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    expect(auth()->check())->toBeFalse();
});

test('a user without an institution is sent through onboarding', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('institution.create'));
});

test('a director with active institution sees rich dashboard with analytics', function () {
    $this->seed(PermissionSeeder::class);

    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Oakridge Academy',
        'code' => 'OAK-001',
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);
    $this->actingAs($director);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Welcome back');
    $response->assertSee('Oakridge Academy');
    $response->assertSee('Students');
    $response->assertSee('Parents');
    $response->assertSee('Collection');
    $response->assertSee('Attendance');
    $response->assertSee('Devices');
});
