<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Modules\Institution\Models\Institution;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function directorNamed(string $name): User
{
    $director = User::factory()->create(['name' => $name]);
    $director->assignRole('Director');
    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Green Valley Academy',
        'code' => 'GV-'.uniqid(),
        'type' => 'School',
        'is_approved' => true,
    ]);
    $director->update(['active_institution_id' => $institution->id]);

    return $director;
}

test('a person is greeted by first name, protected from translation', function () {
    $this->actingAs(directorNamed('Amina Hassan'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welcome back, <span translate="no">Amina</span>', false);
});

test('honorifics are kept with the name', function () {
    $this->actingAs(directorNamed('Dr. Otieno Were'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Welcome back, Dr. Otieno');
});

test('an account named after the school or a role gets a plain greeting', function (string $name) {
    $this->actingAs(directorNamed($name))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Welcome back')
        ->assertDontSeeText('Welcome back, ');
})->with([
    'school and role' => 'Green Valley Director',
    'school name' => 'Green Valley',
    'office account' => 'Bursar Office',
]);
