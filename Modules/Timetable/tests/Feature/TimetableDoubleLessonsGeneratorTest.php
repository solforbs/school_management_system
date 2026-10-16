<?php

namespace Modules\Timetable\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timetable\Models\TimetableDraftEntry;
use Modules\Timetable\Models\TimetableStructure;
use Tests\Support\BuildsTimetableWorld;
use Tests\TestCase;

class TimetableDoubleLessonsGeneratorTest extends TestCase
{
    use BuildsTimetableWorld;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = $this->makeInstitution();
    }

    public function test_double_lesson_is_always_two_consecutive_periods_same_teacher_same_day(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher('Math Teacher');
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 4, ['allow_double_periods' => true, 'max_per_day' => 2]);

        $draft = $this->generate(['structure' => $this->structure(6)]);

        $entries = TimetableDraftEntry::where('draft_id', $draft->id)->where('subject_id', $math->id)->get();
        $p1 = $entries->first(fn ($e) => $e->isDoubleLessonPart1());
        $p2 = $entries->first(fn ($e) => $e->isDoubleLessonPart2());

        $this->assertNotNull($p1, 'Double lesson Part 1 should be generated.');
        $this->assertNotNull($p2, 'Double lesson Part 2 should be generated.');
        $this->assertSame($p1->day_of_week, $p2->day_of_week);
        $this->assertSame($p1->teacher_id, $p2->teacher_id);
        $this->assertSame($p1->period_number + 1, $p2->period_number);
        $this->assertSame($p1->end_time->format('H:i'), $p2->start_time->format('H:i'));
    }

    public function test_double_lesson_never_spans_across_a_break(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 4, ['allow_double_periods' => true, 'max_per_day' => 2]);

        // Break after period 2
        $structure = $this->structure(6, null, [
            ['name' => 'Tea Break', 'after_period' => 2, 'duration' => 20, 'color' => 'amber'],
        ]);

        $draft = $this->generate(['structure' => $structure]);

        $entries = TimetableDraftEntry::where('draft_id', $draft->id)->where('subject_id', $math->id)->get();
        $p1 = $entries->first(fn ($e) => $e->isDoubleLessonPart1());
        $p2 = $entries->first(fn ($e) => $e->isDoubleLessonPart2());

        $this->assertNotNull($p1);
        $this->assertNotNull($p2);
        // The pair can NEVER be (Period 2, Period 3) because Tea Break is between them!
        $this->assertFalse(
            $p1->period_number === 2 && $p2->period_number === 3,
            'Double lesson must not span across Period 2 and 3 when tea break is after period 2.'
        );
    }

    public function test_double_lesson_never_spans_break_in_legacy_saved_structure_without_after_period(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 4, ['allow_double_periods' => true, 'max_per_day' => 2]);

        // Legacy structure saved without 'after_period' on break interval
        $legacy = new TimetableStructure([
            'institution_id' => $this->institution->id,
            'name' => 'Legacy bell schedule',
            'days_of_week' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'periods_configuration' => [
                ['period_number' => 1, 'name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => false],
                ['period_number' => 2, 'name' => 'P2', 'start_time' => '08:40', 'end_time' => '09:20', 'is_break' => false],
                ['period_number' => 0, 'name' => 'Tea', 'start_time' => '09:20', 'end_time' => '09:40', 'is_break' => true],
                ['period_number' => 3, 'name' => 'P3', 'start_time' => '09:40', 'end_time' => '10:20', 'is_break' => false],
                ['period_number' => 4, 'name' => 'P4', 'start_time' => '10:20', 'end_time' => '11:00', 'is_break' => false],
            ],
            'is_default' => true,
        ]);
        $legacy->save();

        $draft = $this->generate(['structure_id' => $legacy->id]);

        $entries = TimetableDraftEntry::where('draft_id', $draft->id)->where('subject_id', $math->id)->get();
        $p1 = $entries->first(fn ($e) => $e->isDoubleLessonPart1());
        $p2 = $entries->first(fn ($e) => $e->isDoubleLessonPart2());

        $this->assertNotNull($p1);
        $this->assertNotNull($p2);
        $this->assertFalse(
            $p1->period_number === 2 && $p2->period_number === 3,
            'Double lesson must not span across break even in legacy saved structures.'
        );
    }

    public function test_structure_with_no_adjacent_periods_falls_back_to_single_lessons(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 4, ['allow_double_periods' => true, 'max_per_day' => 2]);

        // Break between EVERY single period (e.g. 1 - break - 2 - break - 3)
        $structure = $this->structure(3, null, [
            ['name' => 'B1', 'after_period' => 1, 'duration' => 10],
            ['name' => 'B2', 'after_period' => 2, 'duration' => 10],
        ]);

        $draft = $this->generate(['structure' => $structure]);

        // All 4 lessons must be placed as singles
        $entries = TimetableDraftEntry::where('draft_id', $draft->id)->where('subject_id', $math->id)->get();
        $this->assertCount(4, $entries);
        $this->assertSame(0, $entries->filter(fn ($e) => $e->isDoubleLesson())->count());
    }

    public function test_multiple_subjects_wanting_doubles_both_fit_without_collision(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $science = $this->makeSubject('Science');

        $t1 = $this->makeTeacher('Math T');
        $t2 = $this->makeTeacher('Science T');

        $this->assign($class, $math, $t1);
        $this->assign($class, $science, $t2);

        $this->allocate($class, $math, 4, ['allow_double_periods' => true, 'max_per_day' => 2]);
        $this->allocate($class, $science, 4, ['allow_double_periods' => true, 'max_per_day' => 2]);

        $draft = $this->generate(['structure' => $this->structure(6)]);

        $entries = TimetableDraftEntry::where('draft_id', $draft->id)->get();
        $mathDoubles = $entries->where('subject_id', $math->id)->filter(fn ($e) => $e->isDoubleLesson());
        $scienceDoubles = $entries->where('subject_id', $science->id)->filter(fn ($e) => $e->isDoubleLesson());

        $this->assertCount(2, $mathDoubles);
        $this->assertCount(2, $scienceDoubles);
        $this->assertNoClashes($this->entriesOf($draft));
    }

    public function test_allow_double_periods_false_prevents_doubles_despite_heavy_subject_name(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 4, ['allow_double_periods' => false, 'max_per_day' => 1]);

        $draft = $this->generate(['structure' => $this->structure(6)]);

        $entries = TimetableDraftEntry::where('draft_id', $draft->id)->where('subject_id', $math->id)->get();
        $this->assertCount(4, $entries);
        $this->assertSame(0, $entries->filter(fn ($e) => $e->isDoubleLesson())->count());
    }

    public function test_subject_with_odd_quota_places_double_and_remaining_singles(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 5, ['allow_double_periods' => true, 'max_per_day' => 2]);

        $draft = $this->generate(['structure' => $this->structure(6)]);

        $entries = TimetableDraftEntry::where('draft_id', $draft->id)->where('subject_id', $math->id)->get();
        $this->assertCount(5, $entries);

        $doubles = $entries->filter(fn ($e) => $e->isDoubleLesson());
        $singles = $entries->filter(fn ($e) => ! $e->isDoubleLesson());

        $this->assertCount(2, $doubles); // 1 double block = 2 periods
        $this->assertCount(3, $singles); // 3 single periods
    }
}
