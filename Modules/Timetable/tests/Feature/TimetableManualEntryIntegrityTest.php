<?php

namespace Modules\Timetable\Tests\Feature;

use App\Models\TeacherSubjects;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;
use Modules\Lesson\Models\Lesson;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Models\TimetableStructure;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsTimetableWorld;
use Tests\TestCase;

class TimetableManualEntryIntegrityTest extends TestCase
{
    use BuildsTimetableWorld;
    use RefreshDatabase;

    protected User $director;

    protected TimetableStructure $structure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->director = User::factory()->create();
        $this->director->assignRole('Director');

        $this->institution = Institution::create([
            'user_id' => $this->director->id,
            'name' => 'Integrity Academy '.uniqid(),
            'code' => 'INT-'.uniqid(),
            'type' => 'School',
            'is_active' => true,
            'status' => 'active',
            'is_approved' => true,
        ]);

        $this->director->update(['active_institution_id' => $this->institution->id]);

        Role::findByName('Director')->givePermissionTo([
            'view timetable',
            'create timetable',
            'edit timetable',
            'delete timetable',
        ]);

        $this->structure = $this->savedStructure(4);
        $this->structure->update(['is_default' => true]);
    }

    public function test_manual_entry_prevents_teacher_clash_on_period_one(): void
    {
        $teacher = $this->makeTeacher('Mr. Ochieng');
        $class1 = $this->makeClass('Grade 7');
        $class2 = $this->makeClass('Grade 8');

        // Schedule teacher in Class 1 for Monday 08:00-08:40
        TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class1->id,
            'teacher_id' => $teacher->id,
            'subject' => 'Science',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
        ]);

        // Try to manually schedule the same teacher in Class 2 at the same time
        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->set('classId', (string) $class2->id)
            ->call('openLessonModal', 'Monday', '08:00-08:40')
            ->set('lessonSubject', 'Biology')
            ->set('lessonTeacherId', $teacher->id)
            ->set('lessonIsDraft', false)
            ->call('saveLesson')
            ->assertHasErrors(['lessonTeacherId'])
            ->assertSee('Teacher Clash')
            ->assertSee('Cannot save schedule slot');

        $this->assertEquals(0, TimetableEntry::where('class_id', $class2->id)->count());
    }

    public function test_manual_entry_prevents_double_lesson_from_overwriting_subsequent_period_with_recorded_attendance(): void
    {
        $class = $this->makeClass('Grade 7');

        // Period 2 (08:40-09:20) has History
        $historyEntry = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'subject' => 'History',
            'day_of_week' => 'Monday',
            'start_time' => '08:40',
            'end_time' => '09:20',
        ]);

        // History has recorded student attendance
        Lesson::create([
            'institution_id' => $this->institution->id,
            'timetable_entry_id' => $historyEntry->id,
            'class_id' => $class->id,
            'lesson_date' => now()->toDateString(),
            'status' => 'attended',
        ]);

        // Director tries to place a double lesson of Mathematics starting at 08:00
        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->set('classId', (string) $class->id)
            ->set('lessonDay', 'Monday')
            ->set('lessonPeriod', '08:00-08:40')
            ->set('lessonSubject', 'Mathematics')
            ->set('lessonIsDouble', true)
            ->set('lessonIsDraft', false)
            ->call('saveLesson')
            ->assertHasErrors(['lessonIsDouble']);

        // Ensure History entry remains intact
        $this->assertEquals('History', TimetableEntry::where('id', $historyEntry->id)->value('subject'));
    }

    public function test_unchecking_double_lesson_unlinks_twin_part_two_badge(): void
    {
        $class = $this->makeClass('Grade 7');

        $part1 = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'subject' => 'Physics',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
            'notes' => '[Double Lesson Part 1/2]',
        ]);

        $part2 = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'subject' => 'Physics',
            'day_of_week' => 'Monday',
            'start_time' => '08:40',
            'end_time' => '09:20',
            'notes' => '[Double Lesson Part 2/2]',
        ]);

        // Edit Part 1 and uncheck double lesson
        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->set('classId', (string) $class->id)
            ->set('lessonEntryId', $part1->id)
            ->set('lessonDay', 'Monday')
            ->set('lessonPeriod', '08:00-08:40')
            ->set('lessonSubject', 'Physics')
            ->set('lessonIsDouble', false)
            ->set('lessonIsDraft', false)
            ->call('saveLesson')
            ->assertHasNoErrors();

        // Part 1 notes should be cleared
        $this->assertNull($part1->fresh()->notes);

        // Part 2 should still exist, but double lesson badge is unlinked
        $this->assertNotNull($part2->fresh());
        $this->assertNull($part2->fresh()->notes);
    }

    public function test_deleting_double_lesson_part_one_removes_twin_part_two_if_unattended(): void
    {
        $class = $this->makeClass('Grade 7');

        $part1 = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'subject' => 'Chemistry',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
            'notes' => '[Double Lesson Part 1/2]',
        ]);

        $part2 = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'subject' => 'Chemistry',
            'day_of_week' => 'Monday',
            'start_time' => '08:40',
            'end_time' => '09:20',
            'notes' => '[Double Lesson Part 2/2]',
        ]);

        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->set('classId', (string) $class->id)
            ->call('deleteEntry', $part1->id)
            ->assertHasNoErrors();

        $this->assertFalse(TimetableEntry::where('id', $part1->id)->exists());
        $this->assertFalse(TimetableEntry::where('id', $part2->id)->exists());
    }

    public function test_deleting_double_lesson_part_one_preserves_twin_part_two_if_it_has_lessons_and_unlinks_badge(): void
    {
        $class = $this->makeClass('Grade 7');

        $part1 = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'subject' => 'Chemistry',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
            'notes' => '[Double Lesson Part 1/2]',
        ]);

        $part2 = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'subject' => 'Chemistry',
            'day_of_week' => 'Monday',
            'start_time' => '08:40',
            'end_time' => '09:20',
            'notes' => '[Double Lesson Part 2/2]',
        ]);

        Lesson::create([
            'institution_id' => $this->institution->id,
            'timetable_entry_id' => $part2->id,
            'class_id' => $class->id,
            'lesson_date' => now()->toDateString(),
            'status' => 'attended',
        ]);

        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->set('classId', (string) $class->id)
            ->call('deleteEntry', $part1->id)
            ->assertHasNoErrors();

        $this->assertFalse(TimetableEntry::where('id', $part1->id)->exists());
        $this->assertTrue(TimetableEntry::where('id', $part2->id)->exists());
        $this->assertNull($part2->fresh()->notes);
    }

    public function test_deleting_double_lesson_part_two_unlinks_part_one_badge(): void
    {
        $class = $this->makeClass('Grade 7');

        $part1 = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'subject' => 'Geography',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
            'notes' => '[Double Lesson Part 1/2]',
        ]);

        $part2 = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'subject' => 'Geography',
            'day_of_week' => 'Monday',
            'start_time' => '08:40',
            'end_time' => '09:20',
            'notes' => '[Double Lesson Part 2/2]',
        ]);

        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->set('classId', (string) $class->id)
            ->call('deleteEntry', $part2->id)
            ->assertHasNoErrors();

        $this->assertFalse(TimetableEntry::where('id', $part2->id)->exists());
        $this->assertTrue(TimetableEntry::where('id', $part1->id)->exists());
        $this->assertNull($part1->fresh()->notes);
    }

    public function test_open_add_lesson_modal_sets_up_in_grid_entry_and_auto_assigns_teacher(): void
    {
        $class = $this->makeClass('Grade 6');
        $subject = $this->makeSubject('Mathematics');
        $teacher = $this->makeTeacher('Mr. Kariuki');

        SubjectTeacher::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->set('classId', (string) $class->id)
            ->call('openAddLessonModal', 'Monday', '08:00-08:40')
            ->assertSet('showLessonModal', true)
            ->assertSet('lessonDay', 'Monday')
            ->assertSet('lessonPeriod', '08:00-08:40')
            ->set('lessonSubject', 'Mathematics')
            ->assertSet('lessonTeacherId', $teacher->id)
            ->call('saveLesson')
            ->assertHasNoErrors()
            ->assertSet('showLessonModal', false);

        $entry = TimetableEntry::where('class_id', $class->id)
            ->where('day_of_week', 'Monday')
            ->where('start_time', '08:00')
            ->first();

        $this->assertNotNull($entry);
        $this->assertEquals('Mathematics', $entry->subject);
        $this->assertEquals($teacher->id, $entry->teacher_id);
    }

    public function test_period_two_teacher_clash_displays_exact_class_diagnostic(): void
    {
        $classA = $this->makeClass('Form 1 North');
        $classB = $this->makeClass('Form 2 South');
        $teacher = $this->makeTeacher('Shared Teacher');

        // Teacher is already booked in Form 2 South during period 2 (08:40-09:20)
        TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $classB->id,
            'teacher_id' => $teacher->id,
            'subject' => 'Biology',
            'day_of_week' => 'Monday',
            'start_time' => '08:40',
            'end_time' => '09:20',
        ]);

        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->set('classId', (string) $classA->id)
            ->call('openLessonModal', 'Monday', '08:00-08:40')
            ->set('lessonSubject', 'Chemistry')
            ->set('lessonTeacherId', $teacher->id)
            ->set('lessonIsDouble', true)
            ->call('saveLesson')
            ->assertHasErrors(['lessonTeacherId'])
            ->assertSee('Teacher Clash (Period 2)')
            ->assertSee('Form 2 South')
            ->assertSet('showLessonModal', true);
    }

    public function test_edit_period_modal_displays_validation_errors(): void
    {
        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->call('openEditPeriodModal', '08:00-08:40', 'Period 1')
            ->assertSet('showEditPeriodModal', true)
            ->set('editPeriodStart', '09:00')
            ->set('editPeriodEnd', '08:00') // end before start
            ->call('savePeriodTime')
            ->assertHasErrors(['editPeriodEnd'])
            ->assertSee('Cannot save period hours')
            ->assertSet('showEditPeriodModal', true);
    }

    public function test_edit_break_modal_displays_validation_errors(): void
    {
        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->call('openNewBreakModal')
            ->assertSet('showEditBreakModal', true)
            ->set('editBreakName', '') // required
            ->call('saveBreak')
            ->assertHasErrors(['editBreakName'])
            ->assertSee('Cannot save rest break')
            ->assertSet('showEditBreakModal', true);
    }

    public function test_teacher_subject_mismatch_validation_blocks_unqualified_teachers(): void
    {
        $class = $this->makeClass('Grade 8');
        $math = $this->makeSubject('Mathematics');
        $french = $this->makeSubject('French');
        $frenchTeacher = $this->makeTeacher('Madame Dupont');

        // Madame Dupont is registered only for French
        TeacherSubjects::create([
            'teacher_id' => $frenchTeacher->id,
            'subject_id' => $french->id,
        ]);

        // Another teacher is registered for Mathematics so math has defined qualifications
        $mathTeacher = $this->makeTeacher('Mr. Euler');
        TeacherSubjects::create([
            'teacher_id' => $mathTeacher->id,
            'subject_id' => $math->id,
        ]);

        // Attempting to assign Madame Dupont to teach Mathematics should be blocked
        Livewire::actingAs($this->director)
            ->test('timetable::index')
            ->set('classId', (string) $class->id)
            ->call('openLessonModal', 'Monday', '08:00-08:40')
            ->set('lessonSubject', 'Mathematics')
            ->set('lessonTeacherId', $frenchTeacher->id)
            ->call('saveLesson')
            ->assertHasErrors(['lessonTeacherId'])
            ->assertSee('Subject Mismatch')
            ->assertSee('is not registered or qualified to teach Mathematics')
            ->assertSet('showLessonModal', true);
    }
}
