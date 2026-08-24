<?php

namespace Modules\Teacher\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Institution\Models\Institution;
use Modules\Teacher\Models\TeacherDetails;

class TeacherController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('view teacher'), 403);

        $institution = currentInstitution();
        $institutionId = $institution?->id ?? 0;

        $baseQuery = TeacherDetails::query();
        if (! isAdmin()) {
            $baseQuery->where('institution_id', $institutionId);
        }

        // DB-level KPIs with zero hydration overhead
        $totalTeachers = (clone $baseQuery)->count();
        $totalActive = (clone $baseQuery)->where('is_active', true)->count();
        $totalDepartments = (clone $baseQuery)->whereNotNull('department')->where('department', '!=', '')->distinct()->count('department');

        // Get distinct departments list for dropdown
        $departments = (clone $baseQuery)->whereNotNull('department')->where('department', '!=', '')->distinct()->pluck('department')->sort()->values();

        $query = (clone $baseQuery)->with(['teacher', 'institution']);

        // Search Filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'LIKE', "%{$search}%")
                    ->orWhere('department', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('qualification', 'LIKE', "%{$search}%")
                    ->orWhereHas('teacher', function ($tq) use ($search) {
                        $tq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Department Filter
        if ($request->filled('department')) {
            $query->where('department', $request->input('department'));
        }

        // Status Filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $teachers = $this->applySort(
            $query,
            sortable: ['employee_number', 'department', 'phone', 'status', 'created_at'],
            defaultColumn: 'created_at',
            defaultDirection: 'desc',
        )->paginate(12)->withQueryString();

        return view('teacher::index', [
            'teachers' => $teachers,
            'totalTeachers' => $totalTeachers,
            'totalActive' => $totalActive,
            'totalDepartments' => $totalDepartments,
            'departments' => $departments,
            'institution' => $institution,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(Auth::user()->can('create teacher'), 403);

        $institutions = isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
        $institution = currentInstitution();

        return view('teacher::create', compact('institutions', 'institution'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create teacher'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'phone' => 'nullable|string|max:20',
            'employee_number' => 'nullable|string|max:100|unique:teacher_details,employee_number',
            'department' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'address' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,on_leave,suspended,resigned,terminated',
            'notes' => 'nullable|string',
        ]);

        $institutionId = isAdmin() ? $validated['institution_id'] : currentInstitution()?->id;

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->assignRole('Teacher');

            TeacherDetails::create([
                'teacher_id' => $user->id,
                'institution_id' => $institutionId,
                'phone' => $validated['phone'] ?? null,
                'employee_number' => $validated['employee_number'] ?? null,
                'department' => $validated['department'] ?? null,
                'qualification' => $validated['qualification'] ?? null,
                'hire_date' => $validated['hire_date'] ?? null,
                'address' => $validated['address'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'status' => $validated['status'] ?? 'active',
                'notes' => $validated['notes'] ?? null,
            ]);

            DB::commit();

            return redirect()->route('teacher.index')->with('success', 'Teacher added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating teacher: '.$e->getMessage());

            return back()->withInput()->withErrors(['error' => 'An error occurred while creating the teacher. Please try again.']);
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view teacher'), 403);

        $teacherDetails = TeacherDetails::with(['teacher', 'institution'])
            ->where('teacher_id', $id)
            ->firstOrFail();

        $institution = currentInstitution();

        return view('teacher::show', compact('teacherDetails', 'institution'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        abort_unless(Auth::user()->can('edit teacher'), 403);

        $teacher = TeacherDetails::with('teacher')->where('teacher_id', $id)->firstOrFail();
        $institutions = isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
        $institution = currentInstitution();

        return view('teacher::edit', compact('teacher', 'institutions', 'institution'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('edit teacher'), 403);

        $teacherDetails = TeacherDetails::where('teacher_id', $id)->firstOrFail();
        $user = $teacherDetails->teacher;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8',
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'phone' => 'nullable|string|max:20',
            'employee_number' => 'nullable|string|max:100|unique:teacher_details,employee_number,'.$teacherDetails->id,
            'department' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'address' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|string|in:active,on_leave,suspended,resigned,terminated',
            'notes' => 'nullable|string',
        ]);

        $institutionId = isAdmin() ? $validated['institution_id'] : currentInstitution()?->id;

        DB::beginTransaction();

        try {
            $userUpdates = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            if (! empty($validated['password'])) {
                $userUpdates['password'] = Hash::make($validated['password']);
            }

            $user->update($userUpdates);

            $teacherDetails->update([
                'institution_id' => $institutionId,
                'phone' => $validated['phone'] ?? null,
                'employee_number' => $validated['employee_number'] ?? null,
                'department' => $validated['department'] ?? null,
                'qualification' => $validated['qualification'] ?? null,
                'hire_date' => $validated['hire_date'] ?? null,
                'address' => $validated['address'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'status' => $validated['status'] ?? $teacherDetails->status,
                'notes' => $validated['notes'] ?? null,
            ]);

            DB::commit();

            return redirect()->route('teacher.show', $user->id)->with('success', 'Teacher updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating teacher: '.$e->getMessage());

            return back()->withInput()->withErrors(['error' => 'An error occurred while updating the teacher.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete teacher'), 403);

        $teacherDetails = TeacherDetails::where('teacher_id', $id)->firstOrFail();
        $user = $teacherDetails->teacher;

        DB::beginTransaction();

        try {
            $teacherDetails->delete();
            if ($user) {
                $user->delete();
            }

            DB::commit();

            return redirect()->route('teacher.index')->with('success', 'Teacher removed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting teacher: '.$e->getMessage());

            return back()->withErrors(['error' => 'An error occurred while deleting the teacher.']);
        }
    }
}
