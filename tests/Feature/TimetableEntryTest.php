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
        'subject_id' => $subject->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '08:45',
        'room' => 'Room 101',
    ])->assertRedirect(route('timetable.index'));

    expect(TimetableEntry::count())->toBe(1)
        ->and(TimetableEntry::first()->class_id)->toBe($class->id)
        ->and(TimetableEntry::first()->subject_id)->toBe($subject->id)
        // The name is denormalised alongside the id for anything still
        // reading the legacy free-text column.
        ->and(TimetableEntry::first()->subject)->toBe('Mathematics')
        ->and(TimetableEntry::first()->institution_id)->toBe($institution->id);
});

test('a subject from another school cannot be scheduled', function () {
    [$director, , $class] = makeTimetableEntrySchool();
    [, , , $theirSubject] = makeTimetableEntrySchool();

    // 'exists:subjects,id' alone would let a crafted request put another
    // school's subject on this school's timetable.
    $this->actingAs($director)->post(route('timetable.store'), [
        'class_id' => $class->id,
        'subject_id' => $theirSubject->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '08:45',
    ])->assertForbidden();

    expect(TimetableEntry::count())->toBe(0);
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
        'subject_id' => $subject->id,
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

test('a director can delete an individual timetable slot via livewire', function () {
    [$director, $institution, $class] = makeTimetableEntrySchool();

    $entry = TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject' => 'Science',
        'day_of_week' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '09:45',
    ]);

    expect(TimetableEntry::where('id', $entry->id)->exists())->toBeTrue();

    Livewire::actingAs($director)
        ->test('timetable::index')
        ->set('classId', (string) $class->id)
        ->call('deleteEntry', $entry->id)
        ->assertHasNoErrors();

    expect(TimetableEntry::where('id', $entry->id)->exists())->toBeFalse();
});

test('a director can completely clear a class timetable via livewire', function () {
    [$director, $institution, $class] = makeTimetableEntrySchool();

    TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject' => 'English',
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '08:45',
    ]);

    TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'subject' => 'Chemistry',
        'day_of_week' => 'Tuesday',
        'start_time' => '08:45',
        'end_time' => '09:30',
    ]);

    expect(TimetableEntry::where('class_id', $class->id)->count())->toBe(2);

    Livewire::actingAs($director)
        ->test('timetable::index')
        ->set('classId', (string) $class->id)
        ->call('clearClassTimetable')
        ->assertHasNoErrors();

    expect(TimetableEntry::where('class_id', $class->id)->count())->toBe(0);
});

test('a user can view the printable noticeboard timetable with identical weekly grid UI', function () {
    [$director, $institution, $class, $subject] = makeTimetableEntrySchool();

    $teacher = User::factory()->create(['name' => 'Mr. Kamau']);
    $teacher->assignRole('Teacher');

    TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class->id,
        'teacher_id' => $teacher->id,
        'subject' => 'Mathematics',
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '08:40',
        'room' => 'Lab 2',
        'notes' => '[Double Lesson Part 1/2]',
    ]);

    $response = $this->actingAs($director)->get(route('timetable.print', ['class_id' => $class->id]));

    $response->assertOk()
        ->assertViewIs('timetable::print')
        ->assertSee($institution->name)
        ->assertSee($class->name)
        ->assertSee('Mathematics')
        ->assertSee('Mr. Kamau')
        ->assertSee('Lab 2')
        ->assertSee('NOTICE BOARD COPY')
        ->assertSee('Class Teacher')
        ->assertSee('Principal / Headteacher Stamp');
});

test('csv import merges target class schedule without affecting other classes and preserves notes', function () {
    [$director, $institution, $class1] = makeTimetableEntrySchool();

    $class2 = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Form 2 West',
    ]);

    // Populate Class 2 with entries that must NOT be affected
    TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class2->id,
        'subject' => 'History',
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '08:45',
    ]);
    TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class2->id,
        'subject' => 'Geography',
        'day_of_week' => 'Tuesday',
        'start_time' => '09:00',
        'end_time' => '09:45',
    ]);

    // Populate Class 1 with old entries that WILL be replaced
    TimetableEntry::create([
        'institution_id' => $institution->id,
        'class_id' => $class1->id,
        'subject' => 'Old Subject',
        'day_of_week' => 'Wednesday',
        'start_time' => '10:00',
        'end_time' => '10:45',
    ]);

    $csvContent = "Day,Start Time,End Time,Subject,Teacher Email,Room,Notes\n".
        "Monday,08:00,08:40,Physics,,Lab 1,[Double Lesson Part 1/2]\n".
        "Monday,08:40,09:20,Physics,,Lab 1,[Double Lesson Part 2/2]\n".
        "Monday,09:20,09:50,Tea Break,,,\n".
        "Monday,09:50,10:30,Chemistry,,Lab 2,\n";

    $file = UploadedFile::fake()->createWithContent('form1_timetable.csv', $csvContent);

    $response = $this->actingAs($director)->post(route('timetable.import.store'), [
        'class_id' => $class1->id,
        'file' => $file,
    ]);

    $response->assertRedirect(route('timetable.index', ['class_id' => $class1->id]));

    // Class 1 has exactly 3 lessons (Tea Break was skipped as non-teaching break)
    $class1Entries = TimetableEntry::where('class_id', $class1->id)->get();
    expect($class1Entries->count())->toBe(3)
        ->and($class1Entries->pluck('subject')->all())->toContain('Physics', 'Chemistry')
        ->and($class1Entries->where('subject', 'Physics')->first()->notes)->toBe('[Double Lesson Part 1/2]');

    // Old subject in Class 1 is gone
    expect(TimetableEntry::where('class_id', $class1->id)->where('subject', 'Old Subject')->exists())->toBeFalse();

    // Class 2 entries remain completely untouched!
    $class2Entries = TimetableEntry::where('class_id', $class2->id)->get();
    expect($class2Entries->count())->toBe(2)
        ->and($class2Entries->pluck('subject')->all())->toBe(['History', 'Geography']);
});
