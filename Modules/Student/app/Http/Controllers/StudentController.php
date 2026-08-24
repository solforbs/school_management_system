<?php

namespace Modules\Student\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\Devices;
use App\Models\User;
use App\Services\ImageCompressionService;
use App\Services\ProfilePhotoResolver;
use App\Services\ZKTecoUserSyncService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Models\StudentDetails;

class StudentController extends Controller
{
    use Sortable;

    public function __construct(
        private readonly ZKTecoUserSyncService $syncService,
        private readonly ProfilePhotoResolver $photoResolver,
        private readonly ImageCompressionService $imageCompressor,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('view student'), 403);

        $institution = currentInstitution();
        $institutionId = $institution?->id ?? 0;

        $baseQuery = StudentDetails::query();

        if (Auth::user()->hasRole('Parent')) {
            $baseQuery->where('parent_id', Auth::id());
        } elseif (! isAdmin()) {
            $baseQuery->where('institution_id', $institutionId);
        }

        // Compute Aggregated KPIs at the database level with ZERO model hydration overhead
        $totalStudents = (clone $baseQuery)->count();
        $totalActive = (clone $baseQuery)->where('enrollment_status', 'active')->count();
        $totalClasses = (clone $baseQuery)->distinct('class_id')->whereNotNull('class_id')->count('class_id');

        // Query builder with relations for the paginated view
        $query = (clone $baseQuery)->with(['student', 'institution', 'schoolClass']);

        // Search Filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('admission_number', 'LIKE', "%{$search}%")
                    ->orWhere('student_number', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('guardian_name', 'LIKE', "%{$search}%")
                    ->orWhere('guardian_phone', 'LIKE', "%{$search}%")
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Class Filter
        if ($classId = $request->input('class_id')) {
            $query->where('class_id', $classId);
        }

        // Enrollment Status Filter
        if ($status = $request->input('status')) {
            $query->where('enrollment_status', $status);
        }

        // Gender Filter
        if ($gender = $request->input('gender')) {
            $query->where('gender', $gender);
        }

        // Available classes for filter dropdown
        $classes = SchoolClass::where('institution_id', $institutionId)
            ->orderBy('name')
            ->get(['id', 'name', 'level']);

        $students = $this->applySort(
            $query,
            sortable: ['admission_number', 'gender', 'phone', 'enrollment_status', 'created_at'],
            defaultColumn: 'created_at',
            defaultDirection: 'desc',
        )->paginate(15)->withQueryString();

        return view('student::index', [
            'students' => $students,
            'totalStudents' => $totalStudents,
            'totalActive' => $totalActive,
            'totalClasses' => $totalClasses,
            'classes' => $classes,
            'institution' => $institution,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(Auth::user()->can('create student'), 403);

        $institutions = isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
        $institutionId = currentInstitution()?->id ?? 0;

        $classes = SchoolClass::where('institution_id', $institutionId)
            ->orderBy('name')
            ->get(['id', 'name', 'level']);

        $existingParents = User::role('Parent')->with('children')->orderBy('name')->get();

        return view('student::create', compact('institutions', 'existingParents', 'classes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create student'), 403);

        $validated = $request->validate([
            // Account
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',

            // Personal
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'profile_image' => 'nullable|image|max:2048',
            'admission_number' => 'nullable|string|max:100',
            'student_number' => 'nullable|string|max:100',
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'class_id' => ['nullable', 'exists:classes,id'],
            'enrollment_status' => 'nullable|in:active,expelled,graduated,suspended,transferred,withdrawn,dropped',

            // Address
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',

            // Parent
            'parent_id' => ['nullable', 'exists:users,id', function ($attribute, $value, $fail) {
                if ($value && ! User::find($value)?->hasRole('Parent')) {
                    $fail('The selected parent account is not valid.');
                }
            }],
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
        ]);

        $validated['institution_id'] = isAdmin()
            ? $validated['institution_id']
            : currentInstitution()?->id;

        abort_unless($validated['institution_id'], 422, 'No institution selected.');

        try {
            DB::transaction(function () use ($request, $validated) {
                $parent = null;
                $parentDetailsRow = null;

                if (! empty($validated['parent_id'])) {
                    $parent = User::find($validated['parent_id']);
                    if ($parent) {
                        $parentDetailsRow = ParentDetails::firstOrCreate(
                            ['parent_id' => $parent->id],
                            [
                                'parent_phone' => $validated['parent_phone'] ?? null,
                                'parent_occupation' => $validated['parent_occupation'] ?? null,
                            ]
                        );
                    }
                } elseif (! empty($validated['parent_name']) || ! empty($validated['parent_email']) || ! empty($validated['parent_phone'])) {
                    $parent = User::create([
                        'name' => $validated['parent_name'] ?? 'Parent',
                        'email' => $validated['parent_email'] ?? 'parent_'.time().'@example.com',
                        'password' => Hash::make($validated['parent_phone'] ?? 'password123'),
                    ]);
                    $parent->syncRoles('Parent');

                    $parentDetailsRow = ParentDetails::create([
                        'parent_id' => $parent->id,
                        'parent_phone' => $validated['parent_phone'] ?? null,
                        'parent_occupation' => $validated['parent_occupation'] ?? null,
                    ]);
                }

                // Create Student User
                $student = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);
                $student->syncRoles('Student');

                // Prepare Student Details Data
                $studentData = collect($validated)
                    ->except([
                        'name',
                        'email',
                        'password',
                        'is_active',
                        'parent_id',
                        'parent_name',
                        'parent_phone',
                        'profile_image',
                        'parent_email',
                        'parent_occupation',
                    ])
                    ->toArray();

                $studentData['student_id'] = $student->id;

                if ($parentDetailsRow) {
                    $studentData['parent_id'] = $parentDetailsRow->id;
                }

                $studentData['is_active'] = $request->boolean('is_active', true);
                $studentData['enrollment_status'] = $validated['enrollment_status'] ?? 'active';

                if ($request->hasFile('profile_image')) {
                    $studentData['profile_photo'] = $this->imageCompressor->store(
                        $request->file('profile_image'),
                        'students/photos',
                    );
                }

                $student->studentUserDetails()->create($studentData);
            });

            return redirect()->route('student.index')
                ->with('success', 'Student enrolled successfully!');
        } catch (Exception $e) {
            Log::error('Student creation failed: '.$e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to enroll student: '.$e->getMessage());
        }
    }

    /**
     * Show the specified resource.
     */
    public function show(User $student)
    {
        abort_unless(Auth::user()->can('view student'), 403);
        $this->authorizeStudentAccess($student);

        $student->load(['studentUserDetails.schoolClass', 'studentUserDetails.institution', 'studentParent']);
        $studentDetails = $student->studentUserDetails;

        return view('student::show', [
            'student' => $student,
            'studentDetails' => $studentDetails,
            'institution' => currentInstitution(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        abort_unless(Auth::user()->can('edit student'), 403);

        $student = User::whereId($id)->with(['studentParent', 'studentUserDetails.schoolClass', 'studentInstitution'])->first();
        abort_unless($student, 404);
        $this->authorizeStudentAccess($student);

        $institutionId = currentInstitution()?->id ?? 0;
        $classes = SchoolClass::where('institution_id', $institutionId)
            ->orderBy('name')
            ->get(['id', 'name', 'level']);

        return view('student::edit', compact('student', 'classes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update student'), 403);

        $student = User::findOrFail($id);
        $this->authorizeStudentAccess($student);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$student->id,
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'profile_image' => 'nullable|image|max:2048',
            'admission_number' => 'nullable|string|max:100',
            'student_number' => 'nullable|string|max:100',
            'class_id' => ['nullable', 'exists:classes,id'],
            'enrollment_status' => 'nullable|in:active,expelled,graduated,suspended,transferred,withdrawn,dropped',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'parent_name' => 'nullable|string|max:255',
            'parent_phone' => 'nullable|string|max:20',
            'parent_email' => 'nullable|email|max:255',
            'parent_occupation' => 'nullable|string|max:255',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_phone' => 'nullable|string|max:20',
            'guardian_email' => 'nullable|email|max:255',
            'guardian_relationship' => 'nullable|string|max:100',
            'medical_conditions' => 'nullable|string',
            'allergies' => 'nullable|string',
            'special_needs' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        try {
            DB::transaction(function () use ($request, $validated, $student) {
                // Update User info
                $userData = ['name' => $validated['name'], 'email' => $validated['email']];
                if (! empty($validated['password'])) {
                    $userData['password'] = Hash::make($validated['password']);
                }
                $student->update($userData);

                $studentDetails = StudentDetails::where('student_id', $student->id)->firstOrFail();

                $studentData = collect($validated)
                    ->except(['name', 'email', 'password', 'parent_name', 'parent_phone', 'parent_email', 'parent_occupation', 'profile_image'])
                    ->toArray();

                $studentData['is_active'] = $request->boolean('is_active', true);

                if ($request->hasFile('profile_image')) {
                    if ($studentDetails->profile_photo && Storage::disk('public')->exists($studentDetails->profile_photo)) {
                        Storage::disk('public')->delete($studentDetails->profile_photo);
                    }

                    $studentData['profile_photo'] = $this->imageCompressor->store(
                        $request->file('profile_image'),
                        'students/photos',
                    );
                }

                $studentDetails->update($studentData);

                if ($request->hasFile('profile_image')) {
                    $this->resyncPhotoIfAlreadySynced($studentDetails);
                }

                // Handle Parent User and Parent Details
                if (! empty($validated['parent_name']) || ! empty($validated['parent_email']) || ! empty($validated['parent_phone'])) {
                    $parentDetailsRow = null;
                    if ($studentDetails->parent_id) {
                        $parentDetailsRow = ParentDetails::find($studentDetails->parent_id);
                    }

                    $parentUser = null;
                    if ($parentDetailsRow && $parentDetailsRow->parent_id) {
                        $parentUser = User::find($parentDetailsRow->parent_id);
                    }

                    if ($parentUser) {
                        $parentUser->update([
                            'name' => $validated['parent_name'] ?? $parentUser->name,
                            'email' => $validated['parent_email'] ?? $parentUser->email,
                        ]);
                    } else {
                        $parentUser = User::create([
                            'name' => $validated['parent_name'] ?? 'Parent',
                            'email' => $validated['parent_email'] ?? 'parent_'.time().'@example.com',
                            'password' => Hash::make($validated['parent_phone'] ?? 'password123'),
                        ]);
                        $parentUser->syncRoles('Parent');
                    }

                    $parentDetailsRow = ParentDetails::updateOrCreate(
                        ['parent_id' => $parentUser->id],
                        [
                            'parent_phone' => $validated['parent_phone'] ?? null,
                            'parent_occupation' => $validated['parent_occupation'] ?? null,
                        ]
                    );

                    $studentDetails->parent_id = $parentDetailsRow->id;
                    $studentDetails->save();
                }
            });

            return redirect()->route('student.show', $student->id)
                ->with('success', 'Student updated successfully!');
        } catch (Exception $e) {
            Log::error('Student update failed: '.$e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update student: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        abort_unless(Auth::user()->can('delete student'), 403);

        $user = User::findOrFail($id);
        $this->authorizeStudentAccess($user);

        if ($user->studentUserDetails) {
            $user->studentUserDetails->delete();
        }
        $user->delete();

        return redirect()->route('student.index')->with('success', 'Student successfully removed from directory!');
    }

    /**
     * Guards against acting on a student outside the caller's own scope.
     */
    private function authorizeStudentAccess(User $student): void
    {
        if (isAdmin()) {
            return;
        }

        $user = Auth::user();

        if ($user->hasRole('Parent')) {
            abort_unless(
                StudentDetails::where('student_id', $student->id)->where('parent_id', $user->id)->exists(),
                403
            );

            return;
        }

        if ($user->hasRole('Student')) {
            abort_unless($student->id === $user->id, 403);

            return;
        }

        $studentInstitutionId = StudentDetails::where('student_id', $student->id)->value('institution_id');

        abort_unless($studentInstitutionId && $studentInstitutionId === currentInstitution()?->id, 403);
    }

    /**
     * Push the student's updated photo to active device(s).
     */
    private function resyncPhotoIfAlreadySynced(StudentDetails $studentDetails): void
    {
        $student = User::findOrFail($studentDetails->student_id);

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
            'device_count' => $devices->count(),
        ]);
    }
}
