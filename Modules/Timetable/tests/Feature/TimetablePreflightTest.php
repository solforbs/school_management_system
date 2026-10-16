<?php

namespace Modules\Timetable\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Models\SubjectAllocation;
use Modules\Timetable\Models\TimetableDraft;
use Modules\Timetable\Models\TimetableStructure;
use Modules\Timetable\Services\TimetablePreflightService;
use Modules\Timetable\Support\PreflightReport;
use Tests\TestCase;

class TimetablePreflightTest extends TestCase
{
    use RefreshDatabase;

    protected Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = $this->makeInstitution();
    }

    // --- helpers ---------------------------------------------------------

    protected function makeInstitution(): Institution
    {
        $owner = User::create([
            'name' => 'Owner '.Str::random(5),
            'email' => Str::random(10).'@example.com',
            'password' => bcrypt('password'),
        ]);

        return Institution::create([
            'name' => 'School '.Str::random(6),
            'code' => strtoupper(Str::random(6)),
            'user_id' => $owner->id,
        ]);
    }

    protected function makeTeacher(string $name = 'Teacher'): User
    {
        return User::create([
            'name' => $name.' '.Str::random(4),
            'email' => Str::random(10).'@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    protected function makeClass(string $name = 'Grade 7', ?Institution $institution = null, array $extra = []): SchoolClass
    {
        return SchoolClass::create(array_merge([
            'institution_id' => ($institution ?? $this->institution)->id,
            'name' => $name.' '.Str::random(3),
            'level' => $name,
        ], $extra));
    }

    protected function makeSubject(string $name = 'Mathematics', ?Institution $institution = null, bool $active = true): Subject
    {
        return Subject::create([
            'institution_id' => ($institution ?? $this->institution)->id,
            'name' => $name.' '.Str::random(3),
            'code' => strtoupper(Str::random(5)),
            'is_compulsory' => true,
            'is_active' => $active,
        ]);
    }

    protected function assign(SchoolClass $class, Subject $subject, User $teacher): SubjectTeacher
    {
        return SubjectTeacher::create([
            'institution_id' => $class->institution_id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    protected function allocate(SchoolClass $class, Subject $subject, int $periods, array $extra = []): SubjectAllocation
    {
        return SubjectAllocation::create(array_merge([
            'institution_id' => $class->institution_id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'periods_per_week' => $periods,
            'max_per_day' => 1,
            'allow_double_periods' => false,
            'cognitive_weight' => 'medium',
        ], $extra));
    }

    protected function structure(int $periods = 4, ?array $days = null, array $breaks = []): TimetableStructure
    {
        return new TimetableStructure([
            'days_of_week' => $days ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'periods_configuration' => TimetableStructure::buildCustomConfiguration([
                'daily_periods' => $periods,
                'breaks' => $breaks,
            ]),
        ]);
    }

    protected function check(?TimetableStructure $structure = null, string $scope = 'whole_school', ?int $scopeId = null, ?Institution $institution = null): PreflightReport
    {
        return app(TimetablePreflightService::class)->check(
            ($institution ?? $this->institution)->id,
            $structure ?? $this->structure(),
            $scope,
            $scopeId,
        );
    }

    /** A class with one fully configured subject, so only the thing under test is wrong. */
    protected function validSchool(): array
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        $teacher = $this->makeTeacher();
        $this->assign($class, $subject, $teacher);
        $this->allocate($class, $subject, 4);

        return [$class, $subject, $teacher];
    }

    // --- blockers: nothing set up ---------------------------------------

    public function test_school_with_no_classes_is_blocked(): void
    {
        $report = $this->check();

        $this->assertTrue($report->has('NO_CLASSES'));
        $this->assertFalse($report->canGenerate());
        $this->assertNotEmpty($report->withCode('NO_CLASSES')[0]->howToFix);
    }

    public function test_unknown_class_scope_is_blocked(): void
    {
        $this->validSchool();

        $report = $this->check(null, 'class', 999999);

        $this->assertTrue($report->has('NO_CLASSES'));
    }

    public function test_class_from_another_school_is_never_in_scope(): void
    {
        $other = $this->makeInstitution();
        $foreign = $this->makeClass('Grade 8', $other);
        $this->validSchool();

        $report = $this->check(null, 'class', $foreign->id);

        $this->assertTrue($report->has('NO_CLASSES'));
    }

    public function test_school_with_no_subjects_is_blocked(): void
    {
        $this->makeClass();

        $this->assertTrue($this->check()->has('NO_SUBJECTS'));
    }

    public function test_inactive_subjects_do_not_count(): void
    {
        $this->makeClass();
        $this->makeSubject('Mathematics', null, false);

        $this->assertTrue($this->check()->has('NO_SUBJECTS'));
    }

    public function test_subjects_from_another_school_do_not_count(): void
    {
        $this->makeClass();
        $this->makeSubject('Mathematics', $this->makeInstitution());

        $this->assertTrue($this->check()->has('NO_SUBJECTS'));
    }

    public function test_school_with_no_teachers_is_blocked(): void
    {
        $this->makeClass();
        $this->makeSubject();

        $this->assertTrue($this->check()->has('NO_TEACHERS'));
    }

    public function test_teacher_assignments_in_another_school_do_not_count(): void
    {
        $this->makeClass();
        $this->makeSubject();
        $other = $this->makeInstitution();
        $this->assign($this->makeClass('Grade 8', $other), $this->makeSubject('Science', $other), $this->makeTeacher());

        $this->assertTrue($this->check()->has('NO_TEACHERS'));
    }

    public function test_empty_school_reports_every_missing_piece_at_once(): void
    {
        $this->makeClass();

        $codes = $this->check()->codes();

        $this->assertContains('NO_SUBJECTS', $codes);
        $this->assertContains('NO_TEACHERS', $codes);
    }

    public function test_class_with_no_linked_subjects_is_blocked_and_unlinked_subjects_are_listed(): void
    {
        $this->validSchool();
        $lonely = $this->makeClass('Grade 1');

        $report = $this->check();

        $this->assertTrue($report->has('CLASS_NO_SUBJECTS'));
        $this->assertSame($lonely->id, $report->withCode('CLASS_NO_SUBJECTS')[0]->context['class_id']);
        $this->assertTrue($report->has('SUBJECTS_SKIPPED'));
    }

    // --- bell schedule ----------------------------------------------------

    public function test_structure_with_no_days_is_blocked(): void
    {
        $this->validSchool();

        $this->assertTrue($this->check($this->structure(4, []))->has('NO_STRUCTURE_PERIODS'));
    }

    public function test_structure_with_no_lesson_periods_is_blocked(): void
    {
        $this->validSchool();

        $structure = new TimetableStructure([
            'days_of_week' => ['Monday'],
            'periods_configuration' => [],
        ]);

        $this->assertTrue($this->check($structure)->has('NO_STRUCTURE_PERIODS'));
    }

    public function test_structure_with_reversed_times_is_blocked(): void
    {
        $this->validSchool();

        $structure = new TimetableStructure([
            'days_of_week' => ['Monday'],
            'periods_configuration' => [
                ['period_number' => 1, 'start_time' => '09:00', 'end_time' => '08:00', 'is_break' => false],
            ],
        ]);

        $this->assertTrue($this->check($structure)->has('INVALID_STRUCTURE'));
    }

    public function test_structure_with_overlapping_periods_is_blocked(): void
    {
        $this->validSchool();

        $structure = new TimetableStructure([
            'days_of_week' => ['Monday'],
            'periods_configuration' => [
                ['period_number' => 1, 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => false],
                ['period_number' => 2, 'start_time' => '08:30', 'end_time' => '09:10', 'is_break' => false],
            ],
        ]);

        $this->assertTrue($this->check($structure)->has('INVALID_STRUCTURE'));
    }

    public function test_structure_with_malformed_times_is_blocked(): void
    {
        $this->validSchool();

        $structure = new TimetableStructure([
            'days_of_week' => ['Monday'],
            'periods_configuration' => [
                ['period_number' => 1, 'start_time' => 'eight', 'end_time' => '08:40', 'is_break' => false],
            ],
        ]);

        $this->assertTrue($this->check($structure)->has('INVALID_STRUCTURE'));
    }

    public function test_structure_with_duplicate_period_numbers_is_blocked(): void
    {
        $this->validSchool();

        $structure = new TimetableStructure([
            'days_of_week' => ['Monday'],
            'periods_configuration' => [
                ['period_number' => 1, 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => false],
                ['period_number' => 1, 'start_time' => '08:40', 'end_time' => '09:20', 'is_break' => false],
            ],
        ]);

        $this->assertTrue($this->check($structure)->has('INVALID_STRUCTURE'));
    }

    public function test_wizard_built_structure_with_breaks_and_assembly_is_valid(): void
    {
        $this->validSchool();

        $structure = new TimetableStructure([
            'days_of_week' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'periods_configuration' => TimetableStructure::buildCustomConfiguration([
                'daily_periods' => 8,
                'has_assembly' => true,
                'has_games' => true,
                'breaks' => [
                    ['name' => 'Tea', 'after_period' => 2, 'duration' => 20],
                    ['name' => 'Lunch', 'after_period' => 5, 'duration' => 60],
                ],
            ]),
        ]);

        $report = $this->check($structure);

        $this->assertFalse($report->has('INVALID_STRUCTURE'));
        $this->assertFalse($report->has('NO_STRUCTURE_PERIODS'));
    }

    // --- allocations ------------------------------------------------------

    public function test_zero_lessons_per_week_is_blocked(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        $this->assign($class, $subject, $this->makeTeacher());
        $this->allocate($class, $subject, 0);

        $this->assertTrue($this->check()->has('INVALID_ALLOCATION'));
    }

    public function test_daily_limit_that_cannot_fit_the_weekly_quota_is_blocked(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        $this->assign($class, $subject, $this->makeTeacher());
        $this->allocate($class, $subject, 6, ['max_per_day' => 1]); // 1 × 5 days < 6

        $issue = $this->check()->withCode('INVALID_ALLOCATION')[0];

        $this->assertSame($subject->id, $issue->context['subject_id']);
    }

    public function test_daily_limit_exactly_fitting_the_quota_is_allowed(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        $this->assign($class, $subject, $this->makeTeacher());
        $this->allocate($class, $subject, 5, ['max_per_day' => 1]); // 1 × 5 days = 5

        $this->assertFalse($this->check()->has('INVALID_ALLOCATION'));
    }

    // --- capacity ---------------------------------------------------------

    public function test_class_exactly_filling_the_week_passes_capacity(): void
    {
        $class = $this->makeClass();
        for ($i = 0; $i < 5; $i++) {
            $subject = $this->makeSubject('Subject');
            $this->assign($class, $subject, $this->makeTeacher());
            $this->allocate($class, $subject, 4);
        }

        $this->assertFalse($this->check()->has('CLASS_OVER_CAPACITY')); // 20 of 20
    }

    public function test_class_one_lesson_over_capacity_is_blocked_with_exact_numbers(): void
    {
        $class = $this->makeClass();
        for ($i = 0; $i < 4; $i++) {
            $subject = $this->makeSubject('Subject');
            $this->assign($class, $subject, $this->makeTeacher());
            $this->allocate($class, $subject, 4);
        }
        $extra = $this->makeSubject('Extra');
        $this->assign($class, $extra, $this->makeTeacher());
        $this->allocate($class, $extra, 5);

        $issue = $this->check()->withCode('CLASS_OVER_CAPACITY')[0];

        $this->assertSame(21, $issue->context['required']);
        $this->assertSame(20, $issue->context['available']);
        $this->assertStringContainsString('21', $issue->message);
        $this->assertStringContainsString('20', $issue->message);
    }

    public function test_capacity_follows_the_structure_not_a_fixed_number(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        $this->assign($class, $subject, $this->makeTeacher());
        $this->allocate($class, $subject, 5, ['max_per_day' => 5]);

        // 5 days × 1 period = 5 slots: exactly enough. 4 days × 1 = 4: not enough.
        $this->assertFalse($this->check($this->structure(1))->has('CLASS_OVER_CAPACITY'));
        $this->assertTrue($this->check($this->structure(1, ['Monday', 'Tuesday', 'Wednesday', 'Thursday']))->has('CLASS_OVER_CAPACITY'));
    }

    // --- teachers ---------------------------------------------------------

    public function test_subject_with_no_teacher_warns_but_does_not_block(): void
    {
        [$class] = $this->validSchool();
        $orphan = $this->makeSubject('Music');
        $this->allocate($class, $orphan, 2);

        $report = $this->check();

        $this->assertTrue($report->has('LESSON_NO_TEACHER'));
        $this->assertTrue($report->canGenerate());
    }

    public function test_class_teacher_is_used_when_no_subject_teacher_and_warns(): void
    {
        [$class] = $this->validSchool();
        $classTeacher = $this->makeTeacher('Class Teacher');
        $class->update(['class_teacher_id' => $classTeacher->id]);
        $orphan = $this->makeSubject('Music');
        $this->allocate($class, $orphan, 2);

        $report = $this->check();

        $this->assertTrue($report->has('FALLBACK_TO_CLASS_TEACHER'));
        $this->assertFalse($report->has('LESSON_NO_TEACHER'));
    }

    public function test_several_teachers_on_one_subject_warns(): void
    {
        [$class, $subject] = $this->validSchool();
        $this->assign($class, $subject, $this->makeTeacher());

        $this->assertTrue($this->check()->has('MULTIPLE_TEACHERS'));
    }

    public function test_one_teacher_over_the_weekly_limit_is_blocked(): void
    {
        $subject = $this->makeSubject();
        $teacher = $this->makeTeacher('Mr Overloaded');
        for ($i = 0; $i < 6; $i++) { // 6 classes × 4 = 24 > 20
            $class = $this->makeClass();
            $this->assign($class, $subject, $teacher);
            $this->allocate($class, $subject, 4);
        }

        $report = $this->check();
        $issue = $report->withCode('TEACHER_OVERBOOKED')[0];

        $this->assertSame($teacher->id, $issue->context['teacher_id']);
        $this->assertSame(24, $issue->context['required']);
        $this->assertSame(20, $issue->context['available']);
        $this->assertStringContainsString('Mr Overloaded', $issue->message);
        $this->assertFalse($report->canGenerate());
    }

    public function test_teacher_exactly_at_capacity_warns_but_is_not_blocked(): void
    {
        $subject = $this->makeSubject();
        $teacher = $this->makeTeacher();
        for ($i = 0; $i < 5; $i++) { // 5 × 4 = 20 of 20
            $class = $this->makeClass();
            $this->assign($class, $subject, $teacher);
            $this->allocate($class, $subject, 4);
        }

        $report = $this->check();

        $this->assertTrue($report->has('TEACHER_HIGH_LOAD'));
        $this->assertFalse($report->has('TEACHER_OVERBOOKED'));
    }

    public function test_lightly_loaded_teacher_raises_no_load_issue(): void
    {
        $this->validSchool();

        $report = $this->check();

        $this->assertFalse($report->has('TEACHER_HIGH_LOAD'));
        $this->assertFalse($report->has('TEACHER_OVERBOOKED'));
    }

    public function test_daily_cap_in_config_reduces_teacher_capacity(): void
    {
        config(['timetable.max_daily_lessons' => 2]); // 2 × 5 days = 10
        $subject = $this->makeSubject();
        $teacher = $this->makeTeacher();
        for ($i = 0; $i < 3; $i++) { // 12 > 10
            $class = $this->makeClass();
            $this->assign($class, $subject, $teacher);
            $this->allocate($class, $subject, 4);
        }

        $issue = $this->check()->withCode('TEACHER_OVERBOOKED')[0];

        $this->assertSame(10, $issue->context['available']);
    }

    public function test_subject_needing_more_lessons_than_all_its_teachers_can_give_is_blocked(): void
    {
        $subject = $this->makeSubject('Physics');
        $a = $this->makeTeacher();
        $b = $this->makeTeacher();
        for ($i = 0; $i < 11; $i++) { // 44 lessons, two teachers × 20 = 40
            $class = $this->makeClass();
            $this->assign($class, $subject, $a);
            $this->assign($class, $subject, $b);
            $this->allocate($class, $subject, 4);
        }

        $report = $this->check();

        $this->assertTrue($report->has('SUBJECT_UNSTAFFABLE'));
        $this->assertSame(44, $report->withCode('SUBJECT_UNSTAFFABLE')[0]->context['required']);
    }

    // --- quota and doubles --------------------------------------------------

    public function test_subject_without_allocation_warns_that_the_quota_was_estimated(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject('Mathematics');
        $this->assign($class, $subject, $this->makeTeacher());

        $report = $this->check();

        $this->assertTrue($report->has('FALLBACK_QUOTA'));
        $this->assertTrue($report->canGenerate());
    }

    public function test_explicit_allocation_does_not_warn_about_an_estimate(): void
    {
        $this->validSchool();

        $this->assertFalse($this->check()->has('FALLBACK_QUOTA'));
    }

    public function test_doubles_wanted_but_every_period_followed_by_a_break_warns(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        $this->assign($class, $subject, $this->makeTeacher());
        $this->allocate($class, $subject, 4, ['allow_double_periods' => true, 'max_per_day' => 2]);

        $structure = $this->structure(4, null, [
            ['name' => 'B1', 'after_period' => 1, 'duration' => 10],
            ['name' => 'B2', 'after_period' => 2, 'duration' => 10],
            ['name' => 'B3', 'after_period' => 3, 'duration' => 10],
        ]);

        $this->assertTrue($this->check($structure)->has('DOUBLE_UNSATISFIABLE'));
    }

    public function test_doubles_with_a_valid_adjacent_pair_do_not_warn(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        $this->assign($class, $subject, $this->makeTeacher());
        $this->allocate($class, $subject, 4, ['allow_double_periods' => true, 'max_per_day' => 2]);

        $structure = $this->structure(4, null, [['name' => 'Tea', 'after_period' => 2, 'duration' => 20]]);

        $this->assertFalse($this->check($structure)->has('DOUBLE_UNSATISFIABLE'));
    }

    public function test_doubles_blocked_by_a_one_per_day_limit_warn(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject();
        $this->assign($class, $subject, $this->makeTeacher());
        $this->allocate($class, $subject, 4, ['allow_double_periods' => true, 'max_per_day' => 1]);

        $this->assertTrue($this->check()->has('DOUBLE_BLOCKED_BY_DAILY_LIMIT'));
    }

    public function test_class_allocation_beats_curriculum_allocation(): void
    {
        $curriculum = Curriculum::create([
            'institution_id' => $this->institution->id,
            'name' => 'CBC '.Str::random(3),
            'system' => 'cbc',
            'status' => 'active',
        ]);
        $class = $this->makeClass('Grade 7', null, ['curriculum_id' => $curriculum->id]);
        $subject = $this->makeSubject();
        $this->assign($class, $subject, $this->makeTeacher());

        SubjectAllocation::create([
            'institution_id' => $this->institution->id,
            'curriculum_id' => $curriculum->id,
            'subject_id' => $subject->id,
            'periods_per_week' => 30, // curriculum-wide: would overflow the week
            'max_per_day' => 7,
        ]);
        $this->allocate($class, $subject, 4);

        $this->assertFalse($this->check()->has('CLASS_OVER_CAPACITY'));
    }

    public function test_curriculum_wide_allocation_applies_to_classes_on_that_curriculum_only(): void
    {
        $curriculum = Curriculum::create([
            'institution_id' => $this->institution->id,
            'name' => 'CBC '.Str::random(3),
            'system' => 'cbc',
            'status' => 'active',
        ]);
        $onCurriculum = $this->makeClass('Grade 7', null, ['curriculum_id' => $curriculum->id]);
        $other = $this->makeClass('Grade 8');
        $subject = $this->makeSubject();
        SubjectAllocation::create([
            'institution_id' => $this->institution->id,
            'curriculum_id' => $curriculum->id,
            'subject_id' => $subject->id,
            'periods_per_week' => 4,
            'max_per_day' => 1,
        ]);
        $this->assign($onCurriculum, $subject, $this->makeTeacher());

        $report = $this->check();

        $noSubjects = array_map(fn ($i) => $i->context['class_id'], $report->withCode('CLASS_NO_SUBJECTS'));
        $this->assertContains($other->id, $noSubjects);
        $this->assertNotContains($onCurriculum->id, $noSubjects);
    }

    // --- happy path and scope -----------------------------------------------

    public function test_fully_configured_school_has_no_blockers(): void
    {
        $this->validSchool();

        $report = $this->check();

        $this->assertTrue($report->canGenerate());
        $this->assertSame([], $report->blockers());
    }

    public function test_class_scope_only_checks_that_class(): void
    {
        [$good] = $this->validSchool();
        $this->makeClass('Grade 1'); // would be CLASS_NO_SUBJECTS in a whole-school run

        $this->assertFalse($this->check(null, 'class', $good->id)->has('CLASS_NO_SUBJECTS'));
        $this->assertTrue($this->check()->has('CLASS_NO_SUBJECTS'));
    }

    public function test_stream_scope_covers_every_class_at_the_same_level(): void
    {
        $a = $this->makeClass('Grade 7');
        $b = $this->makeClass('Grade 7');
        $this->makeSubject();

        $report = $this->check(null, 'stream', $a->id);

        $ids = array_map(fn ($i) => $i->context['class_id'], $report->withCode('CLASS_NO_SUBJECTS'));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids);
    }

    public function test_every_issue_explains_how_to_fix_it(): void
    {
        $this->makeClass();

        foreach ($this->check()->issues() as $issue) {
            $this->assertNotEmpty($issue->message, $issue->code);
            $this->assertNotEmpty($issue->howToFix, $issue->code);
        }
    }

    public function test_report_serialises_for_the_ui(): void
    {
        $this->makeClass();

        $array = $this->check()->toArray();

        $this->assertFalse($array['can_generate']);
        $this->assertNotEmpty($array['blockers']);
        $this->assertArrayHasKey('how_to_fix', $array['blockers'][0]);
    }

    public function test_preflight_never_writes_to_the_database(): void
    {
        $this->validSchool();
        $before = [
            TimetableStructure::count(),
            SubjectAllocation::count(),
            TimetableDraft::count(),
        ];

        $this->check();

        $this->assertSame($before, [
            TimetableStructure::count(),
            SubjectAllocation::count(),
            TimetableDraft::count(),
        ]);
    }
}
