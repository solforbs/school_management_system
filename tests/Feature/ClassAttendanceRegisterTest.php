<?php

use App\Models\Devices;
use App\Models\User;
use App\Services\SmsService;
use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Enums\VerifyMode;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;
use Carbon\Carbon;
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

    $this->director = User::factory()->create();
    $this->director->assignRole('Director');
    $this->institution = Institution::create([
        'user_id' => $this->director->id,
        'name' => 'St. Kizito Primary',
        'code' => 'SKP-'.uniqid(),
        'type' => 'Primary',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $this->director->update(['active_institution_id' => $this->institution->id]);

    $this->curriculum = Curriculum::create([
        'institution_id' => $this->institution->id,
        'name' => 'CBC Primary',
        'system' => 'cbc',
        'status' => 'active',
    ]);

    $this->teacher = User::factory()->create();
    $this->teacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $this->teacher->id,
        'institution_id' => $this->institution->id,
        'is_active' => true,
    ]);

    $this->schoolClass = SchoolClass::create([
        'institution_id' => $this->institution->id,
        'name' => 'Grade 6 Blue',
        'curriculum_id' => $this->curriculum->id,
        'class_teacher_id' => $this->teacher->id,
    ]);

    // Create 3 learners
    $this->student1 = User::factory()->create(['name' => 'Achieng Mary']);
    StudentDetails::create([
        'student_id' => $this->student1->id,
        'class_id' => $this->schoolClass->id,
        'institution_id' => $this->institution->id,
        'admission_number' => 'ADM-001',
        'is_active' => true,
    ]);

    $this->student2 = User::factory()->create(['name' => 'Barasa Collins']);
    StudentDetails::create([
        'student_id' => $this->student2->id,
        'class_id' => $this->schoolClass->id,
        'institution_id' => $this->institution->id,
        'admission_number' => 'ADM-002',
        'is_active' => true,
    ]);

    $this->student3 = User::factory()->create(['name' => 'Mutiso Peter']);
    StudentDetails::create([
        'student_id' => $this->student3->id,
        'class_id' => $this->schoolClass->id,
        'institution_id' => $this->institution->id,
        'admission_number' => 'ADM-003',
        'is_active' => true,
    ]);
});

test('teacher can view class attendance register and renders student list', function () {
    $this->actingAs($this->teacher);

    Livewire::test('attendance::register', ['classId' => $this->schoolClass->id])
        ->assertSee('Attendance')
        ->assertSee('Grade 6 Blue')
        ->assertSee('Achieng Mary')
        ->assertSee('Barasa Collins')
        ->assertSee('Mutiso Peter')
        ->assertSee('Mark all present')
        ->assertSee('Clear all');
});

test('biometric gate scans automatically pre-tick student present with gate scan badge', function () {
    $zkDevice = ZktecoDevice::create([
        'serial_number' => 'SN-'.uniqid(),
        'name' => 'Main Gate Terminal',
        'ip_address' => '192.168.1.100',
    ]);

    $device = Devices::create([
        'institution_id' => $this->institution->id,
        'serial_number' => $zkDevice->serial_number,
        'zkteco_device_id' => $zkDevice->id,
        'is_active' => true,
    ]);

    // Student 1 scanned at 07:35 AM today
    $today = Carbon::today();
    ZktecoAttendanceLog::create([
        'device_id' => $zkDevice->id,
        'pin' => (string) $this->student1->id,
        'recorded_at' => $today->copy()->setTime(7, 35),
        'occurred_at' => $today->copy()->setTime(7, 35),
        'status' => 0, // CheckIn
        'verify_mode' => 1,
    ]);

    $this->actingAs($this->teacher);

    $component = Livewire::test('attendance::register', ['classId' => $this->schoolClass->id]);

    // Student 1 should be auto-ticked present
    expect($component->get('statuses')[$this->student1->id])->toBe('present');
    // Student 2 and 3 have no scan, so they should be null/unmarked
    expect($component->get('statuses')[$this->student2->id])->toBeNull();
    expect($component->get('statuses')[$this->student3->id])->toBeNull();

    // Live KPI counters should reflect 1 present, 0 absent, rate 33%
    expect($component->get('presentCount'))->toBe(1);
    expect($component->get('absentCount'))->toBe(0);
    expect($component->get('attendanceRate'))->toBe(33);
});

test('teacher can mark all present with single click', function () {
    $this->actingAs($this->teacher);

    Livewire::test('attendance::register', ['classId' => $this->schoolClass->id])
        ->call('markAllPresent')
        ->assertSet('statuses.'.$this->student1->id, 'present')
        ->assertSet('statuses.'.$this->student2->id, 'present')
        ->assertSet('statuses.'.$this->student3->id, 'present');
});

test('teacher can mark student absent, toggle parent notified, and save records', function () {
    $this->actingAs($this->teacher);

    Livewire::test('attendance::register', ['classId' => $this->schoolClass->id])
        ->call('markStatus', $this->student1->id, 'present')
        ->call('markStatus', $this->student2->id, 'present')
        ->call('markStatus', $this->student3->id, 'absent')
        ->call('toggleParentNotified', $this->student3->id)
        ->set('notes', 'Heavy morning rainfall.')
        ->call('saveAttendance');

    // Verify records exist in database
    $att1 = Attendance::where('student_id', $this->student1->id)->where('date', today())->first();
    expect($att1)->not->toBeNull();
    expect($att1->status)->toBe('present');
    expect($att1->source)->toBe('manual');

    $att3 = Attendance::where('student_id', $this->student3->id)->where('date', today())->first();
    expect($att3)->not->toBeNull();
    expect($att3->status)->toBe('absent');
    expect($att3->parent_notified)->toBeTrue();
    expect($att3->notes)->toBe('Heavy morning rainfall.');
    expect($att3->recorded_by)->toBe($this->teacher->id);
});

test('teacher override: student scanned at gate but marked absent by teacher receives override source', function () {
    $zkDevice = ZktecoDevice::create([
        'serial_number' => 'SN-'.uniqid(),
        'name' => 'Main Gate Terminal',
        'ip_address' => '192.168.1.101',
    ]);

    Devices::create([
        'institution_id' => $this->institution->id,
        'serial_number' => $zkDevice->serial_number,
        'zkteco_device_id' => $zkDevice->id,
        'is_active' => true,
    ]);

    // Student 1 scanned at gate
    ZktecoAttendanceLog::create([
        'device_id' => $zkDevice->id,
        'pin' => (string) $this->student1->id,
        'recorded_at' => Carbon::now()->subHours(2),
        'occurred_at' => Carbon::now()->subHours(2),
        'status' => 0,
        'verify_mode' => 1,
    ]);

    $this->actingAs($this->teacher);

    // Teacher marks student 1 absent despite gate scan (truancy / left class)
    Livewire::test('attendance::register', ['classId' => $this->schoolClass->id])
        ->call('markStatus', $this->student1->id, 'absent')
        ->call('saveAttendance');

    $record = Attendance::where('student_id', $this->student1->id)->where('date', today())->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe('absent');
    expect($record->source)->toBe('override');
    expect($record->scanned_at)->not->toBeNull();
});

test('verified source: student scanned at gate and verified present by teacher receives verified source', function () {
    $zkDevice = ZktecoDevice::create([
        'serial_number' => 'SN-'.uniqid(),
        'name' => 'Main Gate Terminal',
        'ip_address' => '192.168.1.102',
    ]);

    Devices::create([
        'institution_id' => $this->institution->id,
        'serial_number' => $zkDevice->serial_number,
        'zkteco_device_id' => $zkDevice->id,
        'is_active' => true,
    ]);

    ZktecoAttendanceLog::create([
        'device_id' => $zkDevice->id,
        'pin' => (string) $this->student1->id,
        'recorded_at' => Carbon::now()->subHours(2),
        'occurred_at' => Carbon::now()->subHours(2),
        'status' => 0,
        'verify_mode' => 1,
    ]);

    $this->actingAs($this->teacher);

    Livewire::test('attendance::register', ['classId' => $this->schoolClass->id])
        ->call('saveAttendance');

    $record = Attendance::where('student_id', $this->student1->id)->where('date', today())->first();
    expect($record->status)->toBe('present');
    expect($record->source)->toBe('verified');
    expect($record->scanned_at)->not->toBeNull();
});

test('teacher who is not homeroom teacher but has subject allocations can access their assigned class register', function () {
    $subjectTeacher = User::factory()->create();
    $subjectTeacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $subjectTeacher->id,
        'institution_id' => $this->institution->id,
        'is_active' => true,
    ]);

    $otherClass = SchoolClass::create([
        'institution_id' => $this->institution->id,
        'name' => 'Grade 8 Red',
        'curriculum_id' => $this->curriculum->id,
    ]);

    $subject = Subject::create([
        'institution_id' => $this->institution->id,
        'name' => 'Science',
        'code' => 'SCI-'.uniqid(),
    ]);

    SubjectTeacher::create([
        'institution_id' => $this->institution->id,
        'class_id' => $otherClass->id,
        'subject_id' => $subject->id,
        'teacher_id' => $subjectTeacher->id,
    ]);

    $learner = User::factory()->create(['name' => 'Wekesa Brian']);
    StudentDetails::create([
        'student_id' => $learner->id,
        'class_id' => $otherClass->id,
        'institution_id' => $this->institution->id,
        'admission_number' => 'ADM-099',
        'is_active' => true,
    ]);

    $this->actingAs($subjectTeacher);

    Livewire::test('attendance::register')
        ->assertSee('Grade 8 Red')
        ->assertSee('Wekesa Brian')
        ->call('markStatus', $learner->id, 'present')
        ->call('saveAttendance');

    $record = Attendance::where('student_id', $learner->id)->where('date', today())->first();
    expect($record)->not->toBeNull();
    expect($record->status)->toBe('present');
    expect($record->source)->toBe('manual');
});

test('biometric logs page shows 0 percent rate instead of misleading 100 percent when zero logs exist', function () {
    $this->actingAs($this->director);
    session(['current_institution_id' => $this->institution->id]);

    Livewire::test('attendance::index')
        ->assertSee('Attendance Stream')
        ->assertSee('0%')
        ->assertDontSee('100%');
});

test('register displays biometric time window context banner when students scan at gate', function () {
    $zkDevice = ZktecoDevice::create([
        'serial_number' => 'SN-'.uniqid(),
        'name' => 'Main Gate Scanner',
        'ip_address' => '192.168.1.103',
    ]);

    Devices::create([
        'institution_id' => $this->institution->id,
        'serial_number' => $zkDevice->serial_number,
        'zkteco_device_id' => $zkDevice->id,
        'is_active' => true,
    ]);

    $today = Carbon::today();
    // Student 1 scanned at 08:05 AM
    ZktecoAttendanceLog::create([
        'device_id' => $zkDevice->id,
        'pin' => (string) $this->student1->id,
        'recorded_at' => $today->copy()->setTime(8, 5),
        'occurred_at' => $today->copy()->setTime(8, 5),
        'status' => 0,
        'verify_mode' => 1,
    ]);

    // Student 2 scanned at 08:45 AM
    ZktecoAttendanceLog::create([
        'device_id' => $zkDevice->id,
        'pin' => (string) $this->student2->id,
        'recorded_at' => $today->copy()->setTime(8, 45),
        'occurred_at' => $today->copy()->setTime(8, 45),
        'status' => 0,
        'verify_mode' => 1,
    ]);

    $this->actingAs($this->teacher);

    Livewire::test('attendance::register', ['classId' => $this->schoolClass->id])
        ->assertSee('Biometric Gate Check-Ins Detected')
        ->assertSee('Main Gate Scanner')
        ->assertSee('8:05 AM')
        ->assertSee('8:45 AM')
        ->assertSee('Homeroom Class Teacher (You)');
});

test('register displays manual roll-call notice when no biometric scans recorded today', function () {
    $this->actingAs($this->teacher);

    Livewire::test('attendance::register', ['classId' => $this->schoolClass->id])
        ->assertSee('No Biometric Scans Recorded Today')
        ->assertSee('manual roll-call day')
        ->assertSee('Mark All Present');
});

test('register displays recorded time and teacher name once roll-call is saved', function () {
    $this->actingAs($this->teacher);

    // Save attendance
    Livewire::test('attendance::register', ['classId' => $this->schoolClass->id])
        ->call('markStatus', $this->student1->id, 'present')
        ->call('markStatus', $this->student2->id, 'present')
        ->call('markStatus', $this->student3->id, 'absent')
        ->call('saveAttendance');

    expect(Attendance::where('school_class_id', $this->schoolClass->id)->where('date', today())->count())->toBe(3);

    // Reload component
    Livewire::test('attendance::register', ['classId' => $this->schoolClass->id])
        ->assertSee('Class Roll-Call Already Recorded')
        ->assertSee($this->teacher->name)
        ->assertSee('Class Teacher');
});

test('teacher can dispatch parent absence sms with custom message confirmation', function () {
    // Add guardian contact to student 3
    StudentDetails::where('student_id', $this->student3->id)->update([
        'guardian_name' => 'Kilonzo Mutiso',
        'guardian_phone' => '0712345678',
    ]);

    $mockSms = Mockery::mock(SmsService::class);
    $mockSms->shouldReceive('send')->once()->andReturn(['success' => true]);
    $this->app->instance(SmsService::class, $mockSms);

    $this->actingAs($this->teacher);

    Livewire::test('attendance::register', ['classId' => $this->schoolClass->id])
        ->call('markStatus', $this->student3->id, 'absent')
        ->call('sendParentSms', $this->student3->id, 'Dear Kilonzo Mutiso, Mutiso Peter was absent today.');

    $record = Attendance::where('student_id', $this->student3->id)->where('date', today())->first();
    expect($record)->not->toBeNull();
    expect($record->parent_notified)->toBeTrue();
});

test('teacher visiting attendance stream only sees accessible classes and their own learners', function () {
    // Create another class not assigned to teacher
    $otherClass = SchoolClass::create([
        'institution_id' => $this->institution->id,
        'curriculum_id' => $this->curriculum->id,
        'name' => 'Grade 8 Beta',
        'code' => 'G8B',
        'capacity' => 45,
    ]);

    $otherStudent = User::factory()->create(['name' => 'Stranger Learner']);
    $otherStudent->assignRole('Student');
    StudentDetails::create([
        'student_id' => $otherStudent->id,
        'institution_id' => $this->institution->id,
        'class_id' => $otherClass->id,
        'admission_number' => 'ADM-OTHER-001',
    ]);

    // Setup biometric device and logs
    $zkDevice = ZktecoDevice::create([
        'serial_number' => 'DEV-STREAM-'.uniqid(),
        'name' => 'Gate Scanner',
        'status' => 'online',
        'last_activity_at' => now(),
        'timezone' => 'UTC',
        'options' => [],
    ]);
    Devices::create([
        'serial_number' => $zkDevice->serial_number,
        'institution_id' => $this->institution->id,
        'is_active' => true,
        'zkteco_device_id' => $zkDevice->id,
    ]);

    ZktecoUser::create([
        'pin' => (string) $this->student1->id,
        'name' => $this->student1->name,
        'app_user_id' => $this->student1->id,
        'device_id' => $zkDevice->id,
    ]);
    ZktecoAttendanceLog::create([
        'device_id' => $zkDevice->id,
        'pin' => (string) $this->student1->id,
        'status' => AttendanceStatus::CheckIn,
        'verify_mode' => VerifyMode::Fingerprint,
        'work_code' => '0',
        'occurred_at' => now(),
        'recorded_at' => now(),
    ]);

    ZktecoUser::create([
        'pin' => (string) $otherStudent->id,
        'name' => $otherStudent->name,
        'app_user_id' => $otherStudent->id,
        'device_id' => $zkDevice->id,
    ]);
    ZktecoAttendanceLog::create([
        'device_id' => $zkDevice->id,
        'pin' => (string) $otherStudent->id,
        'status' => AttendanceStatus::CheckIn,
        'verify_mode' => VerifyMode::Fingerprint,
        'work_code' => '0',
        'occurred_at' => now(),
        'recorded_at' => now(),
    ]);

    $this->actingAs($this->teacher);

    // Livewire test for Teacher
    $component = Livewire::test('attendance::index');
    $component->assertOk();

    // Teacher sees their assigned class in dropdown, but not the unassigned class
    $classesInComponent = collect($component->get('classes'))->pluck('name');
    expect($classesInComponent)->toContain('Grade 6 Blue')
        ->and($classesInComponent)->not->toContain('Grade 8 Beta');

    // Teacher sees their student's log, but NOT other student's log
    $component->assertSee($this->student1->name)
        ->assertDontSee('Stranger Learner');

    // Now test Director sees both classes and both students
    $this->actingAs($this->director);
    $directorComponent = Livewire::test('attendance::index');
    $directorComponent->assertOk();

    $directorClasses = collect($directorComponent->get('classes'))->pluck('name');
    expect($directorClasses)->toContain('Grade 6 Blue')
        ->and($directorClasses)->toContain('Grade 8 Beta');

    $directorComponent->assertSee($this->student1->name)
        ->assertSee('Stranger Learner');
});
