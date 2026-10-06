<?php

namespace Modules\Timetable\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;
use Modules\Teacher\Models\TeacherDetails;
use Modules\Timetable\Models\TimetableEntry;

class TimetableDatabaseSeeder extends Seeder
{
    /**
     * Teaching periods, Monday-Friday, running 8am-4pm. A short break
     * follows period 3 and lunch follows period 6 - neither gets a
     * timetable entry.
     */
    private const PERIODS = [
        ['08:00', '08:40'],
        ['08:40', '09:20'],
        ['09:20', '10:00'],
        ['10:20', '11:00'],
        ['11:00', '11:40'],
        ['11:40', '12:20'],
        ['13:20', '14:00'],
        ['14:00', '14:40'],
        ['14:40', '15:20'],
        ['15:20', '16:00'],
    ];

    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    /**
     * The full standard 8-4-4 secondary curriculum, weighted by periods/week
     * so the generated week reads like a real school (Maths/English daily,
     * niche electives once a week, etc). Weights sum to 50 = 10 periods x 5
     * days, so every slot in every class's week gets filled.
     */
    private const SUBJECTS = [
        'Mathematics' => 5,
        'English' => 5,
        'Kiswahili' => 4,
        'Biology' => 3,
        'Chemistry' => 3,
        'Physics' => 3,
        'History and Government' => 3,
        'Geography' => 3,
        'Christian Religious Education' => 2,
        'Islamic Religious Education' => 1,
        'Business Studies' => 2,
        'Agriculture' => 2,
        'Computer Studies' => 2,
        'Home Science' => 1,
        'Art and Design' => 1,
        'Woodwork' => 1,
        'Metalwork' => 1,
        'Building Construction' => 1,
        'French' => 1,
        'German' => 1,
        'Music' => 1,
        'Physical Education' => 2,
        'Life Skills Education' => 2,
    ];

    /** Marks teachers this seeder created, so re-runs can clean up and regenerate. */
    private const GENERATED_EMAIL_DOMAIN = '@school.test';

    /** @var array<string, int[]> subject => pool of teacher user IDs qualified for it */
    private array $teacherPool = [];

    /** @var array<string, true> "{teacherId}|{day}|{start}" slots already booked, across all classes */
    private array $busy = [];

    private int $teacherSequence = 0;

    /** @var array<int, array<string, int>> institution_id => [subject name => subject id] */
    private array $subjectIds = [];

    public function run(): void
    {
        $demoInstitution = Institution::where('code', 'GVA001')->first();
        if (! $demoInstitution) {
            return;
        }

        $classes = SchoolClass::where('institution_id', $demoInstitution->id)->orderBy('name')->get();

        if ($classes->isEmpty()) {
            return;
        }

        // Wipe only demo class entries and demo teachers
        TimetableEntry::whereIn('class_id', $classes->pluck('id'))->delete();
        User::where('email', 'like', '%'.self::GENERATED_EMAIL_DOMAIN)
            ->whereHas('teacherUserDetails', fn ($q) => $q->where('institution_id', $demoInstitution->id))
            ->delete();

        // Put any already-existing teacher to use as a Mathematics teacher
        if ($existingTeacher = User::role('Teacher')->whereHas('teacherUserDetails', fn ($q) => $q->where('institution_id', $demoInstitution->id))->first()) {
            $this->teacherPool['Mathematics'] = [$existingTeacher->id];
        }

        foreach ($classes as $schoolClass) {
            foreach ($this->buildWeeklySlots() as [$day, $period, $subject]) {
                [$start, $end] = $period;

                $teacherId = $this->teacherFor($subject, $day, $start, $schoolClass->institution_id);

                TimetableEntry::create([
                    'institution_id' => $schoolClass->institution_id,
                    'class_id' => $schoolClass->id,
                    'teacher_id' => $teacherId,
                    'class_name' => $schoolClass->name,
                    'subject' => $subject,
                    'subject_id' => $this->subjectIdFor($subject, $schoolClass->institution_id),
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                ]);
            }
        }
    }

    /**
     * A shuffled Day x Period grid for one class, each slot paired with a
     * subject drawn from the weighted weekly subject list.
     *
     * @return array<int, array{0: string, 1: array{0: string, 1: string}, 2: string}>
     */
    private function buildWeeklySlots(): array
    {
        $subjectBag = [];
        foreach (self::SUBJECTS as $subject => $periodsPerWeek) {
            array_push($subjectBag, ...array_fill(0, $periodsPerWeek, $subject));
        }
        shuffle($subjectBag);

        $slots = [];

        foreach (self::DAYS as $day) {
            $previousSubject = null;

            foreach (self::PERIODS as $period) {
                $index = $this->pickSubjectIndex($subjectBag, $previousSubject);
                $subject = $subjectBag[$index];
                unset($subjectBag[$index]);
                $subjectBag = array_values($subjectBag);

                $slots[] = [$day, $period, $subject];
                $previousSubject = $subject;
            }
        }

        return $slots;
    }

    /**
     * Pick a subject from the remaining bag that isn't the same as the
     * period right before it, so a class doesn't get the same subject
     * back-to-back. Falls back to whatever's left if that's genuinely
     * unavoidable (e.g. only one subject remains in the bag).
     *
     * @param  string[]  $bag
     */
    private function pickSubjectIndex(array $bag, ?string $previousSubject): int
    {
        $candidates = array_keys(array_filter($bag, fn ($subject) => $subject !== $previousSubject));

        if (empty($candidates)) {
            return array_key_first($bag);
        }

        return $candidates[array_rand($candidates)];
    }

    /**
     * The catalog Subject record for this name in this institution, if one
     * exists (Subject is a separate, institution-scoped catalog - not every
     * free-text subject name used here is guaranteed to have a match).
     */
    private function subjectIdFor(string $subject, int $institutionId): ?int
    {
        if (! isset($this->subjectIds[$institutionId])) {
            $this->subjectIds[$institutionId] = Subject::where('institution_id', $institutionId)
                ->pluck('id', 'name')
                ->all();
        }

        return $this->subjectIds[$institutionId][$subject] ?? null;
    }

    /**
     * Find a teacher qualified for this subject who is free at this
     * day/time, so no teacher ever ends up double-booked across classes.
     * Mints a new teacher for the subject when the existing pool is
     * exhausted at this slot.
     */
    private function teacherFor(string $subject, string $day, string $start, int $institutionId): int
    {
        $this->teacherPool[$subject] ??= [];

        foreach ($this->teacherPool[$subject] as $teacherId) {
            $key = "{$teacherId}|{$day}|{$start}";

            if (! isset($this->busy[$key])) {
                $this->busy[$key] = true;

                return $teacherId;
            }
        }

        $teacherId = $this->createTeacher($subject, $institutionId);
        $this->teacherPool[$subject][] = $teacherId;
        $this->busy["{$teacherId}|{$day}|{$start}"] = true;

        return $teacherId;
    }

    private function createTeacher(string $subject, int $institutionId): int
    {
        $this->teacherSequence++;

        $teacher = User::create([
            'name' => $subject.' Teacher '.$this->teacherSequence,
            'email' => 'teacher.'.Str::slug($subject).'.'.$this->teacherSequence.self::GENERATED_EMAIL_DOMAIN,
            'password' => Hash::make('password123'),
        ]);
        $teacher->syncRoles('Teacher');

        TeacherDetails::create([
            'teacher_id' => $teacher->id,
            'institution_id' => $institutionId,
            'department' => $subject,
            'status' => 'active',
            'is_active' => true,
        ]);

        return $teacher->id;
    }
}
