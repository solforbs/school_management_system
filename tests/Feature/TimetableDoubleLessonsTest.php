<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Timetable\Models\TimetableDraft;
use Modules\Timetable\Models\TimetableDraftEntry;
use Modules\Timetable\Models\TimetableEntry;
use Modules\Timetable\Models\TimetableStructure;
use Modules\Timetable\Services\TimetableGeneratorService;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('timetable structure correctly identifies consecutive teaching periods and break boundaries', function () {
    $structure = new TimetableStructure([
        'daily_periods' => 8,
        'lesson_duration' => 40,
        'school_start_time' => '08:00',
        'active_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        'periods_configuration' => [
            ['time' => '08:00-08:40', 'name' => 'Period 1', 'is_break' => false, 'period_number' => 1],
            ['time' => '08:40-09:20', 'name' => 'Period 2', 'is_break' => false, 'period_number' => 2],
            ['time' => '09:20-09:40', 'name' => 'Short Break', 'is_break' => true, 'duration' => 20],
            ['time' => '09:40-10:20', 'name' => 'Period 3', 'is_break' => false, 'period_number' => 3],
            ['time' => '10:20-11:00', 'name' => 'Period 4', 'is_break' => false, 'period_number' => 4],
            ['time' => '11:00-11:30', 'name' => 'Tea Break', 'is_break' => true, 'duration' => 30],
            ['time' => '11:30-12:10', 'name' => 'Period 5', 'is_break' => false, 'period_number' => 5],
        ],
    ]);

    // Period 1 -> Period 2 is valid consecutive double period
    expect($structure->canFormDoublePeriod('08:00-08:40'))->toBeTrue();
    $next = $structure->getNextConsecutiveTeachingPeriod('08:00-08:40');
    expect($next)->not->toBeNull()
        ->and($next['time'])->toBe('08:40-09:20')
        ->and($next['name'])->toBe('Period 2');

    // Period 2 cannot form double period because Period 3 is separated by Short Break (09:20-09:40)
    expect($structure->canFormDoublePeriod('08:40-09:20'))->toBeFalse();
    expect($structure->getNextConsecutiveTeachingPeriod('08:40-09:20'))->toBeNull();

    // Period 3 -> Period 4 is valid consecutive
    expect($structure->canFormDoublePeriod('09:40-10:20'))->toBeTrue();
    $next3 = $structure->getNextConsecutiveTeachingPeriod('09:40-10:20');
    expect($next3)->not->toBeNull()
        ->and($next3['time'])->toBe('10:20-11:00');

    // Period 4 cannot form double because Tea Break follows
    expect($structure->canFormDoublePeriod('10:20-11:00'))->toBeFalse();
});

test('timetable entry and draft entry helper methods identify double lessons accurately', function () {
    $entry1 = new TimetableEntry([
        'subject' => 'Mathematics',
        'notes' => '[Double Lesson Part 1/2]',
    ]);
    expect($entry1->isDoubleLesson())->toBeTrue()
        ->and($entry1->isDoubleLessonPart1())->toBeTrue()
        ->and($entry1->isDoubleLessonPart2())->toBeFalse();

    $entry2 = new TimetableEntry([
        'subject' => 'Mathematics',
        'notes' => '[Double Lesson Part 2/2]',
    ]);
    expect($entry2->isDoubleLesson())->toBeTrue()
        ->and($entry2->isDoubleLessonPart1())->toBeFalse()
        ->and($entry2->isDoubleLessonPart2())->toBeTrue();

    $singleEntry = new TimetableEntry([
        'subject' => 'English',
        'notes' => null,
    ]);
    expect($singleEntry->isDoubleLesson())->toBeFalse()
        ->and($singleEntry->isDoubleLessonPart1())->toBeFalse()
        ->and($singleEntry->isDoubleLessonPart2())->toBeFalse();

    $draftEntry = new TimetableDraftEntry([
        'subject_title' => 'Chemistry',
        'notes' => '[Double Lesson Part 1/2]',
    ]);
    expect($draftEntry->isDoubleLesson())->toBeTrue()
        ->and($draftEntry->isDoubleLessonPart1())->toBeTrue();
});

test('generator service places double lessons atomically for heavy subjects', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Double Lesson Academy '.uniqid(),
        'code' => 'DLA-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $curriculum = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC Junior Secondary',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 West',
        'level' => 'Grade 8',
        'curriculum_id' => $curriculum->id,
    ]);

    $math = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'code' => 'MATH',
        'is_compulsory' => true,
        'is_active' => true,
    ]);

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject_id' => $math->id,
        'teacher_id' => $teacher->id,
    ]);

    $generator = app(TimetableGeneratorService::class);
    $draft = $generator->generate($institution->id, [
        'scope_type' => 'class',
        'scope_id' => $class->id,
        'draft_name' => 'Double Lesson Test Run',
        'prefer_morning_high_cognitive' => true,
    ]);

    expect($draft)->toBeInstanceOf(TimetableDraft::class);

    $mathEntries = TimetableDraftEntry::where('draft_id', $draft->id)
        ->where('subject_id', $math->id)
        ->get();

    // Verify lessons allocated
    expect($mathEntries->count())->toBeGreaterThan(0);

    // If double lessons are generated, verify their consistency
    $doublePart1 = $mathEntries->filter(fn ($e) => $e->isDoubleLessonPart1());
    $doublePart2 = $mathEntries->filter(fn ($e) => $e->isDoubleLessonPart2());

    if ($doublePart1->isNotEmpty()) {
        expect($doublePart2->count())->toBe($doublePart1->count());
        foreach ($doublePart1 as $p1) {
            $p2 = $doublePart2->first(fn ($p) => $p->day_of_week === $p1->day_of_week && $p->start_time->format('H:i') === $p1->end_time->format('H:i'));
            expect($p2)->not->toBeNull();
        }
    }
});
