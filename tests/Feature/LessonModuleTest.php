<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Lesson\Models\Lesson;
use Modules\Lesson\Models\LessonReport;
use Modules\Subject\Models\Subject;
use Modules\Timetable\Models\TimetableEntry;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('public');
});

function makeLessonSchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Lesson Academy '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 Alpha',
    ]);

    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Science',
        'is_active' => true,
    ]);

    $entry = TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject' => $subject->name,
        'day_of_week' => Carbon::now()->format('l'),
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    return [$director->refresh(), $institution, $class, $entry];
}

test('a director marks and bulk stores daily lesson attendance', function () {
    [$director, $institution, $class, $entry] = makeLessonSchool();
    $today = Carbon::today()->toDateString();

    $this->actingAs($director)->post(route('lesson.store'), [
        'class_id' => $class->id,
        'date' => $today,
        'statuses' => [
            [
                'timetable_entry_id' => $entry->id,
                'status' => 'attended',
                'remarks' => 'Completed topic on Photosynthesis',
            ],
        ],
    ])->assertRedirect(route('lesson.index', ['class_id' => $class->id, 'date' => $today]));

    expect(Lesson::count())->toBe(1);
    $lesson = Lesson::first();
    expect($lesson->class_id)->toBe($class->id)
        ->and($lesson->timetable_entry_id)->toBe($entry->id)
        ->and($lesson->status)->toBe('attended')
        ->and($lesson->remarks)->toBe('Completed topic on Photosynthesis')
        ->and($lesson->marked_by)->toBe($director->id);
});

test('a director updates and deletes a single lesson attendance record', function () {
    [$director, $institution, $class, $entry] = makeLessonSchool();
    $today = Carbon::today()->toDateString();

    $lesson = Lesson::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'timetable_entry_id' => $entry->id,
        'lesson_date' => $today,
        'status' => 'not_attended',
        'marked_by' => $director->id,
    ]);

    $this->actingAs($director)->put(route('lesson.update', $lesson->id), [
        'status' => 'recovered',
        'remarks' => 'Recovered during free slot',
    ])->assertRedirect(route('lesson.show', $lesson->id));

    expect($lesson->refresh()->status)->toBe('recovered')
        ->and($lesson->remarks)->toBe('Recovered during free slot');

    $this->actingAs($director)->delete(route('lesson.destroy', $lesson->id))
        ->assertRedirect();

    expect(Lesson::count())->toBe(0);
});

test('generating a lesson report aggregates attendance statistics', function () {
    [$director, $institution, $class, $entry] = makeLessonSchool();
    $today = Carbon::today()->toDateString();

    Lesson::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'timetable_entry_id' => $entry->id,
        'lesson_date' => $today,
        'status' => 'attended',
        'marked_by' => $director->id,
    ]);

    $this->actingAs($director)->post(route('lesson.reports.generate'), [
        'class_id' => $class->id,
        'type' => 'daily',
        'date' => $today,
    ])->assertRedirect();

    expect(LessonReport::count())->toBe(1);
    $report = LessonReport::first();
    expect($report->class_id)->toBe($class->id)
        ->and($report->type)->toBe('daily')
        ->and($report->attended_count)->toBe(1);
});

test('livewire lesson attendance marks periods in place', function () {
    [$director, $institution, $class, $entry] = makeLessonSchool();
    $today = Carbon::today()->toDateString();

    Livewire::actingAs($director)
        ->test('lesson::index', ['class_id' => (string) $class->id, 'date' => $today])
        ->set("statuses.{$entry->id}", 'attended')
        ->set("remarks.{$entry->id}", 'All practicals completed')
        ->call('save')
        ->assertHasNoErrors();

    expect(Lesson::count())->toBe(1)
        ->and(Lesson::first()->status)->toBe('attended');
});
