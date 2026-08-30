<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Selections\Models\SubjectSelection;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeStudentWithInstitution(int $min = 1, int $max = 3): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Selection Academy '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
        'min_electives' => $min,
        'max_electives' => $max,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Form 3 North',
    ]);

    $student = User::factory()->create();
    $student->assignRole('Student');

    $studentDetails = StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
    ]);

    $compulsory = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'English',
        'is_compulsory' => true,
        'is_active' => true,
    ]);

    $elective1 = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'French',
        'is_compulsory' => false,
        'is_active' => true,
    ]);

    $elective2 = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'German',
        'is_compulsory' => false,
        'is_active' => true,
    ]);

    $elective3 = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Music',
        'is_compulsory' => false,
        'is_active' => true,
    ]);

    return [
        $student->refresh(),
        $institution,
        $class,
        [$elective1, $elective2, $elective3],
        $compulsory,
    ];
}

test('a student selects elective subjects via controller endpoint', function () {
    [$student, $institution, $class, $electives] = makeStudentWithInstitution(1, 2);

    $this->actingAs($student)->post(route('selections.store'), [
        'subject_ids' => [$electives[0]->id, $electives[1]->id],
    ])->assertRedirect(route('selections.index'));

    expect(SubjectSelection::where('student_id', $student->id)->count())->toBe(2);
    $selectedIds = SubjectSelection::where('student_id', $student->id)->pluck('subject_id')->all();
    expect($selectedIds)->toContain($electives[0]->id, $electives[1]->id);
});

test('a student cannot pick fewer electives than minimum allowed', function () {
    [$student, $institution] = makeStudentWithInstitution(2, 4);

    $this->actingAs($student)->post(route('selections.store'), [
        'subject_ids' => [],
    ])->assertSessionHas('error');

    expect(SubjectSelection::where('student_id', $student->id)->count())->toBe(0);
});

test('a student cannot pick more electives than maximum allowed', function () {
    [$student, $institution, $class, $electives] = makeStudentWithInstitution(1, 2);

    $this->actingAs($student)->post(route('selections.store'), [
        'subject_ids' => [$electives[0]->id, $electives[1]->id, $electives[2]->id],
    ])->assertSessionHas('error');

    expect(SubjectSelection::where('student_id', $student->id)->count())->toBe(0);
});

test('a student cannot pick electives from another institution', function () {
    [$student, $institution] = makeStudentWithInstitution(1, 2);

    $otherInstitution = Institution::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Other School',
        'code' => 'OTHER-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
    ]);

    $foreignSubject = Subject::create([
        'institution_id' => $otherInstitution->id,
        'name' => 'Foreign Art',
        'is_compulsory' => false,
        'is_active' => true,
    ]);

    $this->actingAs($student)->post(route('selections.store'), [
        'subject_ids' => [$foreignSubject->id],
    ])->assertSessionHas('error');

    expect(SubjectSelection::where('student_id', $student->id)->count())->toBe(0);
});

test('the livewire subject picker renders compulsory and elective subjects and saves choices', function () {
    [$student, $institution, $class, $electives, $compulsory] = makeStudentWithInstitution(1, 2);

    Livewire::actingAs($student)
        ->test('selections::index')
        ->assertSee('Compulsory Subjects')
        ->assertSee($compulsory->name)
        ->assertSee($electives[0]->name)
        ->assertSee($electives[1]->name)
        ->set('selected', [(string) $electives[0]->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(SubjectSelection::where('student_id', $student->id)->count())->toBe(1)
        ->and(SubjectSelection::first()->subject_id)->toBe($electives[0]->id);
});
