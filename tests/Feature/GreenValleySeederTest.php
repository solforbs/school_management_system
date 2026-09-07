<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Database\Seeders\InstitutionDatabaseSeeder;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\ReportCard;
use Modules\Result\Models\Result;

test('institution database seeder creates green valley academy and role users', function () {
    $this->seed(InstitutionDatabaseSeeder::class);

    $institution = Institution::where('code', 'GVA001')->first();
    expect($institution)->not->toBeNull();
    expect($institution->name)->toBe('Green Valley Academy');
    expect($institution->status)->toBe('active');
    expect($institution->is_approved)->toBeTrue();

    // Director (School Owner / Head)
    $director = User::where('email', 'director@greenvalley.ac.ke')->first();
    expect($director)->not->toBeNull();
    expect($director->hasRole('Director'))->toBeTrue();
    expect($director->active_institution_id)->toBe($institution->id);
    expect(Hash::check('password', $director->password))->toBeTrue();

    // Faculty & Staff
    expect(User::role('Teacher')->count())->toBeGreaterThanOrEqual(40);
    expect(User::role('Accountant')->count())->toBeGreaterThanOrEqual(1);

    // Parent & Students
    expect(User::role('Parent')->count())->toBeGreaterThanOrEqual(400);
    expect(User::role('Student')->count())->toBeGreaterThanOrEqual(1000);

    // Financial & Academic scale checks
    expect(Fee::where('institution_id', $institution->id)->count())->toBeGreaterThan(1000);
    expect(Result::where('institution_id', $institution->id)->count())->toBeGreaterThan(1000);
    expect(ReportCard::where('institution_id', $institution->id)->count())->toBeGreaterThan(1000);
});
