<?php

use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\PermissionSeeder;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->director = User::factory()->create();
    $this->director->assignRole('Director');
    $this->institution = Institution::create([
        'user_id' => $this->director->id,
        'name' => 'Arrears Academy',
        'code' => 'ARR-'.uniqid(),
        'type' => 'School',
        'is_approved' => true,
    ]);
    $this->director->update(['active_institution_id' => $this->institution->id]);
});

function overdueFee(Institution $institution, User $student, float $amount, float $paid = 0): Fee
{
    return Fee::create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'title' => 'Term fee',
        'amount' => $amount,
        'amount_paid' => $paid,
        'due_date' => today()->subWeek(),
    ]);
}

test('defaulters are counted once per student, not once per overdue fee', function () {
    $amina = User::factory()->create();
    $brian = User::factory()->create();
    $cleared = User::factory()->create();

    // Amina owes on three separate fees, Brian on one - two defaulters.
    overdueFee($this->institution, $amina, 1000);
    overdueFee($this->institution, $amina, 2000);
    overdueFee($this->institution, $amina, 500, 100);
    overdueFee($this->institution, $brian, 3000);
    // Paid in full: not a defaulter.
    overdueFee($this->institution, $cleared, 1500, 1500);
    // Not due yet: not a defaulter.
    Fee::create([
        'institution_id' => $this->institution->id,
        'student_id' => $brian->id,
        'title' => 'Next term',
        'amount' => 4000,
        'amount_paid' => 0,
        'due_date' => today()->addMonth(),
    ]);

    $stats = app(AnalyticsService::class)->institutionStats($this->director);

    expect($stats['overdue_students_count'])->toBe(2)
        ->and($stats['overdue_fees_count'])->toBe(4);
});

test('the dashboard shows the number of defaulting students', function () {
    $amina = User::factory()->create();
    overdueFee($this->institution, $amina, 1000);
    overdueFee($this->institution, $amina, 2000);

    $this->actingAs($this->director)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('1 overdue defaulters');
});
