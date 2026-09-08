<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Database\Seeders\ExamTimetableSeeder;
use Modules\Examinations\Models\Examination;
use Modules\Examinations\Models\ExamSitting;
use Modules\Institution\Models\Institution;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Teacher\Models\TeacherDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * @return array{0: User, 1: Institution}
 */
function esSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Sitting School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function esTeacher(Institution $institution): User
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

function esClass(Institution $institution, string $name): SchoolClass
{
    return SchoolClass::create(['institution_id' => $institution->id, 'name' => $name]);
}

function esSubject(Institution $institution, string $name): Subject
{
    return Subject::create([
        'institution_id' => $institution->id,
        'name' => $name,
        'is_active' => true,
        'is_compulsory' => true,
    ]);
}

/**
 * @return Collection<int, User>
 */
function esStudents(Institution $institution, SchoolClass $class, int $count = 2)
{
    $parent = User::factory()->create();
    DB::table('parent_details')->insert(['id' => $parent->id, 'parent_id' => $parent->id]);

    return collect(range(1, $count))->map(function (int $i) use ($institution, $class, $parent) {
        $student = User::factory()->create(['name' => 'Pupil '.$i.' '.uniqid()]);

        StudentDetails::create([
            'student_id' => $student->id,
            'institution_id' => $institution->id,
            'class_id' => $class->id,
            'parent_id' => $parent->id,
            'admission_number' => 'ADM-'.$i.'-'.uniqid(),
        ]);

        return $student;
    });
}

test('creating an exam fans out one paper per subject per class', function () {
    [$director, $institution] = esSchool();

    $classA = esClass($institution, 'Grade 7 A');
    $classB = esClass($institution, 'Grade 7 B');
    esSubject($institution, 'Mathematics');
    esSubject($institution, 'English');
    esSubject($institution, 'Biology');

    $this->actingAs($director)->post(route('examinations.store'), [
        'title' => 'Mock Exam',
        'term' => 'Term 2',
        'exam_type' => 'mock',
        'academic_year' => 2026,
        'class_id' => '',
    ])->assertRedirect(route('examinations.index'));

    $sitting = ExamSitting::sole();

    expect($sitting->title)->toBe('Mock Exam')
        ->and($sitting->exam_type)->toBe('mock')
        ->and($sitting->class_id)->toBeNull()
        ->and($sitting->papers()->count())->toBe(6);

    $paper = $sitting->papers()->where('class_id', $classA->id)->first();
    expect($paper->subject_id)->not->toBeNull()
        ->and($paper->exam_date)->not->toBeNull()
        ->and((int) $paper->total_marks)->toBeGreaterThan(0);

    expect($sitting->papers()->distinct()->pluck('class_id')->sort()->values()->all())
        ->toBe(collect([$classA->id, $classB->id])->sort()->values()->all());
});

test('an exam targeted at one class only fans out for that class', function () {
    [$director, $institution] = esSchool();

    $classA = esClass($institution, 'Grade 8 A');
    esClass($institution, 'Grade 8 B');
    esSubject($institution, 'Mathematics');
    esSubject($institution, 'English');

    $this->actingAs($director)->post(route('examinations.store'), [
        'title' => 'Grade 8 A Retake',
        'term' => 'Term 2',
        'academic_year' => 2026,
        'class_id' => $classA->id,
    ])->assertRedirect(route('examinations.index'));

    $sitting = ExamSitting::sole();

    expect($sitting->class_id)->toBe($classA->id)
        ->and($sitting->papers()->count())->toBe(2)
        ->and($sitting->papers()->pluck('class_id')->unique()->all())->toBe([$classA->id]);
});

test('the exam picker shows one card per sitting, not one per subject', function () {
    [$director, $institution] = esSchool();

    esClass($institution, 'Grade 9 A');
    esClass($institution, 'Grade 9 B');
    esSubject($institution, 'Mathematics');
    esSubject($institution, 'English');
    esSubject($institution, 'Biology');

    $this->actingAs($director)->post(route('examinations.store'), [
        'title' => 'End Term Exam',
        'term' => 'Term 2',
        'exam_type' => 'end_term',
        'academic_year' => 2026,
    ]);

    expect(Examination::count())->toBe(6);

    Livewire::actingAs($director)->test('result::entry-exams')
        ->assertSee('End Term Exam')
        ->tap(fn ($c) => expect($c->instance()->sittings)->toHaveCount(1));
});

test('inside a sitting a teacher only sees the classes they teach', function () {
    [$director, $institution] = esSchool();
    $teacher = esTeacher($institution);

    $classA = esClass($institution, 'Grade 7 East');
    esClass($institution, 'Grade 7 West');
    $maths = esSubject($institution, 'Mathematics');
    esSubject($institution, 'English');

    $this->actingAs($director)->post(route('examinations.store'), [
        'title' => 'Mid Term Exam',
        'term' => 'Term 2',
        'exam_type' => 'mid_term',
        'academic_year' => 2026,
    ]);

    $this->actingAs($director)->post(route('subject.teachers.store'), [
        'class_id' => $classA->id,
        'subject_id' => $maths->id,
        'teacher_ids' => [$teacher->id],
    ]);

    $sitting = ExamSitting::sole();

    $classes = Livewire::actingAs($teacher)
        ->test('result::entry-classes', ['sittingId' => (string) $sitting->id])
        ->instance()->classes;

    expect($classes->pluck('id')->all())->toBe([$classA->id]);
});

test('the mark sheet auto-selects the one subject a teacher owns and saves against the right paper', function () {
    [$director, $institution] = esSchool();
    $teacher = esTeacher($institution);

    $class = esClass($institution, 'Grade 7 Only');
    $maths = esSubject($institution, 'Mathematics');
    esSubject($institution, 'English');
    $students = esStudents($institution, $class);

    $this->actingAs($director)->post(route('examinations.store'), [
        'title' => 'Opening Exam',
        'term' => 'Term 1',
        'exam_type' => 'opening',
        'academic_year' => 2026,
    ]);

    $this->actingAs($director)->post(route('subject.teachers.store'), [
        'class_id' => $class->id,
        'subject_id' => $maths->id,
        'teacher_ids' => [$teacher->id],
    ]);

    $sitting = ExamSitting::sole();
    $mathsPaper = $sitting->papers()->where('class_id', $class->id)->where('subject_id', $maths->id)->sole();

    Livewire::actingAs($teacher)->test('result::entry', [
        'sittingId' => (string) $sitting->id,
        'classId' => (string) $class->id,
    ])
        ->assertSet('subjectId', (string) $maths->id)
        ->assertSet('subjectLocked', true)
        ->assertSet('classLocked', true)
        ->assertSet('examinationId', (string) $mathsPaper->id)
        ->set('marks', [
            $students[0]->id => 70,
            $students[1]->id => 45,
        ])
        ->call('save');

    expect(Result::where('examination_id', $mathsPaper->id)->count())->toBe(2)
        ->and(Result::where('student_id', $students[0]->id)->first()->subject_id)->toBe($maths->id)
        ->and((float) Result::where('student_id', $students[0]->id)->first()->marks_obtained)->toBe(70.0);
});

test('the exam timetable seeder creates one sitting and is idempotent', function () {
    [, $institution] = esSchool();

    esClass($institution, 'Grade 7 S');
    esClass($institution, 'Grade 8 S');
    esSubject($institution, 'Mathematics');
    esSubject($institution, 'English');

    (new ExamTimetableSeeder)->run();
    $afterFirst = Examination::count();

    (new ExamTimetableSeeder)->run();

    expect(ExamSitting::count())->toBe(1)
        ->and(Examination::count())->toBe($afterFirst)
        ->and($afterFirst)->toBe(4);
});
