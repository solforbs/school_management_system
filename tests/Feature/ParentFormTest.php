<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

/*
 * Regression coverage for a production crash: the edit screen referenced
 * $this->linkedStudentIds without the component ever defining it, so
 * opening any parent's edit page 500'd with
 * "Property [$linkedStudentIds] not found on component: [parent::form]".
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeParentFormDirector(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Parent Form School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function makeLinkableStudent(Institution $institution, ?int $parentId = null): User
{
    $student = User::factory()->create(['name' => 'Student '.uniqid()]);

    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'parent_id' => $parentId,
        'admission_number' => 'ADM-'.uniqid(),
    ]);

    return $student;
}

function makeExistingParent(Institution $institution): User
{
    $parent = User::factory()->create();
    $parent->assignRole('Parent');

    DB::table('parent_details')->insert([
        'id' => $parent->id,
        'parent_id' => $parent->id,
    ]);

    return $parent->refresh();
}

test('the edit page renders without crashing on linkedStudentIds', function () {
    [$director, $institution] = makeParentFormDirector();
    $parent = makeExistingParent($institution);
    $linkedChild = makeLinkableStudent($institution, $parent->id);
    $unlinkedChild = makeLinkableStudent($institution);

    $this->actingAs($director)
        ->get(route('parent.edit', $parent->id))
        ->assertOk()
        ->assertSee($linkedChild->name)
        ->assertSee('Currently Linked')
        ->assertSee($unlinkedChild->name);
});

test('a currently linked child is pre-checked and shown as linked', function () {
    [$director, $institution] = makeParentFormDirector();
    $parent = makeExistingParent($institution);
    $linkedChild = makeLinkableStudent($institution, $parent->id);

    Livewire::actingAs($director)
        ->test('parent::form', ['parentId' => $parent->id])
        ->assertSee($linkedChild->name)
        ->assertSee('Currently Linked');
});

test('saving without unchecking a linked child keeps it linked', function () {
    [$director, $institution] = makeParentFormDirector();
    $parent = makeExistingParent($institution);
    $linkedChild = makeLinkableStudent($institution, $parent->id);

    // The form only ever posts the checkboxes it rendered - simulating
    // exactly what a real submit sends when nothing was unchecked.
    $this->actingAs($director)->put(route('parent.update', $parent->id), [
        'name' => $parent->name,
        'email' => $parent->email,
        'children' => [$linkedChild->id],
    ])->assertRedirect(route('parent.show', $parent->id));

    expect(StudentDetails::where('student_id', $linkedChild->id)->first()->parent_id)->toBe($parent->id);
});

test('unchecking a linked child unlinks it', function () {
    [$director, $institution] = makeParentFormDirector();
    $parent = makeExistingParent($institution);
    $linkedChild = makeLinkableStudent($institution, $parent->id);

    $this->actingAs($director)->put(route('parent.update', $parent->id), [
        'name' => $parent->name,
        'email' => $parent->email,
        'children' => [],
    ])->assertRedirect(route('parent.show', $parent->id));

    expect(StudentDetails::where('student_id', $linkedChild->id)->first()->parent_id)->toBeNull();
});

test('linking a new student while keeping the existing one keeps both linked', function () {
    [$director, $institution] = makeParentFormDirector();
    $parent = makeExistingParent($institution);
    $linkedChild = makeLinkableStudent($institution, $parent->id);
    $newChild = makeLinkableStudent($institution);

    $this->actingAs($director)->put(route('parent.update', $parent->id), [
        'name' => $parent->name,
        'email' => $parent->email,
        'children' => [$linkedChild->id, $newChild->id],
    ])->assertRedirect(route('parent.show', $parent->id));

    expect(StudentDetails::where('student_id', $linkedChild->id)->first()->parent_id)->toBe($parent->id)
        ->and(StudentDetails::where('student_id', $newChild->id)->first()->parent_id)->toBe($parent->id);
});

test('creating a parent still only offers unlinked students, with none marked as linked', function () {
    [$director, $institution] = makeParentFormDirector();
    $unlinkedChild = makeLinkableStudent($institution);

    Livewire::actingAs($director)
        ->test('parent::form')
        ->assertSee($unlinkedChild->name)
        ->assertDontSee('Currently Linked');
});
