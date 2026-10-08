<?php

namespace Modules\Student\Actions;

use App\Models\Devices;
use App\Models\User;
use App\Services\ImageCompressionService;
use App\Services\ParentMatchingService;
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
            'admission_number' => [
                'required',
                'string',
                'max:100',
                $institutionId > 0
                    ? Rule::unique('student_details', 'admission_number')->where('institution_id', $institutionId)
                    : Rule::unique('student_details', 'admission_number'),
            ],

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
    public static function updateRules(?int $studentUserId = null, ?int $studentDetailsId = null, int $institutionId = 0): array
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
                'required',
                'string',
                'max:100',
                $studentDetailsId
                    ? ($institutionId > 0
                        ? Rule::unique('student_details', 'admission_number')->where('institution_id', $institutionId)->ignore($studentDetailsId)
                        : Rule::unique('student_details', 'admission_number')->ignore($studentDetailsId))
                    : 'required',
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
        // 1. Idempotency & Duplicate Prevention:
        // Before creating a new user or student details, search for an existing active student match.
        $existing = $this->findExistingStudentMatch($institutionId, $data);
        if ($existing) {
            return $this->enrichExistingStudent($existing, $data, $photo);
        }

        $this->guardStudentLimit($institutionId);

        return DB::transaction(function () use ($data, $institutionId, $photo) {
            $parent = $this->resolveParentForCreate($data, $institutionId);

            $admissionNumber = ! empty($data['admission_number'])
                ? trim((string) $data['admission_number'])
                : null;

            if (empty($admissionNumber)) {
                throw new \InvalidArgumentException('Admission number is required to enroll a student. Student details cannot be saved without it.');
            }

            // Student email must NOT be automatically generated with fake domains.
            // Leave it NULL when not supplied.
            $email = ! empty($data['email'])
                ? trim((string) $data['email'])
                : null;

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
            $studentData['is_active'] = (bool) ($data['is_active'] ?? true);

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
     * Find an existing active student record that safely matches this identity.
     * Checks admission_number, student_number, and normalized student name while
     * strictly verifying there are no conflicting identity fields (different DOB, different parent phone).
     *
     * @param  array<string, mixed>  $data
     */
    public function findExistingStudentMatch(int $institutionId, array $data): ?StudentDetails
    {
        $name = trim((string) ($data['name'] ?? ''));
        $admissionNumber = ! empty($data['admission_number']) ? trim((string) $data['admission_number']) : null;
        $studentNumber = ! empty($data['student_number']) ? trim((string) $data['student_number']) : null;

        // 1. If admission number is supplied, check for existing student with that admission number in this institution
        if ($admissionNumber) {
            $byAdm = StudentDetails::where('institution_id', $institutionId)
                ->where('admission_number', $admissionNumber)
                ->with(['student', 'parent'])
                ->first();

            if ($byAdm) {
                return $byAdm;
            }
        }

        // 2. If student_number is supplied, check by student_number in this institution
        if ($studentNumber) {
            $byStuNo = StudentDetails::where('institution_id', $institutionId)
                ->where('student_number', $studentNumber)
                ->with(['student', 'parent'])
                ->first();

            if ($byStuNo) {
                return $byStuNo;
            }
        }

        if ($name === '') {
            return null;
        }

        $normName = preg_replace('/\s+/', ' ', strtolower($name));
        $classId = ! empty($data['class_id']) ? (int) $data['class_id'] : null;

        // 3. Search for candidates with the same normalized name in this institution
        $candidates = StudentDetails::where('institution_id', $institutionId)
            ->whereHas('student', function ($q) use ($normName) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', [$normName]);
            })
            ->with(['student', 'parent'])
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $matcher = app(ParentMatchingService::class);
        $rawPhone = ! empty($data['guardian_phone']) ? $data['guardian_phone'] : (! empty($data['parent_phone']) ? $data['parent_phone'] : null);
        $inputPhone = $rawPhone ? $matcher->normalizePhone($rawPhone) : null;
        $rawGuardian = ! empty($data['guardian_name']) ? $data['guardian_name'] : (! empty($data['parent_name']) ? $data['parent_name'] : null);
        $inputGuardian = $rawGuardian ? $matcher->normalizeName($rawGuardian) : null;
        $inputDob = ! empty($data['date_of_birth']) ? $data['date_of_birth'] : null;

        $matching = $candidates->filter(function (StudentDetails $cand) use ($classId, $inputPhone, $inputDob, $admissionNumber, $matcher) {
            // If candidate already has an explicit admission number and a DIFFERENT admission number was passed, conflict!
            if ($admissionNumber && ! empty($cand->admission_number) && strcasecmp($cand->admission_number, $admissionNumber) !== 0) {
                return false;
            }

            // Conflict if DOB differs
            if ($inputDob && ! empty($cand->date_of_birth) && $cand->date_of_birth !== $inputDob) {
                return false;
            }

            // Conflict if guardian phone differs
            if ($inputPhone && ! empty($cand->guardian_phone)) {
                $candPhone = $matcher->normalizePhone($cand->guardian_phone);
                if ($candPhone && $candPhone !== $inputPhone) {
                    return false;
                }
            }

            // Conflict if explicit classes differ
            if ($classId && ! empty($cand->class_id) && (int) $cand->class_id !== $classId) {
                return false;
            }

            return true;
        });

        if ($matching->count() === 1) {
            return $matching->first();
        }

        return null;
    }

    /**
     * Enrich an existing student details record in-place instead of creating a duplicate student.
     *
     * @param  array<string, mixed>  $data
     */
    public function enrichExistingStudent(StudentDetails $existing, array $data, ?UploadedFile $photo = null): User
    {
        return DB::transaction(function () use ($existing, $data, $photo) {
            $user = $existing->student ?? User::find($existing->student_id);

            $adm = ! empty($data['admission_number']) ? trim((string) $data['admission_number']) : null;
            if ($adm && empty($existing->admission_number)) {
                $existing->admission_number = $adm;
            }

            if (! empty($data['class_id']) && empty($existing->class_id)) {
                $existing->class_id = $data['class_id'];
            }

            if (! empty($data['student_number']) && empty($existing->student_number)) {
                $existing->student_number = $data['student_number'];
            }

            if (! empty($data['gender']) && empty($existing->gender)) {
                $existing->gender = in_array(strtolower($data['gender']), ['male', 'female', 'other'], true) ? strtolower($data['gender']) : null;
            }

            if (! empty($data['date_of_birth']) && empty($existing->date_of_birth)) {
                $existing->date_of_birth = $data['date_of_birth'];
            }

            if (! empty($data['guardian_name']) && empty($existing->guardian_name)) {
                $existing->guardian_name = $data['guardian_name'];
            }

            if (! empty($data['guardian_phone']) && empty($existing->guardian_phone)) {
                $existing->guardian_phone = $data['guardian_phone'];
            }

            if ($photo) {
                $existing->profile_photo = $this->imageCompressor->store($photo, 'students/photos');
            }

            $existing->save();

            $this->syncParentOnUpdate($existing, $data);

            return $user ?? $existing->student;
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
        return app(ParentMatchingService::class)->resolveParent($institutionId, $data);
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
                $oldParentId = $studentDetails->parent_id;
                $studentDetails->parent_id = null;
                $studentDetails->save();
                $this->cleanupOrphanedDummyParent($oldParentId);
            }

            return;
        }

        $parent = app(ParentMatchingService::class)->resolveParent((int) $studentDetails->institution_id, $data);
        if ($parent) {
            $oldParentId = $studentDetails->parent_id;

            if ($studentDetails->parent_id !== $parent->id) {
                $studentDetails->parent_id = $parent->id;
                $studentDetails->save();

                if ($oldParentId) {
                    $this->cleanupOrphanedDummyParent($oldParentId);
                }
            }

            if (! empty($data['parent_name']) && $parent->name !== $data['parent_name'] && $data['parent_name'] !== 'Parent') {
                $parent->update(['name' => $data['parent_name']]);
            }
        }
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
