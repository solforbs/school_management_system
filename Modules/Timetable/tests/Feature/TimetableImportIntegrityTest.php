<?php

namespace Modules\Timetable\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Lesson\Models\Lesson;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Services\TimetableImportService;
use Tests\Support\BuildsTimetableWorld;
use Tests\TestCase;

class TimetableImportIntegrityTest extends TestCase
{
    use BuildsTimetableWorld;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = $this->makeInstitution();
    }

    public function test_import_preserves_existing_entries_with_recorded_lessons_in_place(): void
    {
        $class = $this->makeClass('Grade 7 Gold');
        $teacher = $this->makeTeacher('Prof Hawking');
        $this->makeSubject('Mathematics');
        $this->makeSubject('Science');

        // Existing entry 1 with recorded student attendance
        $entryWithLesson = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'class_name' => $class->name,
            'subject' => 'Mathematics',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
        ]);

        $lesson = Lesson::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'timetable_entry_id' => $entryWithLesson->id,
            'status' => 'attended',
            'lesson_date' => now()->toDateString(),
        ]);

        // Existing entry 2 without any lessons (stale slot)
        $staleEntry = TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'class_name' => $class->name,
            'subject' => 'Old Topic',
            'day_of_week' => 'Tuesday',
            'start_time' => '08:00',
            'end_time' => '08:40',
        ]);

        // Incoming CSV modifies Monday 08:00 to Science and adds Wednesday 08:00
        $csvContent = "Day,Start Time,End Time,Subject,Teacher Email,Room,Notes\n"
            ."Monday,08:00,08:40,Science,{$teacher->email},Lab 1,[Updated Lesson]\n"
            ."Wednesday,08:00,08:40,Mathematics,{$teacher->email},Room 10,\n";

        $file = UploadedFile::fake()->createWithContent('timetable.csv', $csvContent);
        $result = app(TimetableImportService::class)->import($file, $class);

        $this->assertEmpty($result['errors']);
        $this->assertSame(2, $result['created']);

        // Assert historical entry was preserved in place with same primary key
        $this->assertDatabaseHas('timetable_entries', [
            'id' => $entryWithLesson->id,
            'subject' => 'Science',
            'room' => 'Lab 1',
        ]);

        // Assert attendance log is intact and points to same entry ID
        $this->assertSame($entryWithLesson->id, $lesson->fresh()->timetable_entry_id);

        // Assert stale unattended entry was cleanly removed
        $this->assertDatabaseMissing('timetable_entries', [
            'id' => $staleEntry->id,
        ]);

        // Assert new Wednesday entry was inserted
        $this->assertDatabaseHas('timetable_entries', [
            'class_id' => $class->id,
            'day_of_week' => 'Wednesday',
            'subject' => 'Mathematics',
        ]);
    }

    public function test_import_rejects_teacher_conflict_across_classes(): void
    {
        $classA = $this->makeClass('Grade 7 A');
        $classB = $this->makeClass('Grade 7 B');
        $teacher = $this->makeTeacher('Shared Teacher');
        $this->makeSubject('Mathematics');

        // Teacher is already booked for Class A on Monday 08:00-08:40
        TimetableEntry::create([
            'institution_id' => $this->institution->id,
            'class_id' => $classA->id,
            'teacher_id' => $teacher->id,
            'class_name' => $classA->name,
            'subject' => 'Mathematics',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '08:40',
        ]);

        // Attempting to import Class B with Teacher on same slot
        $csvContent = "Day,Start Time,End Time,Subject,Teacher Email,Room\n"
            ."Monday,08:00,08:40,Mathematics,{$teacher->email},Room 2\n";

        $file = UploadedFile::fake()->createWithContent('timetable.csv', $csvContent);
        $result = app(TimetableImportService::class)->import($file, $classB);

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('teacher is already scheduled', $result['errors'][0]);
    }
}
