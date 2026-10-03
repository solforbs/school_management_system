<?php

use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Modules\Attendance\Models\Attendance;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Subject\Models\SubjectTeacher;
use Modules\Teacher\Models\TeacherDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('teacher analytics reports manual roll-call rate and reports biometric status accurately', function () {
    $director = User::factory()->create();
    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Green Valley Academy',
        'code' => 'GVA-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $curriculum = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    $teacher = User::factory()->create(['name' => 'John Mwangi']);
    $teacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    $homeroomClass = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 East',
        'curriculum_id' => $curriculum->id,
        'class_teacher_id' => $teacher->id,
    ]);

    $math = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'code' => 'MTH',
    ]);

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $homeroomClass->id,
        'subject_id' => $math->id,
        'teacher_id' => $teacher->id,
    ]);

    // Create 8 students: 7 present, 1 absent (approx 88% / 87.5% manual rate)
    $students = [];
    for ($i = 0; $i < 8; $i++) {
        $u = User::factory()->create(['name' => "Student {$i}"]);
        $details = StudentDetails::create([
            'student_id' => $u->id,
            'institution_id' => $institution->id,
            'class_id' => $homeroomClass->id,
            'admission_number' => "ADM-00{$i}",
            'gender' => $i % 2 === 0 ? 'male' : 'female',
            'guardian_name' => "Parent of Student {$i}",
            'guardian_phone' => '+25471234567'.$i,
            'guardian_email' => "parent{$i}@example.com",
        ]);
        $students[] = ['user' => $u, 'details' => $details];
    }

    $todayDate = today()->toDateString();

    // Mark 7 present and 1 absent for today
    for ($i = 0; $i < 7; $i++) {
        Attendance::create([
            'institution_id' => $institution->id,
            'school_class_id' => $homeroomClass->id,
            'student_id' => $students[$i]['user']->id,
            'date' => $todayDate,
            'status' => 'present',
            'source' => 'manual',
            'recorded_by' => $teacher->id,
        ]);
    }

    $absentRecord = Attendance::create([
        'institution_id' => $institution->id,
        'school_class_id' => $homeroomClass->id,
        'student_id' => $students[7]['user']->id,
        'date' => $todayDate,
        'status' => 'absent',
        'source' => 'manual',
        'recorded_by' => $teacher->id,
        'parent_notified' => false,
        'notes' => 'Fever and cold',
    ]);

    // No biometric device logs created yet -> biometrics count is 0
    $analyticsService = app(AnalyticsService::class);
    $stats = $analyticsService->forUser($teacher);

    // Verify roll-call rate is 88% (7 out of 8 present)
    expect($stats['rollcall_completed'])->toBeTrue()
        ->and($stats['rollcall_total_marked'])->toBe(8)
        ->and($stats['rollcall_present_count'])->toBe(7)
        ->and($stats['rollcall_absent_count'])->toBe(1)
        ->and($stats['rollcall_attendance_rate'])->toBe(88)
        ->and($stats['biometric_scans_today_count'])->toBe(0)
        ->and($stats['biometric_status_text'])->toBe('Biometric check-in not done today');

    // Verify absentee details are properly populated
    expect($stats['absent_students_today'])->toHaveCount(1)
        ->and($stats['absent_students_today']->first()['student_name'])->toBe('Student 7')
        ->and($stats['absent_students_today']->first()['parent_phone'])->toBe('+254712345677')
        ->and($stats['absent_students_today']->first()['parent_notified'])->toBeFalse()
        ->and($stats['absent_students_today']->first()['notes'])->toBe('Fever and cold');

    // Verify weekly trend has 5 weekdays
    expect($stats['weekly_attendance_trend'])->toHaveCount(5);

    // Verify Livewire Reports component renders Teacher Heading and Roll-Call vs Biometrics status
    $this->actingAs($teacher);
    Livewire::test('report::index')
        ->assertSee('Classroom & Academic Analytics')
        ->assertSee('88%')
        ->assertSee('Biometric check-in not done today')
        ->assertSee('Student 7')
        ->assertSee('+254712345677');
});

test('teacher analytics shows pending roll-call state when no register is recorded', function () {
    $director = User::factory()->create();
    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Solforbs High School',
        'code' => 'SHS-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $curriculum = Curriculum::create([
        'institution_id' => $institution->id,
        'name' => 'CBC',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    $teacher = User::factory()->create(['name' => 'Jane Doe']);
    $teacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    $class = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 West',
        'curriculum_id' => $curriculum->id,
        'class_teacher_id' => $teacher->id,
    ]);

    $this->actingAs($teacher);

    $analyticsService = app(AnalyticsService::class);
    $stats = $analyticsService->forUser($teacher);

    expect($stats['rollcall_completed'])->toBeFalse()
        ->and($stats['rollcall_attendance_rate'])->toBeNull()
        ->and($stats['biometric_scans_today_count'])->toBe(0)
        ->and($stats['absent_students_today'])->toBeEmpty();

    Livewire::test('report::index')
        ->assertSee('Classroom & Academic Analytics')
        ->assertSee('Roll-call pending today')
        ->assertSee('Biometric check-in not done');
});
