<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Support\GradingScaleDefaults;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Teacher\Models\TeacherDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeGradedSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Graded School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function makeSchoolTeacher(Institution $institution): User
{
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    return $teacher->refresh();
}

function makeGradedClass(Institution $institution, array $overrides = []): SchoolClass
{
    return SchoolClass::create(array_merge([
        'institution_id' => $institution->id,
        'name' => 'Form 2 '.uniqid(),
    ], $overrides));
}

function makeGradedSubject(Institution $institution, string $name = 'Mathematics'): Subject
{
    return Subject::create([
        'institution_id' => $institution->id,
        'name' => $name,
        'is_active' => true,
        'is_compulsory' => true,
    ]);
}

function makeGradedExamination(Institution $institution, SchoolClass $class, Subject $subject, int $total = 100): Examination
{
    return Examination::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'title' => 'End of Term 2 '.$subject->name,
        'term' => 'Second Term',
        'academic_year' => 2026,
        'exam_date' => '2026-08-10',
        'total_marks' => $total,
    ]);
}

/**
 * student_details.parent_id is non-null and foreign-keyed to
 * parent_details.id rather than users.id - see the standing bug; lining the
 * ids up sidesteps it without depending on that fix.
 */
function makeSchoolParent(): int
{
    $parent = User::factory()->create();

    DB::table('parent_details')->insert([
        'id' => $parent->id,
        'parent_id' => $parent->id,
    ]);

    return $parent->id;
}

/**
 * @return Collection<int, User>
 */
function enrolStudents(Institution $institution, SchoolClass $class, int $count = 3)
{
    $parentId = makeSchoolParent();

    return collect(range(1, $count))->map(function (int $index) use ($institution, $class, $parentId) {
        $student = User::factory()->create(['name' => 'Learner '.$index.' '.uniqid()]);

        StudentDetails::create([
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'class_id' => $class->id,
            'parent_id' => $parentId,
            'admission_number' => 'ADM-'.$index.'-'.uniqid(),
        ]);

        return $student;
    });
}

function loadScale(Institution $institution, ?Curriculum $curriculum, string $system): void
{
    foreach (GradingScaleDefaults::forSystem($system) as $band) {
        GradingBand::create($band + [
            'institution_id' => $institution->id,
            'curriculum_id' => $curriculum?->id,
        ]);
    }
}

test('a subject teacher enters marks for the whole class in one go', function () {
    [$director, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $class, $maths);
    $students = enrolStudents($institution, $class);

    loadScale($institution, null, '844');

    // The Director puts the teacher down for Maths in that class.
    $this->actingAs($director)->post(route('subject.teachers.store'), [
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_ids' => [$teacher->id],
    ])->assertRedirect(route('subject.teachers.index'));

    $marks = [
        $students[0]->id => 85,
        $students[1]->id => 52,
        $students[2]->id => 20,
    ];

    $this->actingAs($teacher)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => $marks,
    ])->assertRedirect(route('result.entry.create', ['examination_id' => $examination->id]));

    expect(Result::count())->toBe(3)
        ->and(Result::where('student_id', $students[0]->id)->first()->grade)->toBe('A')
        ->and(Result::where('student_id', $students[1]->id)->first()->grade)->toBe('C')
        ->and(Result::where('student_id', $students[2]->id)->first()->grade)->toBe('E')
        ->and(Result::where('student_id', $students[0]->id)->first()->class_id)->toBe($class->id)
        ->and(Result::where('student_id', $students[0]->id)->first()->recorded_by)->toBe($teacher->id);
});

test('re-saving the sheet corrects a mark rather than duplicating it', function () {
    [, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $class, $maths);
    $students = enrolStudents($institution, $class, 1);

    loadScale($institution, null, '844');

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_id' => $teacher->id,
    ]);

    $this->actingAs($teacher)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => [$students[0]->id => 40],
    ]);

    $this->actingAs($teacher)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => [$students[0]->id => 90],
    ]);

    expect(Result::count())->toBe(1)
        ->and((float) Result::first()->marks_obtained)->toBe(90.0)
        ->and(Result::first()->grade)->toBe('A');
});

test('a blank row leaves a recorded mark alone', function () {
    [, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $class, $maths);
    $students = enrolStudents($institution, $class, 2);

    loadScale($institution, null, '844');

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_id' => $teacher->id,
    ]);

    $this->actingAs($teacher)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => [$students[0]->id => 70, $students[1]->id => 60],
    ]);

    $this->actingAs($teacher)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => [$students[0]->id => 75, $students[1]->id => ''],
    ]);

    expect((float) Result::where('student_id', $students[0]->id)->first()->marks_obtained)->toBe(75.0)
        ->and((float) Result::where('student_id', $students[1]->id)->first()->marks_obtained)->toBe(60.0);
});

test('a teacher cannot enter marks for a subject they are not assigned', function () {
    [, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $english = makeGradedSubject($institution, 'English');
    $englishExam = makeGradedExamination($institution, $class, $english);
    $students = enrolStudents($institution, $class, 1);

    // Assigned Maths in that class - which says nothing about English.
    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_id' => $teacher->id,
    ]);

    $this->actingAs($teacher)
        ->get(route('result.entry.create', ['examination_id' => $englishExam->id]))
        ->assertForbidden();

    $this->actingAs($teacher)->post(route('result.entry.store'), [
        'examination_id' => $englishExam->id,
        'marks' => [$students[0]->id => 90],
    ])->assertForbidden();

    expect(Result::count())->toBe(0);
});

test('a class teacher enters marks for every subject in their own class', function () {
    [, $institution] = makeGradedSchool();
    $classTeacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution, ['class_teacher_id' => $classTeacher->id]);
    $english = makeGradedSubject($institution, 'English');
    $examination = makeGradedExamination($institution, $class, $english);
    $students = enrolStudents($institution, $class, 2);

    loadScale($institution, null, '844');

    // No subject assignment at all - being the class teacher is the whole
    // of their claim to these marks.
    $this->actingAs($classTeacher)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => [$students[0]->id => 66, $students[1]->id => 47],
    ])->assertRedirect(route('result.entry.create', ['examination_id' => $examination->id]));

    expect(Result::count())->toBe(2)
        ->and(Result::where('student_id', $students[0]->id)->first()->grade)->toBe('B')
        ->and(Result::where('student_id', $students[1]->id)->first()->grade)->toBe('C-');
});

test('a class teacher of one class cannot mark another class', function () {
    [, $institution] = makeGradedSchool();
    $classTeacher = makeSchoolTeacher($institution);

    makeGradedClass($institution, ['class_teacher_id' => $classTeacher->id]);

    $otherClass = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $otherClass, $maths);
    $students = enrolStudents($institution, $otherClass, 1);

    $this->actingAs($classTeacher)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => [$students[0]->id => 80],
    ])->assertForbidden();

    expect(Result::count())->toBe(0);
});

test('marks over the examination total are refused and nothing is saved', function () {
    [$director, $institution] = makeGradedSchool();

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $class, $maths, 50);
    $students = enrolStudents($institution, $class, 2);

    loadScale($institution, null, '844');

    $this->actingAs($director)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => [$students[0]->id => 45, $students[1]->id => 60],
    ])->assertRedirect(route('result.entry.create', ['examination_id' => $examination->id]));

    // All or nothing: the valid row isn't quietly saved alongside the bad
    // one.
    expect(Result::count())->toBe(0);
});

test('a student from another class cannot be smuggled onto the sheet', function () {
    [$director, $institution] = makeGradedSchool();

    $class = makeGradedClass($institution);
    $otherClass = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $class, $maths);

    enrolStudents($institution, $class, 1);
    $outsider = enrolStudents($institution, $otherClass, 1)->first();

    $this->actingAs($director)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => [$outsider->id => 90],
    ])->assertForbidden();

    expect(Result::count())->toBe(0);
});

test('a cbc class is graded against expectations while an 8-4-4 class keeps letter grades', function () {
    [$director, $institution] = makeGradedSchool();

    $cbc = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    $eightFourFour = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => '8-4-4',
        'system' => '844',
        'status' => 'active',
    ]);

    loadScale($institution, $cbc, 'cbc');
    loadScale($institution, $eightFourFour, '844');

    $maths = makeGradedSubject($institution);

    $cbcClass = makeGradedClass($institution, ['curriculum_id' => $cbc->id]);
    $cbcExam = makeGradedExamination($institution, $cbcClass, $maths);
    $cbcStudent = enrolStudents($institution, $cbcClass, 1)->first();

    $legacyClass = makeGradedClass($institution, ['curriculum_id' => $eightFourFour->id]);
    $legacyExam = makeGradedExamination($institution, $legacyClass, $maths);
    $legacyStudent = enrolStudents($institution, $legacyClass, 1)->first();

    $this->actingAs($director)->post(route('result.entry.store'), [
        'examination_id' => $cbcExam->id,
        'marks' => [$cbcStudent->id => 82],
    ]);

    $this->actingAs($director)->post(route('result.entry.store'), [
        'examination_id' => $legacyExam->id,
        'marks' => [$legacyStudent->id => 82],
    ]);

    expect(Result::where('student_id', $cbcStudent->id)->first()->grade)->toBe('EE2')
        ->and(Result::where('student_id', $legacyStudent->id)->first()->grade)->toBe('A');
});

test('the cbc scale covers each of the four bands through its eight levels', function () {
    [$director, $institution] = makeGradedSchool();

    $cbc = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    loadScale($institution, $cbc, 'cbc');

    $class = makeGradedClass($institution, ['curriculum_id' => $cbc->id]);
    $subject = makeGradedSubject($institution, 'Integrated Science');
    $examination = makeGradedExamination($institution, $class, $subject);
    $students = enrolStudents($institution, $class, 4);

    $this->actingAs($director)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => [
            $students[0]->id => 95,
            $students[1]->id => 65,
            $students[2]->id => 45,
            $students[3]->id => 20,
        ],
    ]);

    $gradeFor = fn (int $index) => Result::where('student_id', $students[$index]->id)->first()->grade;

    // The mark lands on a level, and the level names its band.
    expect($gradeFor(0))->toBe('EE1')
        ->and($gradeFor(1))->toBe('ME1')
        ->and($gradeFor(2))->toBe('ME2')
        ->and($gradeFor(3))->toBe('BE1')
        ->and(collect([0, 1, 2, 3])->map(fn ($i) => GradingScaleDefaults::bandFor($gradeFor($i)))->all())
        ->toBe(['EE', 'ME', 'ME', 'BE']);
});

test('a director loads the standard scale for a curriculum once', function () {
    [$director, $institution] = makeGradedSchool();

    $cbc = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    $this->actingAs($director)->post(route('reportcard.gradingbands.defaults'), [
        'institution_id' => $institution->id,
        'curriculum_id' => $cbc->id,
    ])->assertRedirect();

    expect(GradingBand::where('curriculum_id', $cbc->id)->count())->toBe(8);

    // A second run refuses rather than doubling up or wiping a scale the
    // school has since tuned.
    $this->actingAs($director)->post(route('reportcard.gradingbands.defaults'), [
        'institution_id' => $institution->id,
        'curriculum_id' => $cbc->id,
    ]);

    expect(GradingBand::where('curriculum_id', $cbc->id)->count())->toBe(8);
});

test('a teacher from another school cannot be assigned a subject', function () {
    [$director, $institution] = makeGradedSchool();
    [, $otherSchool] = makeGradedSchool();

    $outsider = makeSchoolTeacher($otherSchool);
    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);

    $this->actingAs($director)->post(route('subject.teachers.store'), [
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_ids' => [$outsider->id],
    ])->assertForbidden();

    expect(SubjectTeacher::count())->toBe(0);
});

test('a teacher only sees results for what they teach', function () {
    [, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $english = makeGradedSubject($institution, 'English');

    $mathsExam = makeGradedExamination($institution, $class, $maths);
    $englishExam = makeGradedExamination($institution, $class, $english);
    $students = enrolStudents($institution, $class, 1);

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_id' => $teacher->id,
    ]);

    $mine = Result::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'student_id' => $students[0]->id,
        'examination_id' => $mathsExam->id,
        'marks_obtained' => 70,
    ]);

    $theirs = Result::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'student_id' => $students[0]->id,
        'examination_id' => $englishExam->id,
        'marks_obtained' => 80,
    ]);

    $this->actingAs($teacher)->get(route('result.show', $mine->id))->assertOk();
    $this->actingAs($teacher)->get(route('result.show', $theirs->id))->assertNotFound();
});

test('the marks sheet lists every student in the class with their recorded marks', function () {
    [, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $class, $maths);
    $students = enrolStudents($institution, $class, 3);

    loadScale($institution, null, '844');

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_id' => $teacher->id,
    ]);

    $this->actingAs($teacher)->post(route('result.entry.store'), [
        'examination_id' => $examination->id,
        'marks' => [$students[0]->id => 88],
    ]);

    $this->actingAs($teacher)
        ->get(route('result.entry.create', ['examination_id' => $examination->id]))
        ->assertOk()
        ->assertSee($students[0]->name)
        ->assertSee($students[1]->name)
        ->assertSee($students[2]->name)
        ->assertSee('88');
});

test('the subject teachers screen renders for a director', function () {
    [$director, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_id' => $teacher->id,
    ]);

    $this->actingAs($director)->get(route('subject.teachers.index'))
        ->assertOk()
        ->assertSee($class->name)
        ->assertSee('Mathematics')
        ->assertSee($teacher->name);
});

test('the livewire picker assigns several teachers at once, in place', function () {
    [$director, $institution] = makeGradedSchool();
    $first = makeSchoolTeacher($institution);
    $second = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);

    Livewire::actingAs($director)
        ->test('subject::teachers')
        ->set('classId', (string) $class->id)
        ->set('subjectId', (string) $maths->id)
        ->call('toggle', (string) $first->id)
        ->call('toggle', (string) $second->id)
        ->call('assign')
        ->assertHasNoErrors()
        // No redirect: the list below the form refreshes in place.
        ->assertNoRedirect()
        ->assertSee($first->name);

    expect(SubjectTeacher::count())->toBe(2);

    // Assigning the same teacher again is a no-op rather than a duplicate.
    Livewire::actingAs($director)
        ->test('subject::teachers')
        ->set('classId', (string) $class->id)
        ->set('subjectId', (string) $maths->id)
        ->call('toggle', (string) $first->id)
        ->call('assign')
        ->assertHasNoErrors();

    expect(SubjectTeacher::count())->toBe(2);
});

test('the livewire picker searches teachers and refuses an empty selection', function () {
    [$director, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);

    Livewire::actingAs($director)
        ->test('subject::teachers')
        // The teacher's name still appears in the filter dropdown below, so
        // the empty state is what tells us the card grid filtered down.
        ->set('teacherSearch', 'zzz-nobody')
        ->assertSee('No teacher matches')
        ->set('teacherSearch', '')
        ->assertSee($teacher->name)
        ->set('classId', (string) $class->id)
        ->set('subjectId', (string) $maths->id)
        ->call('assign')
        ->assertHasErrors('selected');

    expect(SubjectTeacher::count())->toBe(0);
});

test('the livewire picker removes an assignment in place', function () {
    [$director, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);

    $assignment = SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_id' => $teacher->id,
    ]);

    Livewire::actingAs($director)
        ->test('subject::teachers')
        ->call('remove', $assignment->id)
        ->assertHasNoErrors();

    expect(SubjectTeacher::count())->toBe(0);
});

test('report card settings shows the scale for the chosen curriculum', function () {
    [$director, $institution] = makeGradedSchool();

    $cbc = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    loadScale($institution, $cbc, 'cbc');

    $this->actingAs($director)
        ->get(route('reportcard.settings', ['curriculum_id' => $cbc->id]))
        ->assertOk()
        ->assertSee('EE')
        ->assertSee('ME')
        ->assertSee('AE')
        ->assertSee('BE');

    // The school-wide scale is a separate ledger, and starts empty.
    $this->actingAs($director)->get(route('reportcard.settings'))
        ->assertOk()
        ->assertSee('No bands on this scale yet.');
});

test('the livewire marks sheet saves a whole class in place', function () {
    [, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $class, $maths);
    $students = enrolStudents($institution, $class, 3);

    loadScale($institution, null, '844');

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_id' => $teacher->id,
    ]);

    Livewire::actingAs($teacher)
        ->test('result::entry')
        ->set('examinationId', (string) $examination->id)
        ->assertSee($students[0]->name)
        ->set('marks.'.$students[0]->id, '85')
        ->set('marks.'.$students[1]->id, '52')
        ->call('save')
        ->assertHasNoErrors()
        // No redirect: the sheet stays open with the grades filled in.
        ->assertNoRedirect()
        ->assertSee('A');

    expect(Result::count())->toBe(2)
        ->and(Result::where('student_id', $students[0]->id)->first()->grade)->toBe('A')
        ->and(Result::where('student_id', $students[1]->id)->first()->grade)->toBe('C');
});

test('the livewire marks sheet refuses a mark over the paper total', function () {
    [$director, $institution] = makeGradedSchool();

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $class, $maths, 50);
    $students = enrolStudents($institution, $class, 2);

    loadScale($institution, null, '844');

    Livewire::actingAs($director)
        ->test('result::entry')
        ->set('examinationId', (string) $examination->id)
        ->set('marks.'.$students[0]->id, '45')
        ->set('marks.'.$students[1]->id, '60')
        ->call('save')
        ->assertHasErrors('marks.'.$students[1]->id);

    // All or nothing: the valid row isn't quietly saved alongside the bad one.
    expect(Result::count())->toBe(0);
});

test('a teacher cannot open the livewire sheet for a subject they are not assigned', function () {
    [, $institution] = makeGradedSchool();
    $teacher = makeSchoolTeacher($institution);

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $english = makeGradedSubject($institution, 'English');
    $englishExam = makeGradedExamination($institution, $class, $english);

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_id' => $teacher->id,
    ]);

    Livewire::actingAs($teacher)
        ->test('result::entry')
        ->set('examinationId', (string) $englishExam->id)
        ->assertForbidden();
});

test('the livewire result list filters and deletes in place', function () {
    [$director, $institution] = makeGradedSchool();

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $class, $maths);
    $students = enrolStudents($institution, $class, 2);

    $result = Result::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'student_id' => $students[0]->id,
        'examination_id' => $examination->id,
        'marks_obtained' => 70,
    ]);

    Livewire::actingAs($director)
        ->test('result::index')
        ->assertSee($students[0]->name)
        ->set('search', 'nobody-by-this-name')
        ->assertDontSee($students[0]->name)
        ->set('search', '')
        ->call('delete', $result->id)
        ->assertHasNoErrors();

    expect(Result::count())->toBe(0);
});

test('the livewire result form records a single mark', function () {
    [$director, $institution] = makeGradedSchool();

    $class = makeGradedClass($institution);
    $maths = makeGradedSubject($institution);
    $examination = makeGradedExamination($institution, $class, $maths);
    $students = enrolStudents($institution, $class, 1);

    loadScale($institution, null, '844');

    Livewire::actingAs($director)
        ->test('result::form')
        ->set('class_id', (string) $class->id)
        ->set('student_id', (string) $students[0]->id)
        ->set('examination_id', (string) $examination->id)
        ->set('marks_obtained', '77')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Result::first()->grade)->toBe('A-');
});
