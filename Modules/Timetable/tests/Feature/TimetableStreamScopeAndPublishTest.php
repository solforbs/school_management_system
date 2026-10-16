<?php

namespace Modules\Timetable\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Lesson\Models\Lesson;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Services\TimetableDemandResolver;
use Modules\Timetable\Services\TimetablePublishService;
use Tests\Support\BuildsTimetableWorld;
use Tests\TestCase;

class TimetableStreamScopeAndPublishTest extends TestCase
{
    use BuildsTimetableWorld;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = $this->makeInstitution();
    }

    public function test_stream_scope_only_targets_classes_with_the_same_grade_level(): void
    {
        $c1 = $this->makeClass('Grade 7 Gold', $this->institution, ['level' => 'Grade 7']);
        $c2 = $this->makeClass('Grade 7 Silver', $this->institution, ['level' => 'Grade 7']);
        $cOther = $this->makeClass('Grade 8 Gold', $this->institution, ['level' => 'Grade 8']);

        $resolver = app(TimetableDemandResolver::class);
        $targets = $resolver->targetClasses($this->institution->id, 'stream', $c1->id);

        $targetIds = $targets->pluck('id')->all();
        $this->assertContains($c1->id, $targetIds);
        $this->assertContains($c2->id, $targetIds);
        $this->assertNotContains($cOther->id, $targetIds);
    }

    public function test_scoped_generation_with_missing_or_invalid_scope_id_fails_cleanly(): void
    {
        $this->makeClass('Grade 7 Gold', $this->institution, ['level' => 'Grade 7']);

        $resolver = app(TimetableDemandResolver::class);

        // Missing scope_id
        $targetsClass = $resolver->targetClasses($this->institution->id, 'class', null);
        $this->assertTrue($targetsClass->isEmpty());

        $targetsStream = $resolver->targetClasses($this->institution->id, 'stream', null);
        $this->assertTrue($targetsStream->isEmpty());

        // Non-existent scope_id
        $targetsNonExistent = $resolver->targetClasses($this->institution->id, 'class', 99999);
        $this->assertTrue($targetsNonExistent->isEmpty());
    }

    public function test_publishing_draft_promotes_structure_to_default_and_unsets_previous(): void
    {
        $c1 = $this->makeClass('Grade 7');
        $s1 = $this->makeSubject('Mathematics');
        $t1 = $this->makeTeacher('Prof Hawking');
        $this->assign($c1, $s1, $t1);
        $this->allocate($c1, $s1, 2);

        // Active baseline structure
        $baselineStructure = $this->savedStructure(2, ['Monday', 'Tuesday']);
        $baselineStructure->update(['is_default' => true]);

        // Custom new routine for the draft
        $draftStructure = $this->savedStructure(2, ['Monday', 'Tuesday']);
        $draftStructure->update(['is_default' => false]);

        $draft = $this->generate([
            'structure_id' => $draftStructure->id,
            'make_default' => false,
            'scope_type' => 'whole_school',
        ]);

        // Before publishing, baseline structure MUST still be default!
        $this->assertTrue($baselineStructure->fresh()->is_default);
        $this->assertFalse($draftStructure->fresh()->is_default);

        // Publish
        $metrics = app(TimetablePublishService::class)->publish($draft, 1);
        $this->assertGreaterThan(0, $metrics['total_draft_entries']);

        // After publishing, draftStructure is promoted to default, baseline unset!
        $this->assertTrue($draftStructure->fresh()->is_default);
        $this->assertFalse($baselineStructure->fresh()->is_default);
    }

    public function test_publishing_draft_matches_legacy_entries_with_null_subject_id_by_name(): void
    {
        $class = $this->makeClass('Grade 7');
        $subject = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher('Prof Hawking');
        $this->assign($class, $subject, $teacher);
        $this->allocate($class, $subject, 1);

        $structure = $this->savedStructure(2, ['Monday', 'Tuesday']);

        // Create a historical entry with subject_id = null and an attached lesson
        $historicalEntry = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'class_name' => $class->name,
            'subject' => 'Mathematics',
            'subject_id' => null,
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
        ]);

        Lesson::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'timetable_entry_id' => $historicalEntry->id,
            'status' => 'attended',
            'lesson_date' => now()->toDateString(),
        ]);

        $draft = $this->generate([
            'structure_id' => $structure->id,
            'scope_type' => 'class',
            'scope_id' => $class->id,
        ]);

        $metrics = app(TimetablePublishService::class)->publish($draft, 1);

        // Legacy entry updated in place and upgraded with subject_id
        $this->assertSame(1, $metrics['updated_in_place']);
        $this->assertSame(0, $metrics['historical_preserved']);
        $this->assertSame($subject->id, $historicalEntry->fresh()->subject_id);
    }
}
