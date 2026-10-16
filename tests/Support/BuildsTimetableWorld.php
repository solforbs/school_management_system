<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Teacher\Models\TeacherDetails;
use Modules\Timetable\Models\SubjectAllocation;
use Modules\Timetable\Models\TimetableDraft;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Models\TimetableStructure;
use Modules\Timetable\Services\TimetableGeneratorService;
use Spatie\Permission\Models\Role;

/**
 * Small, explicit builders for timetable tests. Every test creates exactly
 * the data it needs, so nothing depends on seeders or other tests.
 */
trait BuildsTimetableWorld
{
    protected Institution $institution;

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

    protected function makeTeacher(string $name = 'Teacher', ?Institution $institution = null): User
    {
        $role = Role::firstOrCreate(['name' => 'Teacher', 'guard_name' => 'web']);
        $user = User::create([
            'name' => $name.' '.Str::random(4),
            'email' => Str::random(10).'@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        TeacherDetails::create([
            'teacher_id' => $user->id,
            'institution_id' => ($institution ?? $this->institution)->id,
        ]);

        return $user;
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
            'name' => $name,
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

    /**
     * An unsaved bell schedule built the way the wizard builds it.
     *
     * @param  array<int, string>|null  $days
     * @param  array<int, array<string, mixed>>  $breaks
     */
    protected function structure(int $periods = 4, ?array $days = null, array $breaks = []): TimetableStructure
    {
        return new TimetableStructure([
            'institution_id' => $this->institution->id,
            'name' => 'Test routine',
            'days_of_week' => $days ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'periods_configuration' => TimetableStructure::buildCustomConfiguration([
                'school_start_time' => '08:00',
                'lesson_duration' => 40,
                'daily_periods' => $periods,
                'breaks' => $breaks,
            ]),
        ]);
    }

    protected function savedStructure(int $periods = 4, ?array $days = null, array $breaks = []): TimetableStructure
    {
        $structure = $this->structure($periods, $days, $breaks);
        $structure->save();

        return $structure;
    }

    /**
     * A lesson already on the live timetable (outside the run being generated).
     */
    protected function publishLesson(SchoolClass $class, User $teacher, string $day, string $start, string $end): TimetableEntry
    {
        return TimetableEntry::create([
            'institution_id' => $class->institution_id,
            'class_id' => $class->id,
            'teacher_id' => $teacher->id,
            'subject' => 'Existing lesson',
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function generate(array $options = []): TimetableDraft
    {
        return app(TimetableGeneratorService::class)->generate($this->institution->id, $options);
    }

    /**
     * Draft entries as plain arrays: class, subject, teacher, day, period, notes, locked.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function entriesOf(TimetableDraft $draft): array
    {
        return $draft->draftEntries()->get()->map(fn ($e) => [
            'class_id' => $e->class_id,
            'subject_id' => $e->subject_id,
            'teacher_id' => $e->teacher_id,
            'day' => $e->day_of_week,
            'period' => $e->period_number,
            'start' => $e->start_time->format('H:i'),
            'end' => $e->end_time->format('H:i'),
            'notes' => $e->notes,
            'locked' => $e->is_locked,
        ])->all();
    }

    /**
     * Assert nobody (teacher or class) is in two places at once.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    protected function assertNoClashes(array $entries): void
    {
        $teacherSlots = [];
        $classSlots = [];

        foreach ($entries as $e) {
            $slotKey = $e['day'].'|'.$e['period'];

            $classKey = $e['class_id'].'|'.$slotKey;
            $this->assertArrayNotHasKey($classKey, $classSlots, "class double-booked at {$slotKey}");
            $classSlots[$classKey] = true;

            if ($e['teacher_id']) {
                $teacherKey = $e['teacher_id'].'|'.$slotKey;
                $this->assertArrayNotHasKey($teacherKey, $teacherSlots, "teacher double-booked at {$slotKey}");
                $teacherSlots[$teacherKey] = true;
            }
        }
    }
}
