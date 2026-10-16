<?php

namespace Modules\Timetable\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\Lesson\Models\Lesson;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Actions\SaveTimetableEntry;
use Modules\Timetable\Models\TimetableDraft;
use Modules\Timetable\Models\TimetableDraftEntry;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Services\TimetableGeneratorService;
use Modules\Timetable\Services\TimetablePublishService;
use Tests\TestCase;

class TimetableAutoGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected Institution $institution;

    protected User $admin;

    protected User $teacher1;

    protected User $teacher2;

    protected SchoolClass $class1;

    protected SchoolClass $class2;

    protected Subject $math;

    protected Subject $english;

    protected Subject $phe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@example.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('password')]
        );

        $this->institution = Institution::firstOrCreate(
            ['name' => 'Test Academy'],
            ['code' => 'TA01', 'user_id' => $this->admin->id]
        );

        $this->teacher1 = User::firstOrCreate(
            ['email' => 'teacher1_test@example.com'],
            ['name' => 'Mr. Mwangi', 'password' => bcrypt('password')]
        );

        $this->teacher2 = User::firstOrCreate(
            ['email' => 'teacher2_test@example.com'],
            ['name' => 'Mrs. Achieng', 'password' => bcrypt('password')]
        );

        $curriculum = Curriculum::firstOrCreate(
            ['institution_id' => $this->institution->id, 'name' => 'CBC Junior School'],
            ['system' => 'cbc', 'status' => 'active']
        );

        $this->class1 = SchoolClass::firstOrCreate(
            ['institution_id' => $this->institution->id, 'name' => 'Grade 7 Blue'],
            ['level' => 'Grade 7', 'curriculum_id' => $curriculum->id]
        );

        $this->class2 = SchoolClass::firstOrCreate(
            ['institution_id' => $this->institution->id, 'name' => 'Grade 7 Red'],
            ['level' => 'Grade 7', 'curriculum_id' => $curriculum->id]
        );

        $this->math = Subject::firstOrCreate(
            ['institution_id' => $this->institution->id, 'name' => 'Mathematics'],
            ['code' => 'MATH', 'is_compulsory' => true, 'is_active' => true]
        );

        $this->english = Subject::firstOrCreate(
            ['institution_id' => $this->institution->id, 'name' => 'English Language'],
            ['code' => 'ENG', 'is_compulsory' => true, 'is_active' => true]
        );

        $this->phe = Subject::firstOrCreate(
            ['institution_id' => $this->institution->id, 'name' => 'Physical and Health Education'],
            ['code' => 'PHE', 'is_compulsory' => false, 'is_active' => true]
        );

        // Assign teachers to classes
        SubjectTeacher::firstOrCreate([
            'institution_id' => $this->institution->id,
            'class_id' => $this->class1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
        ]);

        SubjectTeacher::firstOrCreate([
            'institution_id' => $this->institution->id,
            'class_id' => $this->class2->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id, // Teacher 1 teaches Math to both classes
        ]);
    }

    public function test_class_is_double_booked_prevents_simultaneous_lessons_for_same_class(): void
    {
        // Schedule Lesson 1: Class 1 on Monday 08:20 - 09:00
        TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $this->class1->id,
            'teacher_id' => $this->teacher1->id,
            'subject_id' => $this->math->id,
            'subject' => $this->math->name,
            'day_of_week' => 'Monday',
            'start_time' => '08:20',
            'end_time' => '09:00',
        ]);

        // Attempt Lesson 2 for same Class 1 overlapping on Monday 08:30 - 09:10
        $overlappingData = [
            'class_id' => $this->class1->id,
            'teacher_id' => $this->teacher2->id,
            'subject_id' => $this->english->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:30',
            'end_time' => '09:10',
        ];

        $this->assertTrue(
            SaveTimetableEntry::classIsDoubleBooked($overlappingData),
            'Class double booking must be detected when another lesson overlaps.'
        );

        // Non-overlapping time slot should not trigger double booking
        $nonOverlappingData = [
            'class_id' => $this->class1->id,
            'teacher_id' => $this->teacher2->id,
            'subject_id' => $this->english->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:50',
            'end_time' => '10:30',
        ];

        $this->assertFalse(
            SaveTimetableEntry::classIsDoubleBooked($nonOverlappingData),
            'Non-overlapping lesson slot must not be flagged as collision.'
        );
    }

    public function test_teacher_is_double_booked_prevents_simultaneous_teaching(): void
    {
        // Teacher 1 teaches Class 1 on Monday 08:20 - 09:00
        TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $this->class1->id,
            'teacher_id' => $this->teacher1->id,
            'subject_id' => $this->math->id,
            'subject' => $this->math->name,
            'day_of_week' => 'Monday',
            'start_time' => '08:20',
            'end_time' => '09:00',
        ]);

        $data = [
            'class_id' => $this->class2->id,
            'teacher_id' => $this->teacher1->id,
            'subject_id' => $this->math->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:30',
            'end_time' => '09:10',
        ];

        $this->assertTrue(
            SaveTimetableEntry::teacherIsDoubleBooked($data),
            'Teacher double booking must be detected when teacher is already booked.'
        );
    }

    public function test_generator_service_creates_valid_collision_free_draft(): void
    {
        $generator = app(TimetableGeneratorService::class);

        $draft = $generator->generate($this->institution->id, [
            'scope_type' => 'class',
            'scope_id' => $this->class1->id,
            'draft_name' => 'Unit Test Draft',
            'prefer_morning_high_cognitive' => true,
        ]);

        $this->assertInstanceOf(TimetableDraft::class, $draft);
        $this->assertEquals('draft', $draft->status);
        $this->assertGreaterThan(0, $draft->draftEntries()->count());

        // Verify no internal collisions in draft entries
        $entries = $draft->draftEntries;
        foreach ($entries as $entry) {
            if ($entry->teacher_id) {
                $teacherConflict = TimetableDraftEntry::checkTeacherConflict(
                    $draft->id,
                    $entry->teacher_id,
                    $entry->day_of_week,
                    $entry->start_time->format('H:i'),
                    $entry->end_time->format('H:i'),
                    $entry->id
                );
                $this->assertFalse($teacherConflict, 'No teacher double bookings must exist in generated draft.');
            }

            $classConflict = TimetableDraftEntry::checkClassConflict(
                $draft->id,
                $entry->class_id,
                $entry->day_of_week,
                $entry->start_time->format('H:i'),
                $entry->end_time->format('H:i'),
                $entry->id
            );
            $this->assertFalse($classConflict, 'No class double bookings must exist in generated draft.');
        }

        // Clean up test draft
        $draft->delete();
    }

    public function test_publish_service_preserves_historical_entries_with_lesson_logs(): void
    {
        // Create an existing timetable entry
        $entryWithLesson = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $this->class1->id,
            'teacher_id' => $this->teacher1->id,
            'subject_id' => $this->math->id,
            'subject' => $this->math->name,
            'day_of_week' => 'Tuesday',
            'start_time' => '08:20',
            'end_time' => '09:00',
        ]);

        // Attach a mock lesson to simulate historical attendance record
        $lesson = Lesson::create([
            'institution_id' => $this->institution->id,
            'class_id' => $this->class1->id,
            'timetable_entry_id' => $entryWithLesson->id,
            'teacher_id' => $this->teacher1->id,
            'subject_id' => $this->math->id,
            'lesson_date' => now()->toDateString(),
            'status' => 'attended',
        ]);

        // Create a draft with updated time for the same subject
        $draft = TimetableDraft::create([
            'institution_id' => $this->institution->id,
            'name' => 'Publish Safety Test Draft',
            'status' => 'draft',
            'scope_type' => 'class',
            'scope_id' => $this->class1->id,
        ]);

        $draftEntry = TimetableDraftEntry::create([
            'draft_id' => $draft->id,
            'institution_id' => $this->institution->id,
            'class_id' => $this->class1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '09:00',
            'end_time' => '09:40',
        ]);

        $publisher = app(TimetablePublishService::class);
        $result = $publisher->publish($draft, $this->admin->id);

        $this->assertEquals(1, $result['updated_in_place'], 'Existing entry with lessons must be updated in-place.');
        $this->assertDatabaseHas('lessons', ['id' => $lesson->id]);
        $this->assertDatabaseHas('timetable_entries', [
            'id' => $entryWithLesson->id,
            'start_time' => '09:00:00',
        ]);

        // Clean up
        $lesson->delete();
        $entryWithLesson->delete();
        $draft->delete();
    }
}
