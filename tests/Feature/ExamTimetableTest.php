<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;
use Modules\Teacher\Models\TeacherDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeTimetableSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Timetable School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    return [$director->refresh(), $institution];
}

function makeSitting(Institution $institution, SchoolClass $class, string $subjectName, array $overrides = []): Examination
{
    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => $subjectName,
        'is_active' => true,
    ]);

    return Examination::create(array_merge([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'title' => $subjectName.' Paper 1',
        'term' => 'Second Term',
        'exam_type' => 'end_term',
        'academic_year' => 2026,
        'exam_date' => '2026-08-10',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'total_marks' => 100,
    ], $overrides));
}

test('the timetable groups papers by class and orders them by when they are sat', function () {
    [$director, $institution] = makeTimetableSchool();

    $formOne = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 A']);
    $formTwo = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 2 B']);

    makeSitting($institution, $formOne, 'Mathematics', ['exam_date' => '2026-08-12', 'start_time' => '08:00']);
    makeSitting($institution, $formOne, 'English', ['exam_date' => '2026-08-10', 'start_time' => '08:00']);
    makeSitting($institution, $formTwo, 'Kiswahili', ['exam_date' => '2026-08-11']);

    $component = Livewire::actingAs($director)
        ->test('examinations::timetable')
        ->set('term', 'Second Term')
        ->set('examType', 'end_term');

    $groups = $component->instance()->groups;

    expect($groups)->toHaveCount(2)
        ->and($groups[0]['class_name'])->toBe('Form 1 A')
        ->and($groups[1]['class_name'])->toBe('Form 2 B')
        // Earliest paper first within a class.
        ->and($groups[0]['examinations']->first()->title)->toBe('English Paper 1')
        ->and($component->instance()->paperCount)->toBe(3);
});

test('the mid term sitting excludes end term papers', function () {
    [$director, $institution] = makeTimetableSchool();

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 3']);

    makeSitting($institution, $class, 'Biology', ['exam_type' => 'mid_term']);
    makeSitting($institution, $class, 'Physics', ['exam_type' => 'end_term']);

    $midTerm = Livewire::actingAs($director)
        ->test('examinations::timetable')
        ->set('examType', 'mid_term');

    expect($midTerm->instance()->paperCount)->toBe(1)
        ->and($midTerm->instance()->groups[0]['examinations']->first()->title)->toBe('Biology Paper 1');

    $endTerm = Livewire::actingAs($director)
        ->test('examinations::timetable')
        ->set('examType', 'end_term');

    expect($endTerm->instance()->paperCount)->toBe(1)
        ->and($endTerm->instance()->groups[0]['examinations']->first()->title)->toBe('Physics Paper 1');
});

test('narrowing to one class prints only that class', function () {
    [$director, $institution] = makeTimetableSchool();

    $formOne = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 A']);
    $formTwo = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 2 B']);

    makeSitting($institution, $formOne, 'Mathematics');
    makeSitting($institution, $formTwo, 'Kiswahili');

    $component = Livewire::actingAs($director)
        ->test('examinations::timetable')
        ->set('classId', (string) $formTwo->id);

    expect($component->instance()->groups)->toHaveCount(1)
        ->and($component->instance()->groups[0]['class_name'])->toBe('Form 2 B');
});

test('another school\'s papers never appear on the timetable', function () {
    [$director, $institution] = makeTimetableSchool();
    [, $otherSchool] = makeTimetableSchool();

    $ours = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Our Form 1']);
    $theirs = SchoolClass::create(['institution_id' => $otherSchool->id, 'name' => 'Their Form 1']);

    makeSitting($institution, $ours, 'Mathematics');
    makeSitting($otherSchool, $theirs, 'Chemistry');

    $component = Livewire::actingAs($director)->test('examinations::timetable');

    expect($component->instance()->paperCount)->toBe(1)
        ->and($component->instance()->groups[0]['class_name'])->toBe('Our Form 1');
});

test('papers sat on the same day are ordered by the time they start', function () {
    [$director, $institution] = makeTimetableSchool();

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 4']);

    makeSitting($institution, $class, 'History', ['exam_date' => '2026-08-09', 'start_time' => '14:00', 'end_time' => '16:00']);
    makeSitting($institution, $class, 'Geography', ['exam_date' => '2026-08-09', 'start_time' => '08:00', 'end_time' => '10:00']);

    $component = Livewire::actingAs($director)->test('examinations::timetable');

    $papers = $component->instance()->groups[0]['examinations'];

    expect($papers->first()->title)->toBe('Geography Paper 1')
        ->and($papers->last()->title)->toBe('History Paper 1')
        ->and($papers->first()->durationLabel())->toBe('2h');
});

test('the timetable downloads as a pdf', function () {
    Storage::fake('public');

    [$director, $institution] = makeTimetableSchool();

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 A']);
    makeSitting($institution, $class, 'Mathematics');

    $response = $this->actingAs($director)->get(route('examinations.timetable.pdf', [
        'term' => 'Second Term',
        'academic_year' => 2026,
        'exam_type' => 'end_term',
    ]));

    $response->assertOk();

    $filename = 'exam-timetable-end-term-second-term-2026.pdf';
    $path = "exam-timetables/{$institution->id}/{$filename}";

    // The download is served from the stored file, so that's what to check.
    expect($response->headers->get('content-disposition'))->toContain($filename)
        ->and(Storage::disk('public')->get($path))->toStartWith('%PDF');
});

test('the pdf names the class it was narrowed to', function () {
    [$director, $institution] = makeTimetableSchool();

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 2 B']);
    makeSitting($institution, $class, 'Kiswahili', ['exam_type' => 'mid_term']);

    $response = $this->actingAs($director)
        ->get(route('examinations.timetable.pdf', ['exam_type' => 'mid_term', 'class_id' => $class->id]))
        ->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain('exam-timetable-mid-term-form-2-b.pdf');
});

test('a teacher may print the timetable but an outsider may not', function () {
    [, $institution] = makeTimetableSchool();

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 A']);
    makeSitting($institution, $class, 'Mathematics');

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    $this->actingAs($teacher->refresh())->get(route('examinations.timetable'))->assertOk();

    // Someone with no role at all has no examination permission.
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('examinations.timetable'))->assertForbidden();
});

test('downloading stores the pdf and replaces the copy from last time', function () {
    Storage::fake('public');

    [$director, $institution] = makeTimetableSchool();

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 A']);
    makeSitting($institution, $class, 'Mathematics', ['exam_date' => '2026-08-10']);

    $path = "exam-timetables/{$institution->id}/exam-timetable-end-term.pdf";

    $this->actingAs($director)
        ->get(route('examinations.timetable.pdf', ['exam_type' => 'end_term']))
        ->assertOk();

    Storage::disk('public')->assertExists($path);
    $first = Storage::disk('public')->get($path);

    // Reschedule the paper, then download again: the stored copy must be the
    // new one, not the stale file from before.
    Examination::query()->update(['exam_date' => '2026-09-01']);

    $this->actingAs($director)
        ->get(route('examinations.timetable.pdf', ['exam_type' => 'end_term']))
        ->assertOk();

    Storage::disk('public')->assertExists($path);

    expect(Storage::disk('public')->files("exam-timetables/{$institution->id}"))->toHaveCount(1)
        ->and(Storage::disk('public')->get($path))->not->toBe($first);
});

test('each class keeps its own stored timetable', function () {
    Storage::fake('public');

    [$director, $institution] = makeTimetableSchool();

    $formOne = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 A']);
    $formTwo = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 2 B']);

    makeSitting($institution, $formOne, 'Mathematics');
    makeSitting($institution, $formTwo, 'Kiswahili');

    foreach ([$formOne, $formTwo] as $class) {
        $this->actingAs($director)
            ->get(route('examinations.timetable.pdf', ['class_id' => $class->id]))
            ->assertOk();
    }

    // Replacing one class's timetable never removes another's.
    expect(Storage::disk('public')->files("exam-timetables/{$institution->id}"))->toHaveCount(2);
});

test('the exam list filters by sitting and hands that to the download', function () {
    [$director, $institution] = makeTimetableSchool();

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 A']);

    makeSitting($institution, $class, 'Biology', ['exam_type' => 'mid_term']);
    makeSitting($institution, $class, 'Physics', ['exam_type' => 'end_term']);

    Livewire::actingAs($director)
        ->test('examinations::index')
        ->assertSee('Biology Paper 1')
        ->assertSee('Physics Paper 1')
        ->set('examType', 'mid_term')
        ->assertSee('Biology Paper 1')
        ->assertDontSee('Physics Paper 1')
        // The download button carries the same filter through.
        ->assertSee(route('examinations.timetable.pdf', ['exam_type' => 'mid_term']), escape: false);
});
