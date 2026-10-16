<?php

namespace Modules\Timetable\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timetable\Models\TimetableStructure;
use Tests\Support\BuildsTimetableWorld;
use Tests\TestCase;

class TimetableGeneratorConstraintsTest extends TestCase
{
    use BuildsTimetableWorld;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = $this->makeInstitution();
    }

    /** Four classes, four subjects, one teacher per subject shared by every class. */
    protected function sharedTeacherSchool(int $periodsEach = 4): array
    {
        $classes = [];
        for ($i = 0; $i < 4; $i++) {
            $classes[] = $this->makeClass('Grade 7');
        }

        $teachers = [];
        foreach (['Mathematics', 'English', 'Science', 'History'] as $name) {
            $subject = $this->makeSubject($name);
            $teacher = $this->makeTeacher($name.' teacher');
            $teachers[$subject->id] = $teacher;
            foreach ($classes as $class) {
                $this->assign($class, $subject, $teacher);
                $this->allocate($class, $subject, $periodsEach);
            }
        }

        return [$classes, $teachers];
    }

    public function test_whole_school_run_never_double_books_a_teacher_or_a_class(): void
    {
        $this->sharedTeacherSchool();

        $draft = $this->generate(['structure' => $this->structure(6)]);

        $entries = $this->entriesOf($draft);
        $this->assertNotEmpty($entries);
        $this->assertNoClashes($entries);
    }

    public function test_every_lesson_sits_on_a_real_teaching_period_never_a_break(): void
    {
        $this->sharedTeacherSchool(2);
        $structure = $this->structure(6, null, [['name' => 'Tea', 'after_period' => 3, 'duration' => 20]]);
        $teaching = collect($structure->getTeachingPeriods())->keyBy('period_number');

        $draft = $this->generate(['structure' => $structure]);

        foreach ($this->entriesOf($draft) as $e) {
            $this->assertTrue($teaching->has($e['period']), "period {$e['period']} is not a teaching period");
            $this->assertSame($teaching[$e['period']]['start_time'], $e['start']);
            $this->assertSame($teaching[$e['period']]['end_time'], $e['end']);
        }
    }

    public function test_each_class_gets_exactly_the_lessons_it_asked_for(): void
    {
        [$classes] = $this->sharedTeacherSchool(3);

        $draft = $this->generate(['structure' => $this->structure(6)]);

        foreach ($classes as $class) {
            $count = collect($this->entriesOf($draft))->where('class_id', $class->id)->count();
            $this->assertSame(12, $count); // 4 subjects × 3
        }
        $this->assertSame(100.0, (float) $draft->generation_score);
        $this->assertSame(0, $draft->conflict_summary['unplaced_count']);
        $this->assertSame(48, $draft->conflict_summary['total_required']);
    }

    public function test_a_subject_never_exceeds_its_daily_limit(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject('Mathematics');
        $this->assign($class, $subject, $this->makeTeacher());
        $this->allocate($class, $subject, 6, ['max_per_day' => 2]);

        $draft = $this->generate(['structure' => $this->structure(6)]);

        $perDay = collect($this->entriesOf($draft))->countBy('day');
        $this->assertSame(6, $perDay->sum());
        $this->assertLessThanOrEqual(2, $perDay->max());
    }

    public function test_a_derived_daily_limit_spreads_a_subject_across_the_week(): void
    {
        $class = $this->makeClass();
        $subject = $this->makeSubject('Geography');
        $this->assign($class, $subject, $this->makeTeacher());
        $this->allocate($class, $subject, 3, ['max_per_day' => 1]);

        $draft = $this->generate(['structure' => $this->structure(6)]);

        $this->assertSame(3, collect($this->entriesOf($draft))->pluck('day')->unique()->count());
    }

    public function test_teacher_never_exceeds_the_consecutive_lesson_limit_and_breaks_reset_the_run(): void
    {
        config(['timetable.max_consecutive_lessons' => 2]);
        $structure = $this->structure(6, null, [['name' => 'Tea', 'after_period' => 3, 'duration' => 20]]);

        $subject = $this->makeSubject('History');
        $teacher = $this->makeTeacher('Busy teacher');
        for ($i = 0; $i < 5; $i++) { // 20 of 30 slots
            $class = $this->makeClass();
            $this->assign($class, $subject, $teacher);
            $this->allocate($class, $subject, 4);
        }

        $draft = $this->generate(['structure' => $structure]);

        $byDay = collect($this->entriesOf($draft))->where('teacher_id', $teacher->id)->groupBy('day');
        $this->assertSame(20, $byDay->flatten(1)->count());

        foreach ($byDay as $rows) {
            $periods = $rows->pluck('period')->sort()->values()->all();
            $run = 1;
            for ($i = 1; $i < count($periods); $i++) {
                $run = $structure->areConsecutiveTeachingPeriods($periods[$i - 1], $periods[$i]) ? $run + 1 : 1;
                $this->assertLessThanOrEqual(2, $run);
            }
        }
    }

    public function test_consecutive_limit_can_be_switched_off(): void
    {
        config(['timetable.max_consecutive_lessons' => null]);
        $subject = $this->makeSubject('History');
        $teacher = $this->makeTeacher();
        for ($i = 0; $i < 5; $i++) { // 20 of 20 slots, so every period must be used
            $class = $this->makeClass();
            $this->assign($class, $subject, $teacher);
            $this->allocate($class, $subject, 4);
        }

        $draft = $this->generate(['structure' => $this->structure(4)]);

        $this->assertSame(0, $draft->conflict_summary['unplaced_count']);
    }

    public function test_daily_cap_for_teachers_is_respected(): void
    {
        config(['timetable.max_daily_lessons' => 2]);
        $subject = $this->makeSubject('History');
        $teacher = $this->makeTeacher();
        for ($i = 0; $i < 2; $i++) {
            $class = $this->makeClass();
            $this->assign($class, $subject, $teacher);
            $this->allocate($class, $subject, 4);
        }

        $draft = $this->generate(['structure' => $this->structure(6)]);

        $perDay = collect($this->entriesOf($draft))->where('teacher_id', $teacher->id)->countBy('day');
        $this->assertSame(8, $perDay->sum());
        $this->assertLessThanOrEqual(2, $perDay->max());
    }

    public function test_teacher_already_teaching_on_the_live_timetable_is_not_double_booked(): void
    {
        $classA = $this->makeClass();
        $classB = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($classA, $math, $teacher);
        $this->allocate($classA, $math, 4, ['cognitive_weight' => 'high']);
        $structure = $this->savedStructure(6);

        // Control: with nothing published, Monday period 1 is the most attractive slot.
        $before = $this->generate(['structure_id' => $structure->id, 'scope_type' => 'class', 'scope_id' => $classA->id]);
        $this->assertTrue(collect($this->entriesOf($before))->contains(fn ($e) => $e['day'] === 'Monday' && $e['period'] === 1));

        $this->publishLesson($classB, $teacher, 'Monday', '08:00', '08:40');

        $after = $this->generate(['structure_id' => $structure->id, 'scope_type' => 'class', 'scope_id' => $classA->id]);

        $this->assertFalse(collect($this->entriesOf($after))->contains(fn ($e) => $e['day'] === 'Monday' && $e['period'] === 1));
        $this->assertSame(4, count($this->entriesOf($after)));
    }

    public function test_published_lessons_of_classes_being_regenerated_do_not_block_their_own_teacher(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 4, ['cognitive_weight' => 'high']);
        $this->publishLesson($class, $teacher, 'Monday', '08:00', '08:40');

        $draft = $this->generate(['structure' => $this->savedStructure(6), 'scope_type' => 'class', 'scope_id' => $class->id]);

        $this->assertTrue(collect($this->entriesOf($draft))->contains(fn ($e) => $e['day'] === 'Monday' && $e['period'] === 1));
    }

    public function test_locked_lessons_are_kept_and_count_toward_the_quota(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 4);

        $draft = $this->generate([
            'structure' => $this->structure(6),
            'locked_entries' => [[
                'class_id' => $class->id, 'subject_id' => $math->id, 'teacher_id' => $teacher->id,
                'day_of_week' => 'Wednesday', 'period_number' => 5,
            ]],
        ]);

        $entries = $this->entriesOf($draft);
        $locked = collect($entries)->where('locked', true);

        $this->assertCount(1, $locked);
        $this->assertSame('Wednesday', $locked->first()['day']);
        $this->assertSame(5, $locked->first()['period']);
        $this->assertCount(4, $entries); // 1 locked + 3 generated
        $this->assertSame([], $draft->conflict_summary['locked_conflicts']);
    }

    public function test_conflicting_locked_lessons_are_reported_and_not_saved(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 2);

        $lock = fn ($day, $period) => [
            'class_id' => $class->id, 'subject_id' => $math->id, 'teacher_id' => $teacher->id,
            'day_of_week' => $day, 'period_number' => $period,
        ];

        $draft = $this->generate([
            'structure' => $this->structure(4),
            'locked_entries' => [
                $lock('Monday', 1),
                $lock('Monday', 1),   // same class and slot twice
                $lock('Sunday', 1),   // day not in the schedule
                $lock('Monday', 99),  // period not in the schedule
            ],
        ]);

        $conflicts = $draft->conflict_summary['locked_conflicts'];
        $this->assertCount(3, $conflicts);
        $this->assertSame('LOCKED_CONFLICT', $conflicts[0]['reason_code']);
        $this->assertSame(1, collect($this->entriesOf($draft))->where('locked', true)->count());
    }

    public function test_locked_lesson_for_a_class_outside_the_run_is_a_conflict(): void
    {
        $inScope = $this->makeClass();
        $other = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $this->assign($inScope, $math, $this->makeTeacher());
        $this->allocate($inScope, $math, 2);

        $draft = $this->generate([
            'structure' => $this->structure(4),
            'scope_type' => 'class',
            'scope_id' => $inScope->id,
            'locked_entries' => [[
                'class_id' => $other->id, 'subject_id' => $math->id, 'teacher_id' => null,
                'day_of_week' => 'Monday', 'period_number' => 1,
            ]],
        ]);

        $this->assertCount(1, $draft->conflict_summary['locked_conflicts']);
        $this->assertSame([], collect($this->entriesOf($draft))->where('class_id', $other->id)->all());
    }

    public function test_locked_lesson_clashing_with_a_busy_teacher_is_a_conflict(): void
    {
        $class = $this->makeClass();
        $other = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 2);
        $this->publishLesson($other, $teacher, 'Tuesday', '08:00', '08:40');

        $draft = $this->generate([
            'structure' => $this->structure(4),
            'scope_type' => 'class',
            'scope_id' => $class->id,
            'locked_entries' => [[
                'class_id' => $class->id, 'subject_id' => $math->id, 'teacher_id' => $teacher->id,
                'day_of_week' => 'Tuesday', 'period_number' => 1,
            ]],
        ]);

        $this->assertCount(1, $draft->conflict_summary['locked_conflicts']);
    }

    public function test_the_same_data_gives_the_same_timetable_every_time(): void
    {
        $this->sharedTeacherSchool(3);
        $structure = $this->savedStructure(6);

        $first = $this->entriesOf($this->generate(['structure_id' => $structure->id]));
        $second = $this->entriesOf($this->generate(['structure_id' => $structure->id]));

        $this->assertEquals($first, $second);
    }

    public function test_the_same_seed_repeats_exactly_and_still_respects_every_constraint(): void
    {
        $this->sharedTeacherSchool(3);
        $structure = $this->savedStructure(6);

        $a = $this->generate(['structure_id' => $structure->id, 'seed' => 'term-1']);
        $b = $this->generate(['structure_id' => $structure->id, 'seed' => 'term-1']);

        $this->assertEquals($this->entriesOf($a), $this->entriesOf($b));
        $this->assertNoClashes($this->entriesOf($a));
    }

    public function test_lessons_without_a_teacher_are_placed_as_teacher_tbd(): void
    {
        $class = $this->makeClass();
        $assigned = $this->makeSubject('Mathematics');
        $orphan = $this->makeSubject('Music');
        $this->assign($class, $assigned, $this->makeTeacher());
        $this->allocate($class, $assigned, 2);
        $this->allocate($class, $orphan, 2);

        $draft = $this->generate(['structure' => $this->structure(4)]);

        $music = collect($this->entriesOf($draft))->where('subject_id', $orphan->id);
        $this->assertCount(2, $music);
        $this->assertSame([null], $music->pluck('teacher_id')->unique()->values()->all());
        $this->assertContains('LESSON_NO_TEACHER', array_column($draft->conflict_summary['preflight_warnings'], 'code'));
    }

    public function test_class_teacher_covers_a_subject_with_no_assigned_teacher(): void
    {
        $classTeacher = $this->makeTeacher('Class teacher');
        $class = $this->makeClass('Grade 7', null, ['class_teacher_id' => $classTeacher->id]);
        $assigned = $this->makeSubject('Mathematics');
        $orphan = $this->makeSubject('Music');
        $this->assign($class, $assigned, $this->makeTeacher());
        $this->allocate($class, $assigned, 2);
        $this->allocate($class, $orphan, 2);

        $draft = $this->generate(['structure' => $this->structure(4)]);

        $music = collect($this->entriesOf($draft))->where('subject_id', $orphan->id);
        $this->assertSame([$classTeacher->id], $music->pluck('teacher_id')->unique()->values()->all());
    }

    public function test_with_several_teachers_a_free_one_is_used_when_the_first_is_busy(): void
    {
        $class = $this->makeClass();
        $other = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $first = $this->makeTeacher('First');
        $second = $this->makeTeacher('Second');
        $this->assign($class, $math, $first);
        $this->assign($class, $math, $second);
        $this->allocate($class, $math, 3);

        // The first teacher is busy for every slot on the live timetable.
        $structure = $this->savedStructure(2, ['Monday', 'Tuesday', 'Wednesday']);
        foreach (['Monday', 'Tuesday', 'Wednesday'] as $day) {
            $this->publishLesson($other, $first, $day, '08:00', '08:40');
            $this->publishLesson($other, $first, $day, '08:40', '09:20');
        }

        $draft = $this->generate(['structure_id' => $structure->id, 'scope_type' => 'class', 'scope_id' => $class->id]);

        $entries = $this->entriesOf($draft);
        $this->assertCount(3, $entries);
        $this->assertSame([$second->id], collect($entries)->pluck('teacher_id')->unique()->values()->all());
    }

    public function test_classes_and_subjects_from_another_school_never_appear(): void
    {
        $this->sharedTeacherSchool(2);
        $otherSchool = $this->makeInstitution();
        $foreign = $this->makeClass('Grade 7', $otherSchool);
        $foreignSubject = $this->makeSubject('Mathematics', $otherSchool);
        $this->assign($foreign, $foreignSubject, $this->makeTeacher());
        $this->allocate($foreign, $foreignSubject, 4);

        $draft = $this->generate(['structure' => $this->structure(6)]);

        $this->assertNotContains($foreign->id, array_column($this->entriesOf($draft), 'class_id'));
        $this->assertSame(4, $draft->conflict_summary['classes_processed']);
    }

    public function test_class_scope_only_generates_that_class(): void
    {
        [$classes] = $this->sharedTeacherSchool(2);

        $draft = $this->generate(['structure' => $this->structure(6), 'scope_type' => 'class', 'scope_id' => $classes[0]->id]);

        $this->assertSame([$classes[0]->id], collect($this->entriesOf($draft))->pluck('class_id')->unique()->values()->all());
    }

    public function test_stream_scope_generates_every_class_at_that_level(): void
    {
        $a = $this->makeClass('Grade 7');
        $b = $this->makeClass('Grade 7');
        $c = $this->makeClass('Grade 8');
        $math = $this->makeSubject('Mathematics');
        $teacherByClass = [];
        foreach ([$a, $b, $c] as $class) {
            $teacherByClass[$class->id] = $this->makeTeacher();
            $this->assign($class, $math, $teacherByClass[$class->id]);
            $this->allocate($class, $math, 2);
        }

        $draft = $this->generate(['structure' => $this->structure(4), 'scope_type' => 'stream', 'scope_id' => $a->id]);

        $ids = collect($this->entriesOf($draft))->pluck('class_id')->unique()->sort()->values()->all();
        $this->assertSame([$a->id, $b->id], $ids);
    }

    public function test_a_saved_default_structure_is_used_when_none_is_given(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $this->assign($class, $math, $this->makeTeacher());
        $this->allocate($class, $math, 2);
        $default = $this->structure(5);
        $default->is_default = true;
        $default->save();

        $draft = $this->generate();

        $this->assertSame($default->id, $draft->structure_id);
        $this->assertSame(1, TimetableStructure::where('institution_id', $this->institution->id)->count());
    }
}
