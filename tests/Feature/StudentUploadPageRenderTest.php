<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Cache::flush();
});

function uploadPageEnvironment(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Render Academy '.uniqid(),
        'code' => 'RA-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);
    $director->update(['active_institution_id' => $institution->id]);

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Grade 3', 'level' => 'Primary']);

    foreach (['R-1', 'R-2'] as $adm) {
        StudentDetails::create([
            'student_id' => User::factory()->create()->id,
            'institution_id' => $institution->id,
            'class_id' => $class->id,
            'admission_number' => $adm,
            'gender' => 'male',
            'guardian_name' => 'Guardian '.$adm,
            'is_active' => true,
        ]);
    }

    return [$director, $institution, $class];
}

it('renders the bulk upload page over http for a director', function () {
    [$director] = uploadPageEnvironment();

    $this->actingAs($director)->get(route('student.upload'))->assertOk();
});

it('renders roster for all classes and a single class without errors', function () {
    [$director, , $class] = uploadPageEnvironment();

    Livewire::actingAs($director)
        ->test('student::upload')
        ->call('onClassSelectChange', '')
        ->assertOk()
        ->assertSee('All Classes Enrolled Roster')
        ->call('onClassSelectChange', (string) $class->id)
        ->assertOk()
        ->assertSee('Grade 3 Enrolled Roster');
});
