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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'email' => 'nullable|email|max:255|unique:users,email',
            'password' => 'nullable|string|min:8',
            'profile_image' => 'nullable|image|max:2048',
            'admission_number' => 'nullable|string|max:100|unique:student_details,admission_number',

            // Either link an existing parent account (a parent can have more
            // than one student) or fill in the fields below to create one.
            'parent_id' => ['nullable', 'exists:users,id', function ($attribute, $value, $fail) use ($institutionId) {
                if (! $value) {
                    return;
                }

                $parent = User::whereKey($value)->first();
                if (! ($parent instanceof User) || ! $parent->hasRole('Parent')) {
                    $fail('The selected parent account is not valid.');

                    return;
                }

                // The parent must belong to this school: either their own
                // record is stamped with it, or they already have a child
                // enrolled here. student_details carries institution_id;
                // users does not.
                $ownsRow = ParentDetails::where('parent_id', $parent->id)
                    ->where('institution_id', $institutionId)
                    ->exists();

                $hasChildHere = StudentDetails::where('parent_id', $parent->id)
                    ->where('institution_id', $institutionId)
                    ->exists();

                if (! $ownsRow && ! $hasChildHere) {
                    $fail('The selected parent account cannot be used with this institution.');
                }
            }],
        ], self::sharedRules());
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateRules(?int $studentUserId = null, ?int $studentDetailsId = null): array
    {
        return array_merge([
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                $studentUserId ? Rule::unique('users', 'email')->ignore($studentUserId) : 'nullable',
            ],
            'password' => 'nullable|string|min:8',
            'profile_image' => 'nullable|image|max:2048',
            'admission_number' => [
                'nullable',
                'string',
                'max:100',
                $studentDetailsId ? Rule::unique('student_details', 'admission_number')->ignore($studentDetailsId) : 'nullable',
            ],
        ], self::sharedRules());
    }

    /**
     * @return array<string, mixed>
     */
    private static function sharedRules(): array
    {
        return [
            // Personal
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
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
            $parent = $this->resolveParentForCreate($data, $institutionId);

            $admissionNumber = ! empty($data['admission_number'])
                ? trim((string) $data['admission_number'])
                : null;

            $email = ! empty($data['email'])
                ? trim((string) $data['email'])
                : $this->uniqueEmail($data['name'], $admissionNumber);

            $password = ! empty($data['password'])
                ? Hash::make($data['password'])
                : Hash::make(Str::random(16));

            $student = User::create([
                'name' => $data['name'],
                'email' => $email,
                'password' => $password,
            ]);
            $student->syncRoles('Student');

            $studentData = collect($data)
                ->except([
                    'name', 'email', 'password', 'is_active', 'parent_id', 'parent_name',
                    'parent_phone', 'profile_image', 'parent_email', 'parent_occupation',
                ])
                ->toArray();

            $studentData['admission_number'] = $admissionNumber;
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
     * Generate an admission number that doesn't collide with existing records.
     */
    public function generateUniqueAdmissionNumber(): string
    {
        do {
            $adm = strtoupper('ADM-'.now()->year.'-'.Str::random(6));
        } while (StudentDetails::where('admission_number', $adm)->exists());

        return $adm;
    }

    /**
     * Synthesize a clean, unique student login email when none is provided.
     */
    public function uniqueEmail(string $name, ?string $admissionNumber = null): string
    {
        $baseSeed = (! empty($admissionNumber)) ? $admissionNumber : $name;
        $base = Str::slug($baseSeed, '.') ?: 'student';
        $email = $base.'@students.generated';
        $suffix = 1;

        while (User::where('email', $email)->exists()) {
            $email = $base.'.'.$suffix++.'@students.generated';
        }

        return $email;
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

            // Update user record (name, email, password if provided)
            $userUpdates = [];
            if (! empty($data['name'])) {
                $userUpdates['name'] = $data['name'];
            }
            if (! empty($data['email'])) {
                $userUpdates['email'] = $data['email'];
            }
            if (! empty($data['password'])) {
                $userUpdates['password'] = Hash::make($data['password']);
            }
            if (! empty($userUpdates)) {
                $student->update($userUpdates);
            }

            $studentData = collect($data)
                ->except(['name', 'email', 'password', 'parent_name', 'parent_phone', 'parent_email', 'parent_occupation', 'profile_image'])
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
     * Create/link the Parent account from the denormalised guardian_name /
     * guardian_phone columns (used by bulk upload and in-place roster edits).
     */
    public function syncParentFromGuardian(StudentDetails $studentDetails): void
    {
        $name = trim((string) $studentDetails->guardian_name);
        $phone = trim((string) $studentDetails->guardian_phone);

        if ($name === '' && $phone === '') {
            return;
        }

        DB::transaction(fn () => $this->syncParentOnUpdate($studentDetails, [
            'parent_name' => $name !== '' ? $name : null,
            'parent_phone' => $phone !== '' ? $phone : null,
        ]));
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
    private function resolveParentForCreate(array $data, int $institutionId): ?User
    {
        if (! empty($data['parent_id'])) {
            $parent = User::whereKey($data['parent_id'])->first();
            // Safety check - validation in createRules() should prevent this, but be defensive
            abort_unless($parent instanceof User && $parent->hasRole('Parent'), 403, 'Invalid parent account.');

            return $parent;
        }

        if (empty($data['parent_name']) && empty($data['parent_email']) && empty($data['parent_phone'])) {
            return null;
        }

        $phone = ! empty($data['parent_phone']) ? trim((string) $data['parent_phone']) : null;
        $email = ! empty($data['parent_email']) ? trim((string) $data['parent_email']) : null;

        // Check if an existing parent account already matches this phone
        if ($phone) {
            $existingParentDetails = ParentDetails::where('parent_phone', $phone)->first();
            if ($existingParentDetails && $existingParentDetails->parent) {
                if (! empty($data['parent_name'])) {
                    $existingParentDetails->parent->update(['name' => $data['parent_name']]);
                }
                if (! empty($data['parent_occupation'])) {
                    $existingParentDetails->update(['parent_occupation' => $data['parent_occupation']]);
                }

                return $existingParentDetails->parent;
            }
        }

        if ($email) {
            $existingParent = User::role('Parent')->where('email', $email)->first();
            if ($existingParent) {
                return $existingParent;
            }
        }

        // Check if an existing parent account in this school matches by name
        if (! empty($data['parent_name'])) {
            $existingParentByName = User::role('Parent')
                ->where('name', trim((string) $data['parent_name']))
                ->whereHas('parent', fn ($pq) => $pq->where('institution_id', $institutionId))
                ->first();

            if ($existingParentByName) {
                return $existingParentByName;
            }
        }

        $parentEmail = $email ?: 'parent_'.uniqid().'_'.Str::random(4).'@parents.generated';

        $parent = User::create([
            'name' => $data['parent_name'] ?? 'Parent',
            'email' => $parentEmail,
            'password' => Hash::make($phone ?? 'password123'),
        ]);
        $parent->syncRoles('Parent');

        ParentDetails::create([
            'parent_id' => $parent->id,
            // The school enrolling the student owns the parent account it
            // creates alongside them.
            'institution_id' => $institutionId,
            'parent_phone' => $phone,
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

        $phone = ! empty($data['parent_phone']) ? trim((string) $data['parent_phone']) : null;
        $email = ! empty($data['parent_email']) ? trim((string) $data['parent_email']) : null;

        // 1. If phone matches an existing parent in parent_details, link to that parent directly
        if ($phone) {
            $existingDetails = ParentDetails::where('parent_phone', $phone)->first();
            if ($existingDetails && $existingDetails->parent) {
                $oldParentId = $studentDetails->parent_id;

                $studentDetails->parent_id = $existingDetails->parent_id;
                $studentDetails->save();

                if (! empty($data['parent_name'])) {
                    $existingDetails->parent->update(['name' => $data['parent_name']]);
                }
                if (! empty($data['parent_occupation'])) {
                    $existingDetails->update(['parent_occupation' => $data['parent_occupation']]);
                }

                // If previous parent was an orphaned generated dummy parent, clean it up
                if ($oldParentId && $oldParentId !== $existingDetails->parent_id) {
                    $this->cleanupOrphanedDummyParent($oldParentId);
                }

                return;
            }
        }

        // 2. If email matches an existing parent in users, link to that parent
        if ($email) {
            $existingParent = User::role('Parent')->where('email', $email)->first();
            if ($existingParent) {
                $oldParentId = $studentDetails->parent_id;

                $studentDetails->parent_id = $existingParent->id;
                $studentDetails->save();

                if (! empty($data['parent_name'])) {
                    $existingParent->update(['name' => $data['parent_name']]);
                }

                $details = ParentDetails::firstOrNew(['parent_id' => $existingParent->id]);
                if ($phone) {
                    $details->parent_phone = $phone;
                }
                if (! empty($data['parent_occupation'])) {
                    $details->parent_occupation = $data['parent_occupation'];
                }
                $details->institution_id ??= $studentDetails->institution_id;
                $details->save();

                if ($oldParentId && $oldParentId !== $existingParent->id) {
                    $this->cleanupOrphanedDummyParent($oldParentId);
                }

                return;
            }
        }

        // 3. If phone and email are not provided, match existing parent by name within this institution
        if (! $phone && ! $email && ! empty($data['parent_name'])) {
            $existingParentByName = User::role('Parent')
                ->where('name', trim((string) $data['parent_name']))
                ->whereHas('parent', fn ($pq) => $pq->where('institution_id', $studentDetails->institution_id))
                ->first();

            if ($existingParentByName) {
                $oldParentId = $studentDetails->parent_id;

                $studentDetails->parent_id = $existingParentByName->id;
                $studentDetails->save();

                if ($oldParentId && $oldParentId !== $existingParentByName->id) {
                    $this->cleanupOrphanedDummyParent($oldParentId);
                }

                return;
            }
        }

        // 4. Otherwise update current parent or create a new one
        $parent = $studentDetails->parent_id ? User::find($studentDetails->parent_id) : null;

        if ($parent) {
            $parentUpdates = [];
            if (! empty($data['parent_name'])) {
                $parentUpdates['name'] = $data['parent_name'];
            }
            if ($email) {
                $parentUpdates['email'] = $email;
            }
            if (! empty($parentUpdates)) {
                $parent->update($parentUpdates);
            }
        } else {
            $parent = User::create([
                'name' => $data['parent_name'] ?? 'Parent',
                'email' => $email ?: 'parent_'.uniqid().'_'.Str::random(4).'@parents.generated',
                'password' => Hash::make($phone ?? 'password123'),
            ]);
            $parent->syncRoles('Parent');

            $studentDetails->parent_id = $parent->id;
            $studentDetails->save();
        }

        $details = ParentDetails::firstOrNew(['parent_id' => $parent->id]);
        $details->parent_phone = $phone;
        $details->parent_occupation = $data['parent_occupation'] ?? null;
        $details->institution_id ??= $studentDetails->institution_id;
        $details->save();
    }

    private function cleanupOrphanedDummyParent(int $parentId): void
    {
        $hasOtherChildren = StudentDetails::where('parent_id', $parentId)->exists();
        if (! $hasOtherChildren) {
            $user = User::find($parentId);
            if ($user && (str_ends_with($user->email, '@parents.generated') || str_ends_with($user->email, '@example.com'))) {
                ParentDetails::where('parent_id', $parentId)->delete();
                $user->delete();
            }
        }
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
