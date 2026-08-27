<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeClassesDirector(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Classes School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

test('the livewire class form saves without a curriculum', function () {
    [$director, $institution] = makeClassesDirector();

    Livewire::actingAs($director)
        ->test('classes::form')
        ->set('name', 'Form 2 East')
        ->set('level', 'Form 2')
        // Left as the blank "School default" option, which posts an empty
        // string - it has to land as null, not '' (that was a hard database
        // error against an integer column).
        ->set('curriculum_id', '')
        ->set('class_teacher_id', '')
        ->set('capacity', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $schoolClass = SchoolClass::firstOrFail();

    expect($schoolClass->name)->toBe('Form 2 East')
        ->and($schoolClass->institution_id)->toBe($institution->id)
        ->and($schoolClass->curriculum_id)->toBeNull()
        ->and($schoolClass->class_teacher_id)->toBeNull()
        ->and($schoolClass->capacity)->toBeNull();
});

test('editing a class and clearing its curriculum stores null', function () {
    [$director, $institution] = makeClassesDirector();

    $curriculum = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    $schoolClass = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 North',
        'curriculum_id' => $curriculum->id,
    ]);

    Livewire::actingAs($director)
        ->test('classes::form', ['classId' => $schoolClass->id])
        ->assertSet('curriculum_id', (string) $curriculum->id)
        ->set('curriculum_id', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($schoolClass->refresh()->curriculum_id)->toBeNull();
});

test('the class endpoint also treats a blank curriculum as none', function () {
    [$director, $institution] = makeClassesDirector();

    $schoolClass = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 South',
    ]);

    $this->actingAs($director)->put(route('classes.update', $schoolClass->id), [
        'name' => 'Grade 8 South',
        'curriculum_id' => '',
        'class_teacher_id' => '',
        'capacity' => '',
    ])->assertRedirect();

    expect($schoolClass->refresh()->curriculum_id)->toBeNull()
        ->and($schoolClass->capacity)->toBeNull();
});

test('a class from another school is out of reach of the livewire form', function () {
    [$director] = makeClassesDirector();
    [, $otherSchool] = makeClassesDirector();

    $theirs = SchoolClass::create([
        'institution_id' => $otherSchool->id,
        'name' => 'Their Form 1',
    ]);

    // Scoped lookup, so another school's id simply isn't there to find.
    expect(fn () => Livewire::actingAs($director)->test('classes::form', ['classId' => $theirs->id]))
        ->toThrow(ModelNotFoundException::class);
});

test('the livewire class list searches and deletes in place', function () {
    [$director, $institution] = makeClassesDirector();

    $keep = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 West']);
    $drop = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 4 East']);

    Livewire::actingAs($director)
        ->test('classes::index')
        ->assertSee('Form 1 West')
        ->set('search', 'Form 4')
        ->assertSee('Form 4 East')
        ->assertDontSee('Form 1 West')
        ->set('search', '')
        ->call('delete', $drop->id)
        ->assertHasNoErrors();

    expect(SchoolClass::pluck('id')->all())->toBe([$keep->id]);
});

test('the livewire curriculum form records which system it grades on', function () {
    [$director, $institution] = makeClassesDirector();

    Livewire::actingAs($director)
        ->test('curriculum::form')
        ->set('name', 'Competency Based Curriculum')
        ->set('system', 'cbc')
        ->set('status', 'active')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $curriculum = Curriculum::firstOrFail();

    expect($curriculum->system)->toBe('cbc')
        ->and($curriculum->isCbc())->toBeTrue()
        ->and($curriculum->institution_id)->toBe($institution->id);
});

test('the livewire curriculum list deletes in place', function () {
    [$director, $institution] = makeClassesDirector();

    $curriculum = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => '8-4-4',
        'system' => '844',
        'status' => 'active',
    ]);

    Livewire::actingAs($director)
        ->test('curriculum::index')
        ->assertSee('8-4-4')
        ->call('delete', $curriculum->id)
        ->assertHasNoErrors();

    expect(Curriculum::count())->toBe(0);
});
