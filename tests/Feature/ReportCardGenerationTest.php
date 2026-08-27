<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardGenerationService;
use Modules\ReportCard\Services\ReportCardPdfService;
use Modules\ReportCard\Support\GradingScaleDefaults;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('public');
});

/**
 * @return array{0: User, 1: Institution, 2: SchoolClass}
 */
function rcgSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Report School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 North',
    ]);

    foreach (GradingScaleDefaults::cbc() as $band) {
        GradingBand::create($band + ['institution_id' => $institution->id, 'curriculum_id' => null]);
    }

    return [$director->refresh(), $institution, $class];
}

function rcgLearner(Institution $institution, SchoolClass $class): User
{
    $parent = User::factory()->create();
    DB::table('parent_details')->insert(['id' => $parent->id, 'parent_id' => $parent->id]);

    $student = User::factory()->create();

    StudentDetails::create([
        'student_id' => $student->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'parent_id' => $parent->id,
        'admission_number' => 'ADM-'.uniqid(),
    ]);

    return $student;
}

function rcgExam(Institution $institution, SchoolClass $class, string $term = 'Second Term', int $year = 2026): Examination
{
    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics '.uniqid(),
        'is_active' => true,
        'is_compulsory' => true,
    ]);

    return Examination::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'title' => $term.' Mathematics',
        'term' => $term,
        'academic_year' => $year,
        'exam_date' => '2026-08-10',
        'total_marks' => 100,
    ]);
}

function rcgMark(Institution $institution, SchoolClass $class, Examination $examination, User $student, float $marks): Result
{
    return Result::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'examination_id' => $examination->id,
        'marks_obtained' => $marks,
    ]);
}

test('the generate button builds a report card and pdf for every marked learner in the class', function () {
    [$director, $institution, $class] = rcgSchool();

    $examination = rcgExam($institution, $class);

    $marked = rcgLearner($institution, $class);
    $alsoMarked = rcgLearner($institution, $class);
    $unmarked = rcgLearner($institution, $class);

    rcgMark($institution, $class, $examination, $marked, 85);
    rcgMark($institution, $class, $examination, $alsoMarked, 55);

    Livewire::actingAs($director)
        ->test('result::index')
        ->assertSee('Generate Report Cards')
        ->set('classId', (string) $class->id)
        ->set('examinationId', (string) $examination->id)
        // The screen says which term it is about to report on before the
        // button is pressed.
        ->assertSee('Second Term')
        ->call('generateReportCards')
        ->assertHasNoErrors();

    expect(ReportCard::count())->toBe(2);

    $reportCard = ReportCard::where('student_id', $marked->id)->firstOrFail();

    expect($reportCard->term)->toBe('Second Term')
        ->and($reportCard->academic_year)->toBe(2026)
        ->and($reportCard->term_number)->toBe(2)
        ->and($reportCard->class_id)->toBe($class->id)
        ->and($reportCard->status)->toBe('ready')
        ->and($reportCard->mean_grade)->toBe('EE2')
        ->and($reportCard->pdf_path)->not->toBeNull()
        // The learner with no marks this term gets no report card at all,
        // rather than a blank one.
        ->and(ReportCard::where('student_id', $unmarked->id)->exists())->toBeFalse();

    Storage::disk('public')->assertExists($reportCard->pdf_path);
});

test('generating again after a mark is corrected refreshes the report card instead of duplicating it', function () {
    [$director, $institution, $class] = rcgSchool();

    $examination = rcgExam($institution, $class);
    $student = rcgLearner($institution, $class);
    $result = rcgMark($institution, $class, $examination, $student, 30);

    $generate = fn () => Livewire::actingAs($director)
        ->test('result::index')
        ->set('classId', (string) $class->id)
        ->set('examinationId', (string) $examination->id)
        ->call('generateReportCards');

    $generate();

    expect(ReportCard::where('student_id', $student->id)->firstOrFail()->mean_grade)->toBe('AE2');

    $result->update(['marks_obtained' => 90]);

    $generate();

    expect(ReportCard::count())->toBe(1)
        ->and(ReportCard::where('student_id', $student->id)->firstOrFail()->mean_grade)->toBe('EE1');
});

test('the term is taken from the class when all its marks sit in one term', function () {
    [$director, $institution, $class] = rcgSchool();

    $examination = rcgExam($institution, $class);
    $student = rcgLearner($institution, $class);
    rcgMark($institution, $class, $examination, $student, 70);

    // No examination chosen - only the class.
    Livewire::actingAs($director)
        ->test('result::index')
        ->set('classId', (string) $class->id)
        ->call('generateReportCards');

    expect(ReportCard::where('student_id', $student->id)->firstOrFail()->term)->toBe('Second Term');
});

test('a class spanning two terms reports on the current one, and the examination filter has no say in it', function () {
    [$director, $institution, $class] = rcgSchool();

    $first = rcgExam($institution, $class, 'First Term');
    $second = rcgExam($institution, $class, 'Second Term');

    $student = rcgLearner($institution, $class);
    rcgMark($institution, $class, $first, $student, 70);
    rcgMark($institution, $class, $second, $student, 40);

    Livewire::actingAs($director)
        ->test('result::index')
        ->set('classId', (string) $class->id)
        // Filtering the table down to a first-term paper must not drag the
        // report back into first term: an examination is one subject, and a
        // report card covers the whole term.
        ->set('examinationId', (string) $first->id)
        ->call('generateReportCards');

    expect(ReportCard::count())->toBe(1);

    $reportCard = ReportCard::firstOrFail();

    expect($reportCard->term)->toBe('Second Term')
        ->and($reportCard->term_number)->toBe(2)
        // Marked out of 100 in second term, so the second-term figure only.
        ->and((float) $reportCard->mean_percentage)->toBe(40.0);
});

test('the report card sets this term against the one before it, and reaches back a year for term 1', function () {
    [, $institution, $class] = rcgSchool();

    $student = rcgLearner($institution, $class);

    // Last year's third term, already reported on.
    $lastYear = rcgExam($institution, $class, 'Third Term', 2025);
    rcgMark($institution, $class, $lastYear, $student, 50);

    $thisYear = rcgExam($institution, $class, 'First Term', 2026);
    rcgMark($institution, $class, $thisYear, $student, 80);

    $generator = app(ReportCardGenerationService::class);
    $generator->forClass($class, 'Third Term', 2025);
    $generator->forClass($class, 'First Term', 2026);

    $current = ReportCard::where('academic_year', 2026)->firstOrFail();
    $previous = ReportCard::where('academic_year', 2025)->firstOrFail();

    expect($previous->term_number)->toBe(3)
        ->and($current->term_number)->toBe(1)
        ->and((float) $previous->mean_percentage)->toBe(50.0)
        ->and((float) $current->mean_percentage)->toBe(80.0);

    // The comparison the PDF prints: term 1 looks back into last year
    // rather than finding nothing before it.
    $history = (function () use ($current) {
        $method = new ReflectionMethod(ReportCardPdfService::class, 'termHistory');

        return $method->invoke(app(ReportCardPdfService::class), $current);
    })();

    expect($history)->toHaveCount(2)
        ->and($history->first()->id)->toBe($previous->id)
        ->and($history->last()->id)->toBe($current->id);
});

test('generating nothing happens without a class selected', function () {
    [$director, $institution, $class] = rcgSchool();

    $examination = rcgExam($institution, $class);
    rcgMark($institution, $class, $examination, rcgLearner($institution, $class), 70);

    Livewire::actingAs($director)
        ->test('result::index')
        ->call('generateReportCards');

    expect(ReportCard::count())->toBe(0);
});

test('a teacher without report card rights cannot generate them', function () {
    [, $institution, $class] = rcgSchool();

    $examination = rcgExam($institution, $class);
    rcgMark($institution, $class, $examination, rcgLearner($institution, $class), 70);

    $outsider = User::factory()->create();
    $outsider->assignRole('Student');
    $outsider->update(['active_institution_id' => $institution->id]);

    Livewire::actingAs($outsider)
        ->test('result::index')
        ->set('classId', (string) $class->id)
        ->set('examinationId', (string) $examination->id)
        ->call('generateReportCards')
        ->assertForbidden();

    expect(ReportCard::count())->toBe(0);
});
