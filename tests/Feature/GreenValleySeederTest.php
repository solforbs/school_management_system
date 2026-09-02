<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Institution\Database\Seeders\InstitutionDatabaseSeeder;
use Modules\Institution\Models\Institution;

test('institution database seeder creates green valley academy and role users', function () {
    $this->seed(InstitutionDatabaseSeeder::class);

    $institution = Institution::where('code', 'GVA001')->first();
    expect($institution)->not->toBeNull();
    expect($institution->name)->toBe('Green Valley Academy');
    expect($institution->status)->toBe('active');
    expect($institution->is_approved)->toBeFalse();

    // Director (School Owner / Head)
    $director = User::where('email', 'director@greenvalley.ac.ke')->first();
    expect($director)->not->toBeNull();
    expect($director->hasRole('Director'))->toBeTrue();
    expect($director->active_institution_id)->toBe($institution->id);
    expect(Hash::check('password', $director->password))->toBeTrue();

    // Accountant
    $accountant = User::where('email', 'accountant@greenvalley.ac.ke')->first();
    expect($accountant)->not->toBeNull();
    expect($accountant->hasRole('Accountant'))->toBeTrue();
    expect($accountant->staffUserDetails)->not->toBeNull();
    expect($accountant->staffUserDetails->institution_id)->toBe($institution->id);

    // Teacher
    $teacher = User::where('email', 'teacher@greenvalley.ac.ke')->first();
    expect($teacher)->not->toBeNull();
    expect($teacher->hasRole('Teacher'))->toBeTrue();
    expect($teacher->teacherUserDetails)->not->toBeNull();
    expect($teacher->teacherUserDetails->institution_id)->toBe($institution->id);

    // Parent & Student
    $parent = User::where('email', 'parent@greenvalley.ac.ke')->first();
    expect($parent)->not->toBeNull();
    expect($parent->hasRole('Parent'))->toBeTrue();

    $student = User::where('email', 'student@greenvalley.ac.ke')->first();
    expect($student)->not->toBeNull();
    expect($student->hasRole('Student'))->toBeTrue();
    expect($student->studentUserDetails)->not->toBeNull();
    expect($student->studentUserDetails->institution_id)->toBe($institution->id);
    expect($student->studentUserDetails->parent_id)->toBe($parent->id);
});
