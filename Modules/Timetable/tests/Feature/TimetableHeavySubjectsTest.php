<?php

namespace Modules\Timetable\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timetable\Models\TimetableDraft;
use Modules\Timetable\Models\TimetableDraftEntry;
use Tests\Support\BuildsTimetableWorld;
use Tests\TestCase;

class TimetableHeavySubjectsTest extends TestCase
{
    use BuildsTimetableWorld;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = $this->makeInstitution();
    }

    public function test_maths_and_sciences_prefer_morning_periods_on_6_period_day(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher('Math Teacher');
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 4, ['max_per_day' => 1, 'cognitive_weight' => 'high']);

        $draft = $this->generate([
            'structure' => $this->structure(6),
            'prefer_morning_high_cognitive' => true,
        ]);

        $entries = TimetableDraftEntry::where('draft_id', $draft->id)->where('subject_id', $math->id)->get();
        $this->assertCount(4, $entries);

        // On a 6-period day, morning is periods 1-3. All 4 single lessons on different days should be in morning!
        foreach ($entries as $e) {
            $this->assertLessThanOrEqual(3, $e->period_number, "Math should be in morning half (P1-3) but got P{$e->period_number}");
        }
    }

    public function test_maths_and_sciences_prefer_morning_periods_on_8_period_day(): void
    {
        $class = $this->makeClass();
        $science = $this->makeSubject('Integrated Science');
        $teacher = $this->makeTeacher('Science Teacher');
        $this->assign($class, $science, $teacher);
        $this->allocate($class, $science, 4, ['max_per_day' => 1, 'cognitive_weight' => 'high']);

        $draft = $this->generate([
            'structure' => $this->structure(8),
            'prefer_morning_high_cognitive' => true,
        ]);

        $entries = TimetableDraftEntry::where('draft_id', $draft->id)->where('subject_id', $science->id)->get();
        $this->assertCount(4, $entries);

        // On 8-period day, morning is periods 1-4.
        foreach ($entries as $e) {
            $this->assertLessThanOrEqual(4, $e->period_number, "Science should be in morning half (P1-4) but got P{$e->period_number}");
        }
    }

    public function test_heavy_subject_is_never_placed_in_last_period_when_free_slots_exist(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 4, ['max_per_day' => 1, 'cognitive_weight' => 'high']);

        // 8 periods per day, class only has 4 lessons total in the week!
        $draft = $this->generate([
            'structure' => $this->structure(8),
            'prefer_morning_high_cognitive' => true,
        ]);

        $entries = TimetableDraftEntry::where('draft_id', $draft->id)->where('subject_id', $math->id)->get();
        foreach ($entries as $e) {
            $this->assertNotEquals(8, $e->period_number, 'Heavy subject should never be placed in final period 8 when morning slots are available.');
        }
    }

    public function test_disabling_prefer_morning_removes_the_morning_bias(): void
    {
        $class = $this->makeClass();
        $math = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher();
        $this->assign($class, $math, $teacher);
        $this->allocate($class, $math, 4, ['max_per_day' => 1, 'cognitive_weight' => 'high']);

        $draft = $this->generate([
            'structure' => $this->structure(8),
            'prefer_morning_high_cognitive' => false,
            'seed' => 'afternoon_seed_test',
        ]);

        $this->assertInstanceOf(TimetableDraft::class, $draft);
        $this->assertSame(4, TimetableDraftEntry::where('draft_id', $draft->id)->count());
    }

    public function test_competing_heavy_subjects_for_one_teacher_produce_no_collision(): void
    {
        $class1 = $this->makeClass('Grade 7 A');
        $class2 = $this->makeClass('Grade 7 B');

        $math = $this->makeSubject('Mathematics');
        $physics = $this->makeSubject('Physics');

        $teacher = $this->makeTeacher('STEM Specialist');

        // Teacher teaches Math to Class 1 and Physics to Class 2
        $this->assign($class1, $math, $teacher);
        $this->assign($class2, $physics, $teacher);

        $this->allocate($class1, $math, 4, ['max_per_day' => 1, 'cognitive_weight' => 'high']);
        $this->allocate($class2, $physics, 4, ['max_per_day' => 1, 'cognitive_weight' => 'high']);

        $draft = $this->generate([
            'structure' => $this->structure(6),
            'prefer_morning_high_cognitive' => true,
        ]);

        $entries = $this->entriesOf($draft);
        $this->assertCount(8, $entries);
        $this->assertNoClashes($entries);
    }
}
