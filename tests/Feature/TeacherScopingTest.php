<?php

use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
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

test('analytics service scopes teacher stats to homeroom and teaching allocations', function () {
    $director = User::factory()->create();
    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'St. Teresa School',
        'code' => 'STS-'.uniqid(),
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

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    $myClass = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 East',
        'curriculum_id' => $curriculum->id,
        'class_teacher_id' => $teacher->id,
    ]);

    $otherClass = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 North',
        'curriculum_id' => $curriculum->id,
    ]);

    $math = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'code' => 'MTH',
    ]);

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $myClass->id,
        'subject_id' => $math->id,
        'teacher_id' => $teacher->id,
    ]);

    // 2 students in myClass, 5 in otherClass
    for ($i = 0; $i < 2; $i++) {
        $u = User::factory()->create();
        StudentDetails::create([
            'student_id' => $u->id,
            'institution_id' => $institution->id,
            'class_id' => $myClass->id,
            'admission_number' => 'ADM-MY-'.$i,
            'gender' => 'male',
        ]);
    }
    for ($i = 0; $i < 5; $i++) {
        $u = User::factory()->create();
        StudentDetails::create([
            'student_id' => $u->id,
            'institution_id' => $institution->id,
            'class_id' => $otherClass->id,
            'admission_number' => 'ADM-OTHER-'.$i,
            'gender' => 'female',
        ]);
    }

    $analytics = app(AnalyticsService::class);
    $stats = $analytics->forUser($teacher);

    expect($stats['homeroom_class'])->not->toBeNull()
        ->and($stats['homeroom_class']->id)->toBe($myClass->id)
        ->and($stats['teaching_allocations']->count())->toBe(1)
        ->and($stats['students_count'])->toBe(2); // strictly scoped to taught class, not 7!
});

test('formative assessment screen restricts classes and subjects to teacher allocations', function () {
    $director = User::factory()->create();
    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'St. Teresa School',
        'code' => 'STS-'.uniqid(),
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

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $teacher->id,
        'institution_id' => $institution->id,
        'is_active' => true,
    ]);

    $myClass = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 7 East',
        'curriculum_id' => $curriculum->id,
        'class_teacher_id' => $teacher->id,
    ]);

    $forbiddenClass = SchoolClass::create([
        'institution_id' => $institution->id,
        'name' => 'Grade 8 West',
        'curriculum_id' => $curriculum->id,
    ]);

    $math = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'Mathematics',
        'code' => 'MTH',
    ]);

    $french = Subject::create([
        'institution_id' => $institution->id,
        'name' => 'French',
        'code' => 'FRN',
    ]);

    SubjectTeacher::create([
        'institution_id' => $institution->id,
        'class_id' => $myClass->id,
        'subject_id' => $math->id,
        'teacher_id' => $teacher->id,
    ]);

    $this->actingAs($teacher);

    // Livewire component test
    $component = Livewire::test('examinations::formative');
    $classes = $component->get('classes');
    expect($classes->pluck('id')->all())->toContain($myClass->id)
        ->and($classes->pluck('id')->all())->not->toContain($forbiddenClass->id);

    // Formative store endpoint refuses forbidden class/subject for teacher
    $response = $this->post(route('assessment.formative.store'), [
        'title' => 'Illegal Task',
        'assessment_category' => 'sba',
        'class_id' => $forbiddenClass->id,
        'subject_id' => $french->id,
        'assessment_date' => now()->toDateString(),
    ]);

    $response->assertStatus(403);
});
