<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Services\MisfiledAttendanceFinder;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $director = User::factory()->create();
    $this->institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Audit Primary',
        'code' => 'AUD-'.uniqid(),
        'type' => 'School',
        'is_approved' => true,
    ]);
    $this->blue = SchoolClass::create(['institution_id' => $this->institution->id, 'name' => 'Grade 4 Blue']);
    $this->red = SchoolClass::create(['institution_id' => $this->institution->id, 'name' => 'Grade 4 Red']);

    $this->learnerIn = function (SchoolClass $class, string $name): User {
        $learner = User::factory()->create(['name' => $name]);
        StudentDetails::create([
            'student_id' => $learner->id,
            'class_id' => $class->id,
            'institution_id' => $this->institution->id,
            'admission_number' => 'AUD-'.$learner->id,
            'is_active' => true,
        ]);

        return $learner;
    };

    $this->mark = fn (SchoolClass $class, User $learner, string $date) => Attendance::create([
        'institution_id' => $this->institution->id,
        'school_class_id' => $class->id,
        'student_id' => $learner->id,
        'date' => $date,
        'status' => 'present',
        'source' => 'manual',
    ]);
});

test('a learner recorded in two classes on the same day is flagged, with the wrong row identified', function () {
    $amina = ($this->learnerIn)($this->blue, 'Amina');

    ($this->mark)($this->blue, $amina, '2026-11-02');
    $wrong = ($this->mark)($this->red, $amina, '2026-11-02');

    $found = app(MisfiledAttendanceFinder::class)->find($this->institution->id);

    expect($found['same_day_conflicts'])->toHaveCount(1);
    $conflict = $found['same_day_conflicts']->first();
    expect($conflict['current_class_id'])->toBe($this->blue->id)
        ->and($conflict['rows']->pluck('id')->all())->toContain($wrong->id)
        ->and($found['other_class_rows'])->toBeEmpty();
});

test('a single row under another class is reported separately as a possible transfer', function () {
    $brian = ($this->learnerIn)($this->blue, 'Brian');
    $old = ($this->mark)($this->red, $brian, '2026-10-01');

    $found = app(MisfiledAttendanceFinder::class)->find($this->institution->id);

    expect($found['same_day_conflicts'])->toBeEmpty()
        ->and($found['other_class_rows']->pluck('id')->all())->toBe([$old->id]);
});

test('correct attendance is not reported and the audit changes nothing', function () {
    $carol = ($this->learnerIn)($this->blue, 'Carol');
    ($this->mark)($this->blue, $carol, '2026-11-02');
    ($this->mark)($this->blue, $carol, '2026-11-03');

    $found = app(MisfiledAttendanceFinder::class)->find($this->institution->id);

    expect($found['same_day_conflicts'])->toBeEmpty()
        ->and($found['other_class_rows'])->toBeEmpty();

    $this->artisan('attendance:audit-misfiled', ['--institution' => $this->institution->id])
        ->expectsOutputToContain('no attendance has been changed')
        ->assertSuccessful();

    expect(Attendance::count())->toBe(2);
});
