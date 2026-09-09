<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * A school with two families, each a parent and one enrolled child, and a
 * fee invoice raised against each child.
 *
 * @return array{director: User, parentA: User, parentB: User, feeA: Fee, feeB: Fee}
 */
function feeScopingSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Fee Scope Academy '.uniqid(),
        'code' => 'FSA-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);
    $director->update(['active_institution_id' => $institution->id]);

    $make = function (string $tag) use ($institution): array {
        $parent = User::factory()->create(['active_institution_id' => $institution->id]);
        $parent->assignRole('Parent');

        $child = User::factory()->create(['name' => "Child {$tag}"]);
        $child->assignRole('Student');
        StudentDetails::create([
            'student_id' => $child->id,
            'institution_id' => $institution->id,
            'parent_id' => $parent->id,
            'admission_number' => 'ADM-'.uniqid(),
            'enrollment_status' => 'active',
        ]);

        $fee = Fee::create([
            'institution_id' => $institution->id,
            'student_id' => $child->id,
            'parent_id' => $parent->id,
            'title' => "Term 1 Tuition {$tag}",
            'fee_type' => 'tuition',
            'amount' => 10000,
            'amount_paid' => 4000,
        ]);

        return [$parent->refresh(), $child->refresh(), $fee];
    };

    [$parentA, , $feeA] = $make('Alpha');
    [$parentB, , $feeB] = $make('Bravo');

    return compact('director', 'parentA', 'parentB', 'feeA', 'feeB');
}

test('a parent sees only their own child\'s fees on the fee listing', function () {
    ['parentA' => $parentA, 'feeA' => $feeA, 'feeB' => $feeB] = feeScopingSchool();

    $this->actingAs($parentA)->get(route('feemanagement.index'))
        ->assertOk()
        ->assertSee($feeA->title)
        ->assertDontSee($feeB->title);
});

test('a director sees every family\'s fees on the fee listing', function () {
    ['director' => $director, 'feeA' => $feeA, 'feeB' => $feeB] = feeScopingSchool();

    $this->actingAs($director)->get(route('feemanagement.index'))
        ->assertOk()
        ->assertSee($feeA->title)
        ->assertSee($feeB->title);
});

test('a parent cannot open another family\'s fee detail page', function () {
    ['parentA' => $parentA, 'feeA' => $feeA, 'feeB' => $feeB] = feeScopingSchool();

    $this->actingAs($parentA)->get(route('feemanagement.show', $feeA->id))->assertOk();
    $this->actingAs($parentA)->get(route('feemanagement.show', $feeB->id))->assertNotFound();
});

test('the visibleTo scope narrows a query to the parent\'s children', function () {
    ['parentA' => $parentA, 'feeA' => $feeA] = feeScopingSchool();

    $visible = Fee::query()->visibleTo($parentA)->pluck('id');

    expect($visible)->toHaveCount(1)
        ->and($visible->first())->toBe($feeA->id);
});
