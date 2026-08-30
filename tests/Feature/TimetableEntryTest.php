<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;
use Modules\Timetable\Models\TimetableEntry;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeTimetableEntrySchool(): array
{
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Timetable Academy '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $director->update(['active_institution_id' => $institution->id]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Form 1 East',
    ]);

    $subject = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'is_active' => true,
    ]);

    return [$director->refresh(), $institution, $class, $subject];
}

test('a director creates a timetable entry', function () {
    [$director, $institution, $class, $subject] = makeTimetableEntrySchool();

    $this->actingAs($director)->post(route('timetable.store'), [
        'class_id' => $class->id,
        'subject' => $subject->name,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '08:45',
        'room' => 'Room 101',
    ])->assertRedirect(route('timetable.index'));

    expect(TimetableEntry::count())->toBe(1)
        ->and(TimetableEntry::first()->class_id)->toBe($class->id)
        ->and(TimetableEntry::first()->subject)->toBe('Mathematics')
        ->and(TimetableEntry::first()->institution_id)->toBe($institution->id);
});

test('double booking the same teacher in the same time slot is rejected', function () {
    [$director, $institution, $class, $subject] = makeTimetableEntrySchool();

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    $class2 = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Form 2 West',
    ]);

    TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'subject' => 'Mathematics',
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    $this->actingAs($director)->post(route('timetable.store'), [
        'class_id' => $class2->id,
        'teacher_id' => $teacher->id,
        'subject' => 'Physics',
        'day_of_week' => 'Monday',
        'start_time' => '08:30',
        'end_time' => '09:30',
    ])->assertSessionHas('error');

    expect(TimetableEntry::count())->toBe(1);
});

test('a timetable can be imported from a csv file', function () {
    [$director, $institution, $class] = makeTimetableEntrySchool();

    $csvContent = "Day,Start Time,End Time,Subject,Teacher Email,Room\n".
        "Monday,08:00,08:45,English,,Room 1\n".
        "Monday,08:45,09:30,Mathematics,,Room 1\n".
        "Monday,09:30,10:00,Break,,\n";

    $file = UploadedFile::fake()->createWithContent('timetable.csv', $csvContent);

    $this->actingAs($director)->post(route('timetable.import.store'), [
        'class_id' => $class->id,
        'file' => $file,
    ])->assertRedirect(route('timetable.index', ['class_id' => $class->id]));

    expect(TimetableEntry::where('class_id', $class->id)->count())->toBe(2);
});

test('the livewire timetable index renders weekly grid for a class', function () {
    [$director, $institution, $class, $subject] = makeTimetableEntrySchool();

    TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject' => 'Mathematics',
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '08:45',
        'room' => 'Lab 1',
    ]);

    Livewire::actingAs($director)
        ->test('timetable::index')
        ->set('classId', (string) $class->id)
        ->assertSee('Mathematics')
        ->assertSee('Lab 1');
});
