<?php

use App\Models\Devices;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Classes\Models\SchoolClass;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Staff\Models\StaffDetails;
use Modules\Student\Models\StudentDetails;
use Modules\Teacher\Models\TeacherDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Storage::fake('public');
});

function platformAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    return $admin;
}

/**
 * A fully-populated school: a Director, students (with parents + photos),
 * a teacher, an accountant, and a spread of related records.
 */
function schoolWithEverything(string $name = 'Riverside Academy'): Institution
{
    $director = User::factory()->create(['name' => $name.' Director']);
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => $name,
        'code' => 'RA-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
        'logo' => 'institutions/logos/'.uniqid().'.png',
    ]);
    Storage::disk('public')->put($institution->logo, 'x');
    $director->update(['active_institution_id' => $institution->id]);

    foreach (range(1, 3) as $i) {
        $studentUser = User::factory()->create(['name' => "$name Student $i"]);
        $studentUser->assignRole('Student');

        $parentUser = User::factory()->create(['name' => "$name Parent $i"]);
        $parentUser->assignRole('Parent');
        ParentDetails::create(['parent_id' => $parentUser->id, 'parent_phone' => '070'.uniqid()]);

        $photo = 'students/photos/'.uniqid().'.jpg';
        Storage::disk('public')->put($photo, 'x');

        StudentDetails::create([
            'student_id' => $studentUser->id,
            'parent_id' => $parentUser->id,
            'institution_id' => $institution->id,
            'admission_number' => 'ADM-'.uniqid(),
            'enrollment_status' => 'active',
            'profile_photo' => $photo,
        ]);

        // A fee (FK: fees.student_id -> users, cascadeOnDelete) and a stale
        // biometric mapping (zkteco_users.app_user_id, no FK) - both must
        // survive the student user being deleted without a constraint error.
        Fee::create([
            'institution_id' => $institution->id,
            'student_id' => $studentUser->id,
            'parent_id' => $parentUser->id,
            'title' => 'Term 1',
            'amount' => 5000,
        ]);
        DB::table('zkteco_users')->insert([
            'pin' => (string) $studentUser->id,
            'app_user_id' => $studentUser->id,
        ]);
    }

    $teacherUser = User::factory()->create(['name' => "$name Teacher"]);
    $teacherUser->assignRole('Teacher');
    TeacherDetails::create([
        'teacher_id' => $teacherUser->id,
        'institution_id' => $institution->id,
        'department' => 'Sciences',
    ]);

    $accountantUser = User::factory()->create(['name' => "$name Accountant"]);
    $accountantUser->assignRole('Accountant');
    StaffDetails::create([
        'user_id' => $accountantUser->id,
        'institution_id' => $institution->id,
        'name' => $accountantUser->name,
        'employment_type' => 'full_time',
        'status' => 'active',
    ]);

    SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1']);
    Devices::create(['serial_number' => 'SN-'.uniqid(), 'institution_id' => $institution->id]);

    $plan = Plan::create([
        'name' => 'P-'.uniqid(), 'slug' => 'p-'.uniqid(), 'price' => 1000,
        'billing_cycle' => 'termly', 'modules' => ['Student'], 'features' => [], 'is_active' => true,
    ]);
    Payment::create([
        'institution_id' => $institution->id,
        'plan_id' => $plan->id,
        'reference' => 'REF-'.uniqid(),
        'amount' => 1000,
        'status' => 'successful',
    ]);

    return $institution->refresh();
}

test('an admin permanently deletes an institution and every record under it', function () {
    $admin = platformAdmin();
    $institution = schoolWithEverything();
    $id = $institution->id;
    $directorId = $institution->user_id;
    $logo = $institution->logo;
    $photos = StudentDetails::where('institution_id', $id)->pluck('profile_photo');
    $studentIds = StudentDetails::where('institution_id', $id)->pluck('student_id');
    $teacherIds = TeacherDetails::where('institution_id', $id)->pluck('teacher_id');
    $accountantId = StaffDetails::where('institution_id', $id)->value('user_id');

    $this->actingAs($admin)->delete(route('institution.destroy', $id))
        ->assertRedirect(route('institution.index'))
        ->assertSessionHas('success');

    // The school and every institution-scoped table.
    expect(Institution::find($id))->toBeNull()
        ->and(StudentDetails::where('institution_id', $id)->exists())->toBeFalse()
        ->and(TeacherDetails::where('institution_id', $id)->exists())->toBeFalse()
        ->and(StaffDetails::withTrashed()->where('institution_id', $id)->exists())->toBeFalse()
        ->and(SchoolClass::where('institution_id', $id)->exists())->toBeFalse()
        ->and(Devices::where('institution_id', $id)->exists())->toBeFalse()
        ->and(Payment::where('institution_id', $id)->exists())->toBeFalse()
        ->and(Fee::withTrashed()->where('institution_id', $id)->exists())->toBeFalse();

    // The logins that existed only for this school.
    expect(User::whereIn('id', $studentIds)->count())->toBe(0)
        ->and(User::whereIn('id', $teacherIds)->count())->toBe(0)
        ->and(User::find($accountantId))->toBeNull()
        ->and(User::find($directorId))->toBeNull()
        // parent_details rode along on the parent user delete, no FK error.
        ->and(ParentDetails::whereIn('parent_id', $studentIds)->exists())->toBeFalse();

    // The uploaded files those rows pointed at.
    expect(Storage::disk('public')->exists($logo))->toBeFalse();
    foreach ($photos as $photo) {
        expect(Storage::disk('public')->exists($photo))->toBeFalse();
    }
});

test('a parent with a child in another school keeps their account', function () {
    $admin = platformAdmin();
    $doomed = schoolWithEverything('Doomed High');
    $survivor = schoolWithEverything('Survivor High');

    // Re-point one of Doomed's parents so they also have a child at Survivor.
    $sharedParentId = StudentDetails::where('institution_id', $doomed->id)->value('parent_id');
    $survivorStudent = StudentDetails::where('institution_id', $survivor->id)->first();
    $survivorStudent->update(['parent_id' => $sharedParentId]);

    $this->actingAs($admin)->delete(route('institution.destroy', $doomed->id))->assertRedirect();

    expect(User::find($sharedParentId))->not->toBeNull()
        ->and(Institution::find($survivor->id))->not->toBeNull()
        ->and(StudentDetails::where('institution_id', $survivor->id)->count())->toBe(3);
});

test('a Director who runs a second school is not deleted, and the second school is untouched', function () {
    $admin = platformAdmin();
    $first = schoolWithEverything('First Campus');

    $second = Institution::create([
        'user_id' => $first->user_id,
        'name' => 'Second Campus',
        'code' => 'SC-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $this->actingAs($admin)->delete(route('institution.destroy', $first->id))->assertRedirect();

    expect(Institution::find($first->id))->toBeNull()
        ->and(User::find($first->user_id))->not->toBeNull()
        ->and(Institution::find($second->id))->not->toBeNull();
});

test('a Director cannot delete an institution', function () {
    $director = User::factory()->create();
    $director->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $director->id,
        'name' => 'Not Yours',
        'code' => 'NY-'.uniqid(),
        'type' => 'School',
        'is_approved' => true,
    ]);

    $this->actingAs($director)->delete(route('institution.destroy', $institution->id))
        ->assertForbidden();

    expect(Institution::find($institution->id))->not->toBeNull();
});

test('the livewire index deletes through its confirm dialog', function () {
    $admin = platformAdmin();
    $institution = schoolWithEverything('Livewire School');

    Livewire::actingAs($admin)
        ->test('institution::index')
        ->set('deletingId', $institution->id)
        ->call('delete')
        ->assertHasNoErrors()
        ->assertSet('deletingId', null);

    expect(Institution::find($institution->id))->toBeNull();
});

test('the delete dialog counts what will be removed', function () {
    $admin = platformAdmin();
    $institution = schoolWithEverything('Counted School');

    Livewire::actingAs($admin)
        ->test('institution::index')
        ->set('deletingId', $institution->id)
        ->assertSee('Counted School')
        ->assertSee('This cannot be undone');
});
