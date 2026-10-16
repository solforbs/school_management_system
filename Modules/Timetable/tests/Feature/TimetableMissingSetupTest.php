<?php

namespace Modules\Timetable\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Timetable\Models\TimetableDraft;
use Modules\Timetable\Models\TimetableStructure;
use Modules\Timetable\Support\PreflightFailedException;
use Tests\Support\BuildsTimetableWorld;
use Tests\TestCase;

class TimetableMissingSetupTest extends TestCase
{
    use BuildsTimetableWorld;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = $this->makeInstitution();
    }

    public function test_generating_when_no_classes_exist_throws_preflight_exception_and_creates_no_records(): void
    {
        $this->expectException(PreflightFailedException::class);

        try {
            $this->generate(['structure' => $this->structure(6)]);
        } finally {
            $this->assertSame(0, TimetableDraft::where('institution_id', $this->institution->id)->count());
            $this->assertSame(0, TimetableStructure::where('institution_id', $this->institution->id)->count());
        }
    }

    public function test_generating_when_no_subjects_exist_throws_preflight_exception_with_clear_reason(): void
    {
        $this->makeClass();
        $this->makeTeacher();

        try {
            $this->generate(['structure' => $this->structure(6)]);
            $this->fail('Expected PreflightFailedException was not thrown.');
        } catch (PreflightFailedException $e) {
            $this->assertTrue($e->report->has('NO_SUBJECTS'));
            $issue = $e->report->withCode('NO_SUBJECTS')[0];
            $this->assertNotEmpty($issue->message);
            $this->assertNotEmpty($issue->howToFix);
            $this->assertSame('subject.index', $issue->actionRoute);
        }

        // Verify no orphan drafts or structures were created
        $this->assertSame(0, TimetableDraft::where('institution_id', $this->institution->id)->count());
    }

    public function test_generating_when_no_teachers_exist_throws_preflight_exception_with_clear_guidance(): void
    {
        $this->makeClass();
        $this->makeSubject('Mathematics');

        try {
            $this->generate(['structure' => $this->structure(6)]);
            $this->fail('Expected PreflightFailedException was not thrown.');
        } catch (PreflightFailedException $e) {
            $this->assertTrue($e->report->has('NO_TEACHERS'));
            $issue = $e->report->withCode('NO_TEACHERS')[0];
            $this->assertNotEmpty($issue->howToFix);
            $this->assertSame('teacher.index', $issue->actionRoute);
        }

        $this->assertSame(0, TimetableDraft::where('institution_id', $this->institution->id)->count());
    }

    public function test_generating_when_class_has_no_allocated_subjects_is_blocked(): void
    {
        // School has subjects and teachers, but this class has no allocations and no teacher mappings
        $this->makeClass('Grade 1');
        $this->makeSubject('Physics');
        $this->makeTeacher('Physics Teacher');

        try {
            $this->generate(['structure' => $this->structure(6)]);
            $this->fail('Expected PreflightFailedException was not thrown.');
        } catch (PreflightFailedException $e) {
            $this->assertTrue($e->report->has('CLASS_NO_SUBJECTS'));
            $issue = $e->report->withCode('CLASS_NO_SUBJECTS')[0];
            $this->assertStringContainsString('Grade 1', $issue->message);
            $this->assertNotEmpty($issue->howToFix);
        }

        $this->assertSame(0, TimetableDraft::where('institution_id', $this->institution->id)->count());
    }

    public function test_generating_when_class_is_over_capacity_is_blocked_with_exact_numbers(): void
    {
        $class = $this->makeClass('Grade 7');
        $teacher = $this->makeTeacher();

        // 5 subjects with 5 lessons each = 25 lessons, but 4-period day over 5 days = 20 slots
        for ($i = 1; $i <= 5; $i++) {
            $sub = $this->makeSubject("Subject {$i}");
            $this->assign($class, $sub, $teacher);
            $this->allocate($class, $sub, 5, ['max_per_day' => 2]);
        }

        try {
            $this->generate(['structure' => $this->structure(4)]);
            $this->fail('Expected PreflightFailedException was not thrown.');
        } catch (PreflightFailedException $e) {
            $this->assertTrue($e->report->has('CLASS_OVER_CAPACITY'));
            $issue = $e->report->withCode('CLASS_OVER_CAPACITY')[0];
            $this->assertSame(25, $issue->context['required']);
            $this->assertSame(20, $issue->context['available']);
        }

        $this->assertSame(0, TimetableDraft::where('institution_id', $this->institution->id)->count());
    }

    public function test_generating_when_teacher_is_overloaded_is_blocked(): void
    {
        $teacher = $this->makeTeacher('Mr Single Handed');
        $subject = $this->makeSubject('Mathematics');

        // 6 classes, 4 lessons each = 24 lessons for this single teacher, but weekly capacity is 20
        for ($i = 1; $i <= 6; $i++) {
            $class = $this->makeClass("Class {$i}");
            $this->assign($class, $subject, $teacher);
            $this->allocate($class, $subject, 4);
        }

        try {
            $this->generate(['structure' => $this->structure(4)]);
            $this->fail('Expected PreflightFailedException was not thrown.');
        } catch (PreflightFailedException $e) {
            $this->assertTrue($e->report->has('TEACHER_OVERBOOKED'));
            $issue = $e->report->withCode('TEACHER_OVERBOOKED')[0];
            $this->assertSame($teacher->id, $issue->context['teacher_id']);
            $this->assertSame(24, $issue->context['required']);
            $this->assertSame(20, $issue->context['available']);
        }

        $this->assertSame(0, TimetableDraft::where('institution_id', $this->institution->id)->count());
    }
}
