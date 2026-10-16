<?php

namespace Modules\Timetable\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timetable\Models\TimetableDraft;
use Modules\Timetable\Support\UnplacedReason;
use Tests\Support\BuildsTimetableWorld;
use Tests\TestCase;

class TimetableUnplacedReasonTest extends TestCase
{
    use BuildsTimetableWorld;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = $this->makeInstitution();
    }

    public function test_unplaced_lesson_details_contain_explicit_structured_reasons_and_entity_names(): void
    {
        $class = $this->makeClass('Grade 7 Gold');
        $externalClass = $this->makeClass('Grade 8 Blue');

        $math = $this->makeSubject('Mathematics');
        $english = $this->makeSubject('English');

        $teacherMath = $this->makeTeacher('Prof Hawking');
        $teacherEnglish = $this->makeTeacher('Mr Shakespeare');

        $this->assign($class, $math, $teacherMath);
        $this->assign($class, $english, $teacherEnglish);

        // 3 days with 2 periods per day = 6 slots available
        $structure = $this->savedStructure(2, ['Monday', 'Tuesday', 'Wednesday']);

        // Lock external lessons for Prof Hawking on live timetable so he only has 1 slot free
        $this->publishLesson($externalClass, $teacherMath, 'Monday', '08:00', '08:40');
        $this->publishLesson($externalClass, $teacherMath, 'Monday', '08:40', '09:20');
        $this->publishLesson($externalClass, $teacherMath, 'Tuesday', '08:00', '08:40');
        $this->publishLesson($externalClass, $teacherMath, 'Tuesday', '08:40', '09:20');
        $this->publishLesson($externalClass, $teacherMath, 'Wednesday', '08:00', '08:40');

        $this->allocate($class, $math, 2);
        $this->allocate($class, $english, 2);

        $draft = $this->generate([
            'structure_id' => $structure->id,
            'scope_type' => 'class',
            'scope_id' => $class->id,
        ]);

        $this->assertInstanceOf(TimetableDraft::class, $draft);
        $summary = $draft->conflict_summary;

        $this->assertGreaterThan(0, $summary['unplaced_count']);
        $this->assertNotEmpty($summary['unplaced_details']);

        $detail = $summary['unplaced_details'][0];
        $this->assertSame($class->id, $detail['class_id']);
        $this->assertSame($class->name, $detail['class_name']);
        $this->assertSame($math->id, $detail['subject_id']);
        $this->assertSame('Mathematics', $detail['subject_name']);
        $this->assertSame($teacherMath->id, $detail['teacher_id']);
        $this->assertStringContainsString('Prof Hawking', $detail['teacher_name']);
        $this->assertSame(UnplacedReason::TeacherBusy->value, $detail['reason_code']);
        $this->assertNotEmpty($detail['reason']);
    }

    public function test_teacher_busy_is_reported_when_shared_teacher_has_no_compatible_slot(): void
    {
        $c1 = $this->makeClass('Class 1');
        $c2 = $this->makeClass('Class 2');

        $s1 = $this->makeSubject('Mathematics');
        $s2 = $this->makeSubject('English');

        $t1 = $this->makeTeacher('Shared Math Teacher');
        $t2 = $this->makeTeacher('Shared English Teacher');

        $this->assign($c1, $s1, $t1);
        $this->assign($c1, $s2, $t2);

        $this->assign($c2, $s1, $t1);
        $this->assign($c2, $s2, $t2);

        // 2 days * 2 periods = 4 slots total per class.
        // C1 and C2 each need 2 math + 2 english = 4 lessons each.
        // Total slots available per teacher = 4.
        $this->allocate($c1, $s1, 2);
        $this->allocate($c1, $s2, 2);
        $this->allocate($c2, $s1, 2);
        $this->allocate($c2, $s2, 2);

        $structure = $this->savedStructure(2, ['Monday', 'Tuesday']);

        // Lock C1 on Monday with T1 and Tuesday with T2, while C2 is locked on Tuesday with T2.
        // T1 is locked with C1 on Monday P1 and P2.
        // C2 is locked with T2 on Tuesday P1 and P2.
        // So C2 cannot place T1 on Monday (T1 is busy with C1) or Tuesday (C2 has T2 locked).
        $lockedEntries = [
            [
                'class_id' => $c1->id,
                'subject_id' => $s1->id,
                'teacher_id' => $t1->id,
                'day_of_week' => 'Monday',
                'period_number' => 1,
                'start_time' => '08:00',
                'end_time' => '08:40',
            ],
            [
                'class_id' => $c1->id,
                'subject_id' => $s1->id,
                'teacher_id' => $t1->id,
                'day_of_week' => 'Monday',
                'period_number' => 2,
                'start_time' => '08:40',
                'end_time' => '09:20',
            ],
            [
                'class_id' => $c2->id,
                'subject_id' => $s2->id,
                'teacher_id' => $t2->id,
                'day_of_week' => 'Tuesday',
                'period_number' => 1,
                'start_time' => '08:00',
                'end_time' => '08:40',
            ],
            [
                'class_id' => $c2->id,
                'subject_id' => $s2->id,
                'teacher_id' => $t2->id,
                'day_of_week' => 'Tuesday',
                'period_number' => 2,
                'start_time' => '08:40',
                'end_time' => '09:20',
            ],
        ];

        $draft = $this->generate([
            'structure_id' => $structure->id,
            'scope_type' => 'whole_school',
            'locked_entries' => $lockedEntries,
        ]);

        $this->assertInstanceOf(TimetableDraft::class, $draft);
        $summary = $draft->conflict_summary;

        $this->assertGreaterThan(0, $summary['unplaced_count']);
        $this->assertNotEmpty($summary['unplaced_details']);

        $c2Unplaced = collect($summary['unplaced_details'])
            ->firstWhere('class_id', $c2->id);

        $this->assertNotNull($c2Unplaced);
        $this->assertSame($s1->id, $c2Unplaced['subject_id']);
        $this->assertSame(UnplacedReason::TeacherBusy->value, $c2Unplaced['reason_code']);
    }

    public function test_unplaced_details_structure_when_collision_occurs(): void
    {
        $c1 = $this->makeClass('Class A');
        $cExternal = $this->makeClass('External Class');

        $s1 = $this->makeSubject('Subject 1');
        $s2 = $this->makeSubject('Subject 2');

        $t1 = $this->makeTeacher('Teacher 1');
        $t2 = $this->makeTeacher('Teacher 2');

        $this->assign($c1, $s1, $t1);
        $this->assign($c1, $s2, $t2);

        // 3 days * 2 periods = 6 slots available per class.
        // Class A needs 2 of S1 and 2 of S2.
        // Lock Teacher 1 in 5 of the 6 slots for an external class so Teacher 1 is busy!
        $this->publishLesson($cExternal, $t1, 'Monday', '08:00', '08:40');
        $this->publishLesson($cExternal, $t1, 'Monday', '08:40', '09:20');
        $this->publishLesson($cExternal, $t1, 'Tuesday', '08:00', '08:40');
        $this->publishLesson($cExternal, $t1, 'Tuesday', '08:40', '09:20');
        $this->publishLesson($cExternal, $t1, 'Wednesday', '08:00', '08:40');

        $this->allocate($c1, $s1, 2);
        $this->allocate($c1, $s2, 2);

        $structure = $this->savedStructure(2, ['Monday', 'Tuesday', 'Wednesday']);

        $draft = $this->generate([
            'structure_id' => $structure->id,
            'scope_type' => 'class',
            'scope_id' => $c1->id,
        ]);

        $this->assertInstanceOf(TimetableDraft::class, $draft);
        $summary = $draft->conflict_summary;

        $this->assertArrayHasKey('unplaced_details', $summary);
        $this->assertArrayHasKey('unplaced_count', $summary);
        $this->assertArrayHasKey('total_placed', $summary);
        $this->assertArrayHasKey('total_required', $summary);
        $this->assertArrayHasKey('locked_conflicts', $summary);
        $this->assertArrayHasKey('double_fallbacks', $summary);
        $this->assertArrayHasKey('classes_processed', $summary);
        $this->assertArrayHasKey('total_periods_per_week', $summary);

        $this->assertGreaterThan(0, $summary['unplaced_count']);
        $this->assertLessThan($summary['total_required'], $summary['total_placed']);
    }
}
