<?php

use App\Models\User;
use App\Services\AdmissionNumberPatternService;
use Database\Seeders\PermissionSeeder;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function createTestSchoolForPattern(string $name): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => $name.' '.uniqid(),
        'code' => 'PAT-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    return [$director, $institution];
}

it('detects numeric sequences in patterns like FAHAN-014-2025 and suggests FAHAN-015-2025', function () {
    [, $institution] = createTestSchoolForPattern('Fahan Academy');
    $service = app(AdmissionNumberPatternService::class);

    // Seed existing students with FAHAN-014-2025 pattern
    foreach (['FAHAN-012-2025', 'FAHAN-013-2025', 'FAHAN-014-2025'] as $adm) {
        $student = User::factory()->create();
        StudentDetails::create([
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'admission_number' => $adm,
            'is_active' => true,
        ]);
    }

    $patternInfo = $service->detectPattern($institution->id);

    expect($patternInfo['status'])->toBe('consistent')
        ->and($patternInfo['predominant_pattern'])->not->toBeNull()
        ->and($patternInfo['predominant_pattern']['highest_number'])->toBe(14)
        ->and($patternInfo['predominant_pattern']['highest_admission'])->toBe('FAHAN-014-2025')
        ->and($patternInfo['predominant_pattern']['next_number'])->toBe(15)
        ->and($patternInfo['predominant_pattern']['next_admission'])->toBe('FAHAN-015-2025');
});

it('determines highest number according to pattern even when records are created out of order', function () {
    [, $institution] = createTestSchoolForPattern('Out of Order School');
    $service = app(AdmissionNumberPatternService::class);

    // Create FAHAN-016-2025 first (ID 1)
    $s1 = User::factory()->create();
    StudentDetails::create([
        'student_id' => $s1->id,
        'institution_id' => $institution->id,
        'admission_number' => 'FAHAN-016-2025',
        'is_active' => true,
    ]);

    // Create FAHAN-005-2025 later (ID 2 - highest ID but lower sequence number!)
    $s2 = User::factory()->create();
    StudentDetails::create([
        'student_id' => $s2->id,
        'institution_id' => $institution->id,
        'admission_number' => 'FAHAN-005-2025',
        'is_active' => true,
    ]);

    $patternInfo = $service->detectPattern($institution->id);

    // The logic must NOT simply use latest('id') (which would pick 005)
    // It must find highest valid sequence number 16 and suggest 17
    expect($patternInfo['predominant_pattern']['highest_number'])->toBe(16)
        ->and($patternInfo['predominant_pattern']['highest_admission'])->toBe('FAHAN-016-2025')
        ->and($patternInfo['predominant_pattern']['next_admission'])->toBe('FAHAN-017-2025');
});

it('handles varied numbering patterns including pure numbers and slash formats', function () {
    $service = app(AdmissionNumberPatternService::class);

    // Pure number
    $parsedNum = $service->parseAdmissionNumber('1042');
    expect($parsedNum['number'])->toBe(1042)
        ->and($parsedNum['prefix'])->toBe('')
        ->and($parsedNum['suffix'])->toBe('');

    // Slash format
    $parsedSlash = $service->parseAdmissionNumber('ADM/2026/042');
    expect($parsedSlash['number'])->toBe(42)
        ->and($parsedSlash['prefix'])->toBe('ADM/2026/')
        ->and($parsedSlash['padding'])->toBe(3);

    // Dash format with year
    $parsedDash = $service->parseAdmissionNumber('GVA-10-2026');
    expect($parsedDash['number'])->toBe(10)
        ->and($parsedDash['prefix'])->toBe('GVA-')
        ->and($parsedDash['suffix'])->toBe('-2026');
});

it('detects inconsistent patterns when school uses multiple conflicting formats', function () {
    [, $institution] = createTestSchoolForPattern('Mixed Pattern School');
    $service = app(AdmissionNumberPatternService::class);

    // Seed 2 of one pattern and 2 of another pattern
    foreach (['ADM-001', 'ADM-002', 'FAHAN-014-2025', 'FAHAN-015-2025'] as $adm) {
        $student = User::factory()->create();
        StudentDetails::create([
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'admission_number' => $adm,
            'is_active' => true,
        ]);
    }

    $patternInfo = $service->detectPattern($institution->id);

    expect($patternInfo['status'])->toBe('inconsistent')
        ->and(count($patternInfo['detected_patterns']))->toBeGreaterThanOrEqual(2);
});

it('guarantees strict school isolation so one institutions sequence never affects another', function () {
    [, $schoolA] = createTestSchoolForPattern('School Alpha');
    [, $schoolB] = createTestSchoolForPattern('School Beta');
    $service = app(AdmissionNumberPatternService::class);

    // School A has FAHAN-099-2025
    $sA = User::factory()->create();
    StudentDetails::create([
        'student_id' => $sA->id,
        'institution_id' => $schoolA->id,
        'admission_number' => 'FAHAN-099-2025',
        'is_active' => true,
    ]);

    // School B has FAHAN-010-2025
    $sB = User::factory()->create();
    StudentDetails::create([
        'student_id' => $sB->id,
        'institution_id' => $schoolB->id,
        'admission_number' => 'FAHAN-010-2025',
        'is_active' => true,
    ]);

    $patternA = $service->detectPattern($schoolA->id);
    $patternB = $service->detectPattern($schoolB->id);

    expect($patternA['predominant_pattern']['next_admission'])->toBe('FAHAN-100-2025')
        ->and($patternB['predominant_pattern']['next_admission'])->toBe('FAHAN-011-2025');
});

it('auto-fills sequential admission numbers for empty rows while preserving existing numbers', function () {
    [, $institution] = createTestSchoolForPattern('Auto Fill Academy');
    $service = app(AdmissionNumberPatternService::class);

    // Existing student is FAHAN-014-2025
    $s = User::factory()->create();
    StudentDetails::create([
        'student_id' => $s->id,
        'institution_id' => $institution->id,
        'admission_number' => 'FAHAN-014-2025',
        'is_active' => true,
    ]);

    $rows = [
        ['name' => 'Student 1', 'admission_number' => ''],
        ['name' => 'Student 2', 'admission_number' => 'CUSTOM-99'],
        ['name' => 'Student 3', 'admission_number' => ''],
    ];

    $suggested = $service->suggestForRows($institution->id, $rows, fillBlanksOnly: true);

    expect($suggested[0]['admission_number'])->toBe('FAHAN-015-2025')
        ->and($suggested[1]['admission_number'])->toBe('CUSTOM-99')
        ->and($suggested[2]['admission_number'])->toBe('FAHAN-016-2025');
});
