<?php

namespace Modules\Student\Actions;

use App\Models\Devices;
use App\Models\User;
use App\Services\ImageCompressionService;
use App\Services\ProfilePhotoResolver;
use App\Services\ZKTecoUserSyncService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Exceptions\StudentLimitException;
use Modules\Student\Models\StudentDetails;

/**
 * Enrolling and editing a student, in one place.
 *
 * There's more to it than a form save - a login has to be created, a parent
 * either linked or created alongside, a photo compressed and stored, and a
 * changed photo pushed back out to any biometric device the student is
 * already synced to. The Livewire screen and the controller endpoint both
 * come through here so none of that can be done one way in one place and
 * another way somewhere else.
 */
class SaveStudent
{
    public function __construct(
        private readonly ImageCompressionService $imageCompressor,
        private readonly ProfilePhotoResolver $photoResolver,
        private readonly ZKTecoUserSyncService $syncService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function createRules(int $institutionId = 0): array
    {
        return array_merge([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'profile_image' => 'required|image|max:2048',

            // Either link an existing parent account (a parent can have more
            // than one student) or fill in the fields below to create one.
            'parent_id' => ['nullable', 'exists:users,id', function ($attribute, $value, $fail) use ($institutionId) {
                if (! $value) {
                    return;
                }

                $parent = User::find($value);
                if (! $parent || ! $parent->hasRole('Parent')) {
                    $fail('The selected parent account is not valid.');

                    return;
                }

                // Verify parent has children in this institution or is from this institution
                $hasChildrenInInstitution = $parent->children()
                    ->whereHas('student', fn ($q) => $q->where('institution_id', $institutionId))
                    ->exists();

                if (! $hasChildrenInInstitution) {
                    $fail('The selected parent account cannot be used with this institution.');
                }
            }],
        ], self::sharedRules());
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateRules(): array
    {
        return array_merge([
            'profile_image' => 'nullable|image|max:2048',
        ], self::sharedRules());
    }

    /**
     * @return array<string, string>
     */
    private static function sharedRules(): array
    {
        return [
            // Personal
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'admission_number' => 'nullable|string|max:100',
            'student_number' => 'nullable|string|max:100',
            'class_id' => ['nullable', 'exists:classes,id'],
            'enrollment_status' => 'nullable|in:active,expelled,graduated,suspended,transferred,withdrawn,dropped',

            // Address
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',

            // Parent
            'parent_name' => 'nullable|string|max:255',
            'parent_phone' => 'nullable|string|max:20',
            'parent_email' => 'nullable|email|max:255',
            'parent_occupation' => 'nullable|string|max:255',

            // Guardian
            'guardian_name' => 'nullable|string|max:255',
            'guardian_phone' => 'nullable|string|max:20',
            'guardian_email' => 'nullable|email|max:255',
            'guardian_relationship' => 'nullable|string|max:100',

            // Additional
            'medical_conditions' => 'nullable|string',
            'allergies' => 'nullable|string',
            'special_needs' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $institutionId, ?UploadedFile $photo = null): User
    {
        $this->guardStudentLimit($institutionId);

        return DB::transaction(function () use ($data, $institutionId, $photo) {
            $parent = $this->resolveParentForCreate($data);

            $student = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $student->syncRoles('Student');

            $studentData = collect($data)
                ->except([
                    'name', 'email', 'password', 'is_active', 'parent_id', 'parent_name',
                    'parent_phone', 'profile_image', 'parent_email', 'parent_occupation',
                ])
                ->toArray();

            // student_id is the relation's key. Production also carries a
            // legacy user_id column this codebase never created (see the
            // make_legacy_student_details_user_id_nullable migration) - it
            // duplicates student_id, so it's kept in step where it exists
            // and skipped entirely where it doesn't.
            $studentData['student_id'] = $student->id;

            if (Schema::hasColumn('student_details', 'user_id')) {
                $studentData['user_id'] = $student->id;
            }
            $studentData['institution_id'] = $institutionId;
            $studentData['is_active'] = (bool) ($data['is_active'] ?? false);

            if ($parent) {
                $studentData['parent_id'] = $parent->id;
            }

            if ($photo) {
                $studentData['profile_photo'] = $this->imageCompressor->store($photo, 'students/photos');
            }

            $student->studentUserDetails()->create($studentData);

            return $student;
        });
    }

    /**
     * The plan's student ceiling, enforced here because every enrolment
     * path (form, bulk upload, API) comes through create(). There is
     * deliberately no argument to skip it: a director cannot get past the
     * cap, and neither can an admin acting for the institution - the plan
     * has to change instead.
     */
    private function guardStudentLimit(int $institutionId): void
    {
        $institution = Institution::find($institutionId);

        if ($institution && ! $institution->canAddStudents(1)) {
            throw StudentLimitException::forInstitution($institution);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $student, array $data, ?UploadedFile $photo = null): User
    {
        DB::transaction(function () use ($student, $data, $photo) {
            $studentDetails = StudentDetails::where('student_id', $student->id)->firstOrFail();

            $studentData = collect($data)
                ->except(['parent_name', 'parent_phone', 'parent_email', 'parent_occupation', 'profile_image'])
                ->toArray();

            $studentData['is_active'] = (bool) ($data['is_active'] ?? false);

            // Replace the photo and delete the old file only when a new one
            // was actually sent.
            if ($photo) {
                if ($studentDetails->profile_photo && Storage::disk('public')->exists($studentDetails->profile_photo)) {
                    Storage::disk('public')->delete($studentDetails->profile_photo);
                }

                $studentData['profile_photo'] = $this->imageCompressor->store($photo, 'students/photos');
            }

            $studentDetails->update($studentData);

            if ($photo) {
                $this->resyncPhotoIfAlreadySynced($studentDetails);
            }

            $this->syncParentOnUpdate($studentDetails, $data);
        });

        return $student->refresh();
    }

    /**
     * Link the parent account that was chosen, or create one from whatever
     * parent details were filled in. Neither given means the student simply
     * has no parent on file yet.
     *
     * The parent_id is already validated in createRules() to ensure it belongs
     * to the correct institution, but we do a final safety check here before use.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveParentForCreate(array $data): ?User
    {
        if (! empty($data['parent_id'])) {
            $parent = User::find($data['parent_id']);
            // Safety check - validation in createRules() should prevent this, but be defensive
            abort_unless($parent && $parent->hasRole('Parent'), 403, 'Invalid parent account.');

            return $parent;
        }

        if (empty($data['parent_name']) && empty($data['parent_email']) && empty($data['parent_phone'])) {
            return null;
        }

        $parent = User::create([
            'name' => $data['parent_name'] ?? 'Parent',
            'email' => $data['parent_email'] ?? 'parent_'.time().'@example.com',
            'password' => Hash::make($data['parent_phone'] ?? 'password123'),
        ]);
        $parent->syncRoles('Parent');

        ParentDetails::create([
            'parent_id' => $parent->id,
            'parent_phone' => $data['parent_phone'] ?? null,
            'parent_occupation' => $data['parent_occupation'] ?? null,
        ]);

        return $parent;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncParentOnUpdate(StudentDetails $studentDetails, array $data): void
    {
        $hasParentDetails = ! empty($data['parent_name']) || ! empty($data['parent_email']) || ! empty($data['parent_phone']);

        if (! $hasParentDetails) {
            // Nothing filled in means the student is no longer linked to a
            // parent; the parent account itself is left alone.
            if ($studentDetails->parent_id) {
                $studentDetails->parent_id = null;
                $studentDetails->save();
            }

            return;
        }

        $parent = $studentDetails->parent_id ? User::find($studentDetails->parent_id) : null;

        if ($parent) {
            $parent->update([
                'name' => $data['parent_name'] ?? $parent->name,
                'email' => $data['parent_email'] ?? $parent->email,
            ]);
        } else {
            $parent = User::create([
                'name' => $data['parent_name'] ?? 'Parent',
                'email' => $data['parent_email'] ?? 'parent_'.time().'@example.com',
                'password' => Hash::make($data['parent_phone'] ?? 'password123'),
            ]);
            $parent->syncRoles('Parent');

            $studentDetails->parent_id = $parent->id;
            $studentDetails->save();
        }

        ParentDetails::updateOrCreate(
            ['parent_id' => $parent->id],
            [
                'parent_phone' => $data['parent_phone'] ?? null,
                'parent_occupation' => $data['parent_occupation'] ?? null,
            ]
        );
    }

    /**
     * Push the student's updated photo (and current info) to their
     * institution's active device(s), but only if they were already synced -
     * a student who was never synced will pick up the new photo the same way
     * they'd pick up anything else: manual sync or the device's next connect
     * event.
     */
    private function resyncPhotoIfAlreadySynced(StudentDetails $studentDetails): void
    {
        $student = User::find($studentDetails->student_id);

        if (! $student || ! $student->zkteco_synced) {
            return;
        }

        $devices = Devices::whereInstitutionId($studentDetails->institution_id)
            ->where('is_active', true)
            ->get();

        if ($devices->isEmpty()) {
            return;
        }

        $this->photoResolver->withLocalPath($studentDetails->profile_photo, function (?string $photoPath) use ($devices, $student, $studentDetails) {
            foreach ($devices as $device) {
                $this->syncService->addUserToDevice($device->serial_number, [
                    'pin' => (string) $student->id,
                    'name' => $student->name,
                    'privilege' => 0,
                    'card' => $studentDetails->student_number ?? '',
                    'password' => $studentDetails->admission_number ?? '',
                    'app_user_id' => $student->id,
                    'photo_path' => $photoPath,
                ]);
            }
        });

        Log::info('Re-synced student photo to device(s) after edit', [
            'student_id' => $student->id,
            'devices' => $devices->pluck('serial_number')->all(),
        ]);
    }
}
