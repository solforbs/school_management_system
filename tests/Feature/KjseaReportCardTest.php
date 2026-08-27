<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\GradingBandService;
use Modules\ReportCard\Services\ReportCardPdfService;
use Modules\ReportCard\Services\ReportSheetBuilder;
use Modules\ReportCard\Support\GradingScaleDefaults;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;

function kjseaSchool(): Institution
{
    $owner = User::factory()->create();

    return Institution::create([
        'user_id' => $owner->id,
        'name' => 'Elimikasasa Demo School',
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'email' => 'support@elimikasasa.co.ke',
        'phone' => '+254 702 683 707',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);
}

function kjseaCurriculum(Institution $institution): Curriculum
{
    $curriculum = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    foreach (GradingScaleDefaults::for($curriculum) as $band) {
        GradingBand::create($band + [
            'institution_id' => $institution->id,
            'curriculum_id' => $curriculum->id,
        ]);
    }

    return $curriculum;
}

function kjseaStudent(Institution $institution, SchoolClass $class): User
{
    $parent = User::factory()->create();
    DB::table('parent_details')->insert([
        'id' => $parent->id,
        'parent_id' => $parent->id,
        'parent_phone' => '0712345678',
    ]);

    $student = User::factory()->create(['name' => 'John Doe']);

    StudentDetails::create([
        'student_id' => $student->id,
        'admission_number' => 'ADM-'.uniqid(),
        'student_number' => 'UPI-'.uniqid(),
        'gender' => 'male',
        'parent_id' => $parent->id,
        'institution_id' => $institution->id,
        'class_id' => $class->id,
    ]);

    return $student;
}

function kjseaSubject(Institution $institution, SchoolClass $class, string $name, string $code): Subject
{
    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => $name,
        'code' => $code,
        'is_active' => true,
    ]);

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => User::factory()->create(['name' => 'Brian Njoroge'])->id,
    ]);

    return $subject;
}

function kjseaScore(Institution $institution, SchoolClass $class, Subject $subject, User $student, float $marks, int $outOf, string $term = 'Term 1'): void
{
    $examination = Examination::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'title' => $subject->name.' paper',
        'term' => $term,
        'exam_type' => 'end_term',
        'academic_year' => 2026,
        'exam_date' => '2026-03-10',
        'total_marks' => $outOf,
    ]);

    Result::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'student_id' => $student->id,
        'examination_id' => $examination->id,
        'marks_obtained' => $marks,
    ]);
}

test('the kjsea scale grades a percentage to its eight-level achievement band', function (float $percentage, string $grade, int $points) {
    $institution = kjseaSchool();
    $curriculum = kjseaCurriculum($institution);

    $band = GradingBandService::resolveBand($institution, $percentage, $curriculum->id);

    expect($band?->grade)->toBe($grade)
        ->and($band?->points)->toBe($points);
})->with([
    [100.0, 'EE1', 8],
    [90.0, 'EE1', 8],
    [89.4, 'EE2', 7],
    [75.0, 'EE2', 7],
    [74.5, 'ME1', 6],
    [58.0, 'ME1', 6],
    [57.0, 'ME2', 5],
    [41.0, 'ME2', 5],
    [40.5, 'AE1', 4],
    [31.0, 'AE1', 4],
    [30.0, 'AE2', 3],
    [21.0, 'AE2', 3],
    [20.0, 'BE1', 2],
    [11.0, 'BE1', 2],
    [10.0, 'BE2', 1],
    // The published scale starts at 1%, but an unattempted paper still has
    // to grade to something rather than come back blank.
    [0.0, 'BE2', 1],
]);

test('the four bands are read off the eight levels, so cbc carries both at once', function () {
    $institution = kjseaSchool();
    $curriculum = kjseaCurriculum($institution);

    // One mark inside each of the eight levels.
    $levels = collect([95, 80, 65, 50, 35, 25, 15, 5])
        ->map(fn ($percentage) => GradingBandService::resolve($institution, $percentage, $curriculum->id));

    expect($levels->all())->toBe(['EE1', 'EE2', 'ME1', 'ME2', 'AE1', 'AE2', 'BE1', 'BE2'])
        // Two levels to a band, all the way down.
        ->and($levels->map(fn ($grade) => GradingScaleDefaults::bandFor($grade))->all())
        ->toBe(['EE', 'EE', 'ME', 'ME', 'AE', 'AE', 'BE', 'BE'])
        ->and($curriculum->gradingLabel())->toBe('CBC (EE / ME / AE / BE, levels EE1 to BE2)');
});

test('8-4-4 letters are not read as expectation bands', function () {
    // "A" and "E" are letters on a different scale entirely; nothing should
    // colour or roll them up as though they were EE or BE.
    expect(GradingScaleDefaults::bandFor('A'))->toBeNull()
        ->and(GradingScaleDefaults::bandFor('B+'))->toBeNull()
        ->and(GradingScaleDefaults::bandFor('E'))->toBeNull()
        ->and(GradingScaleDefaults::bandFor(null))->toBeNull()
        ->and(GradingScaleDefaults::bandFor('ME2'))->toBe('ME');
});

test('the report sheet totals a subject across its papers and shows an unassessed subject as unassessed', function () {
    $institution = kjseaSchool();
    $curriculum = kjseaCurriculum($institution);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 9 Champions',
        'curriculum_id' => $curriculum->id,
    ]);

    $student = kjseaStudent($institution, $class);

    $maths = kjseaSubject($institution, $class, 'Mathematics', 'MAT');
    $english = kjseaSubject($institution, $class, 'English', 'ENG');
    kjseaSubject($institution, $class, 'Creative Arts', 'CRE');

    // Two sittings in the term: 30/30 and 60/70 is 90 out of 100.
    kjseaScore($institution, $class, $maths, $student, 30, 30);
    kjseaScore($institution, $class, $maths, $student, 60, 70);
    kjseaScore($institution, $class, $english, $student, 30, 50);

    $reportCard = ReportCard::create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'term' => 'Term 1',
        'academic_year' => 2026,
        'status' => 'ready',
        'completed_at' => now(),
    ]);

    $builder = new ReportSheetBuilder;
    $rows = $builder->rows($reportCard, $institution);
    $summary = $builder->summary($rows, $institution, $curriculum->id);

    $byName = $rows->keyBy(fn ($row) => $row->name);

    expect($rows)->toHaveCount(3)
        ->and($byName['Mathematics']->marks)->toBe(90.0)
        ->and($byName['Mathematics']->outOf)->toBe(100.0)
        ->and($byName['Mathematics']->percentage)->toBe(90.0)
        ->and($byName['Mathematics']->grade())->toBe('EE1')
        ->and($byName['Mathematics']->teacherInitials)->toBe('B. N')
        ->and($byName['English']->percentage)->toBe(60.0)
        ->and($byName['English']->grade())->toBe('ME1')
        ->and($byName['Creative Arts']->isAssessed())->toBeFalse()
        ->and($byName['Creative Arts']->grade())->toBeNull()
        // Only the two subjects actually sat count toward the totals.
        ->and($summary['subjects_assessed'])->toBe(2)
        ->and($summary['subjects_total'])->toBe(3)
        ->and($summary['total_marks'])->toBe(120.0)
        ->and($summary['total_out_of'])->toBe(150.0)
        ->and($summary['mean_percentage'])->toBe(75.0)
        // Level 8 for the 90% and level 6 for the 60%.
        ->and($summary['mean_points'])->toBe(7.0)
        ->and($summary['band']?->grade)->toBe('EE2')
        ->and($builder->pointsCeiling(GradingBandService::scaleFor($institution, $curriculum->id), $curriculum))->toBe(8);
});

test('a kjsea report card renders to a pdf carrying the learner, the levels and the scale key', function () {
    Storage::fake('public');

    $institution = kjseaSchool();
    $curriculum = kjseaCurriculum($institution);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 9 Champions',
        'curriculum_id' => $curriculum->id,
    ]);

    $student = kjseaStudent($institution, $class);
    $maths = kjseaSubject($institution, $class, 'Mathematics', 'MAT');
    kjseaScore($institution, $class, $maths, $student, 45, 50);

    $reportCard = ReportCard::create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'term' => 'Term 1',
        'academic_year' => 2026,
        'status' => 'ready',
        'completed_at' => now(),
    ]);

    $path = (new ReportCardPdfService)->generate($reportCard);

    Storage::disk('public')->assertExists($path);

    expect(substr(Storage::disk('public')->get($path), 0, 4))->toBe('%PDF')
        ->and($reportCard->fresh()->mean_grade)->toBe('EE1')
        ->and((float) $reportCard->fresh()->mean_percentage)->toBe(90.0);
});

test('the standard scales are one flat list of two, and each loads its own bands', function () {
    expect(array_keys(GradingScaleDefaults::options()))->toBe([
        GradingScaleDefaults::SCALE_844,
        GradingScaleDefaults::SCALE_CBC,
    ]);

    $grades = fn (string $key) => collect(GradingScaleDefaults::forKey($key))->pluck('grade')->all();

    expect($grades(GradingScaleDefaults::SCALE_844))
        ->toBe(['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-', 'E'])
        // CBC is one scale of eight levels; the four bands come off them.
        ->and($grades(GradingScaleDefaults::SCALE_CBC))
        ->toBe(['EE1', 'EE2', 'ME1', 'ME2', 'AE1', 'AE2', 'BE1', 'BE2']);
});

test('the standard scale is preselected from whatever the curriculum grades on', function () {
    $institution = kjseaSchool();

    $cbc = kjseaCurriculum($institution);
    $legacy = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => '8-4-4',
        'system' => '844',
        'status' => 'active',
    ]);

    expect(GradingScaleDefaults::keyFor($cbc))->toBe(GradingScaleDefaults::SCALE_CBC)
        ->and(GradingScaleDefaults::keyFor($legacy))->toBe(GradingScaleDefaults::SCALE_844)
        // No curriculum at all is the school-wide scale, which defaults to 8-4-4.
        ->and(GradingScaleDefaults::keyFor(null))->toBe(GradingScaleDefaults::SCALE_844);
});

test('a curriculum named for one system but grading on the other is flagged', function () {
    $institution = kjseaSchool();

    $make = fn (string $name, string $system) => Curriculum::create([
        'institution_id' => $institution->id,
        'name' => $name,
        'system' => $system,
        'status' => 'active',
    ]);

    // The dotted spelling the import's LIKE '%cbc%' never matched.
    expect($make('C.B.C', '844')->looksMisconfigured())->toBeTrue()
        ->and($make('Competency Based', '844')->looksMisconfigured())->toBeTrue()
        ->and($make('8-4-4', 'cbc')->looksMisconfigured())->toBeTrue()
        // Correctly configured ones stay quiet.
        ->and($make('C.B.C ', 'cbc')->looksMisconfigured())->toBeFalse()
        ->and($make('8-4-4 ', '844')->looksMisconfigured())->toBeFalse()
        ->and($make('Cambridge', '844')->looksMisconfigured())->toBeFalse();
});

test('the settings screen labels a curriculum by the grades it actually awards', function () {
    $institution = kjseaSchool();

    $cbc = kjseaCurriculum($institution);
    $legacy = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'C.B.C',
        'system' => '844',
        'status' => 'active',
    ]);

    expect($cbc->gradingLabel())->toBe('CBC (EE / ME / AE / BE, levels EE1 to BE2)')
        // The mismatch reads as an error rather than a formatting quirk.
        ->and($legacy->gradingLabel())->toBe('8-4-4 (A to E)');
});
