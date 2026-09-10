<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Models\StudentDetails;

/*
 * A parent account belongs to one school. Before parent_details carried an
 * institution_id, a parent with no student linked yet was owned by nobody -
 * and every Director could list, open, edit and delete them. These cover the
 * boundary from both sides: a school reaches its own parents, and cannot
 * reach another school's.
 */

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function isolationSchool(string $label): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => $label.' '.uniqid(),
        'code' => 'ISO-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function isolationParent(?Institution $owner): User
{
    $parent = User::factory()->create();
    $parent->assignRole('Parent');

    ParentDetails::create([
        'parent_id' => $parent->id,
        'institution_id' => $owner?->id,
        'parent_phone' => '07'.random_int(10000000, 99999999),
    ]);

    return $parent;
}

test('a director cannot open a parent owned by another school', function () {
    [, $theirs] = isolationSchool('Their School');
    [$ourDirector] = isolationSchool('Our School');

    $theirParent = isolationParent($theirs);

    $this->actingAs($ourDirector)
        ->get(route('parent.show', $theirParent->id))
        ->assertForbidden();
});

test('a director cannot delete a parent owned by another school', function () {
    [, $theirs] = isolationSchool('Their School');
    [$ourDirector] = isolationSchool('Our School');

    $theirParent = isolationParent($theirs);

    $this->actingAs($ourDirector)
        ->delete(route('parent.destroy', $theirParent->id))
        ->assertForbidden();

    expect(User::find($theirParent->id))->not->toBeNull();
});

test('a parent with no children yet is still reachable by the school that created them', function () {
    [$ourDirector, $ours] = isolationSchool('Our School');

    $ourParent = isolationParent($ours);

    $this->actingAs($ourDirector)
        ->get(route('parent.show', $ourParent->id))
        ->assertOk();
});

test('a parent owned by nobody is not reachable by any director', function () {
    [$ourDirector] = isolationSchool('Our School');

    // A legacy row the backfill could not resolve: no owner, no children.
    $orphan = isolationParent(null);

    $this->actingAs($ourDirector)
        ->get(route('parent.show', $orphan->id))
        ->assertForbidden();
});

test('a child enrolled here grants access even when the row predates ownership', function () {
    [$ourDirector, $ours] = isolationSchool('Our School');

    $legacyParent = isolationParent(null);

    $student = User::factory()->create();
    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $ours->id,
        'parent_id' => $legacyParent->id,
        'admission_number' => 'ADM-'.uniqid(),
    ]);

    $this->actingAs($ourDirector)
        ->get(route('parent.show', $legacyParent->id))
        ->assertOk();
});

test('the parent directory lists only this school\'s parents', function () {
    [, $theirs] = isolationSchool('Their School');
    [$ourDirector, $ours] = isolationSchool('Our School');

    $ourParent = isolationParent($ours);
    $theirParent = isolationParent($theirs);
    $orphan = isolationParent(null);

    $listed = User::role('Parent')
        ->whereHas('parent', fn ($pq) => $pq->where('institution_id', $ours->id))
        ->orWhereHas('children', fn ($cq) => $cq->where('institution_id', $ours->id))
        ->pluck('id');

    expect($listed)->toContain($ourParent->id)
        ->and($listed)->not->toContain($theirParent->id)
        ->and($listed)->not->toContain($orphan->id);
});
