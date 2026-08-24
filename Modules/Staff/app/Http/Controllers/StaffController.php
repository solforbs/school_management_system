<?php

namespace Modules\Staff\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Institution\Models\Institution;
use Modules\Staff\Models\StaffDetails;

class StaffController extends Controller
{
    use Sortable;

    /**
     * Roles a staff member can be given a login for. Only the Accountant
     * has a permission set of its own - every other staff role is a record
     * in the school's books, not a system user.
     *
     * @var string[]
     */
    private const SYSTEM_ROLES = ['Accountant'];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        abort_unless(Auth::user()->can('view staff'), 403);

        $institution = currentInstitution();
        $institutionId = $institution?->id ?? 0;

        $query = StaffDetails::with(['user', 'institution']);

        if (! isAdmin()) {
            $query->where('institution_id', $institutionId);
        }

        $search = request('search');
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('staff_number', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('job_title', 'LIKE', "%{$search}%")
                    ->orWhere('department', 'LIKE', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'LIKE', "%{$search}%");
                    });
            });
        }

        $department = request('department');
        if (! empty($department)) {
            $query->where('department', $department);
        }

        $status = request('status');
        if (! empty($status)) {
            $query->where('status', $status);
        }

        $baseQuery = StaffDetails::query();
        if (! isAdmin()) {
            $baseQuery->where('institution_id', $institutionId);
        }

        $totalStaffCount = (clone $baseQuery)->count();
        $departments = (clone $baseQuery)->whereNotNull('department')->where('department', '!=', '')->distinct()->pluck('department')->sort()->values();
        $deptCount = count($departments);
        $totalPayroll = (float) (clone $baseQuery)->sum('salary');

        $staff = $this->applySort(
            $query,
            sortable: ['name', 'staff_number', 'job_title', 'department', 'salary'],
            defaultColumn: 'created_at',
            defaultDirection: 'desc',
        )->paginate(15)->withQueryString();

        return view('staff::index', compact('staff', 'departments', 'totalStaffCount', 'deptCount', 'totalPayroll', 'institution'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(Auth::user()->can('create staff'), 403);

        $institutions = isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();

        return view('staff::create', [
            'institutions' => $institutions,
            'systemRoles' => self::SYSTEM_ROLES,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create staff'), 403);

        $validated = $request->validate($this->rules($request));

        $validated['institution_id'] = isAdmin() ? $validated['institution_id'] : currentInstitution()?->id;

        abort_unless($validated['institution_id'], 422, 'No institution selected.');

        try {
            DB::transaction(function () use ($request, $validated) {
                $staffData = collect($validated)
                    ->except(['password', 'system_role', 'create_account'])
                    ->toArray();
                $staffData['is_active'] = $request->boolean('is_active', true);

                $staff = StaffDetails::create($staffData);

                if ($request->boolean('create_account')) {
                    $this->attachAccountTo($staff, $validated['password'], $validated['system_role']);
                }
            });

            return redirect()->route('staff.index')->with('success', 'Staff member created successfully!');
        } catch (\Exception $e) {
            Log::error('Staff creation failed: '.$e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create staff member: '.$e->getMessage());
        }
    }

    /**
     * Show the specified resource.
     */
    public function show(StaffDetails $staff)
    {
        abort_unless(Auth::user()->can('view staff'), 403);

        $this->authorizeAccessTo($staff);

        $staff->load(['user', 'institution']);

        $payments = $staff->payments()->latest('period')->take(12)->get();

        return view('staff::show', compact('staff', 'payments'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StaffDetails $staff)
    {
        abort_unless(Auth::user()->can('edit staff'), 403);

        $this->authorizeAccessTo($staff);

        $institutions = isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();

        return view('staff::edit', [
            'staff' => $staff->load('user'),
            'institutions' => $institutions,
            'systemRoles' => self::SYSTEM_ROLES,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StaffDetails $staff)
    {
        abort_unless(Auth::user()->can('update staff'), 403);

        $this->authorizeAccessTo($staff);

        $validated = $request->validate($this->rules($request, $staff));

        $validated['institution_id'] = isAdmin() ? $validated['institution_id'] : currentInstitution()?->id;

        abort_unless($validated['institution_id'], 422, 'No institution selected.');

        try {
            DB::transaction(function () use ($request, $validated, $staff) {
                $staffData = collect($validated)
                    ->except(['password', 'system_role', 'create_account'])
                    ->toArray();
                $staffData['is_active'] = $request->boolean('is_active');

                $staff->update($staffData);

                if ($staff->user) {
                    $staff->user->update([
                        'name' => $staff->name,
                        'email' => $staff->email ?? $staff->user->email,
                    ]);

                    if (filled($validated['password'] ?? null)) {
                        $staff->user->update(['password' => Hash::make($validated['password'])]);
                    }

                    if (filled($validated['system_role'] ?? null)) {
                        $staff->user->syncRoles($validated['system_role']);
                    }
                } elseif ($request->boolean('create_account')) {
                    $this->attachAccountTo($staff, $validated['password'], $validated['system_role']);
                }
            });

            return redirect()->route('staff.show', $staff)->with('success', 'Staff member updated successfully!');
        } catch (\Exception $e) {
            Log::error('Staff update failed: '.$e->getMessage());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update staff member: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StaffDetails $staff)
    {
        abort_unless(Auth::user()->can('delete staff'), 403);

        $this->authorizeAccessTo($staff);

        DB::transaction(function () use ($staff) {
            $user = $staff->user;

            $staff->delete();

            // Their login exists only to serve the staff record - it goes
            // with it, so a removed accountant can't still sign in.
            $user?->delete();
        });

        return redirect()->route('staff.index')->with('success', 'Staff member removed successfully!');
    }

    /**
     * Validation rules shared by store and update. On update, uniqueness
     * ignores the record being edited and a password is only required when
     * an account is being added.
     *
     * @return array<string, mixed>
     */
    private function rules(Request $request, ?StaffDetails $staff = null): array
    {
        $needsAccount = $request->boolean('create_account') && ! $staff?->user_id;

        return [
            'name' => 'required|string|max:255',
            'email' => array_filter([
                $needsAccount ? 'required' : 'nullable',
                'email',
                'max:255',
                // Only a staff member who signs in needs an email nobody
                // else already logs in with - for the rest it's just a
                // contact detail.
                $needsAccount ? 'unique:users,email' : null,
                $staff?->user_id ? 'unique:users,email,'.$staff->user_id : null,
            ]),
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'phone' => 'nullable|string|max:20',
            'staff_number' => 'nullable|string|max:100|unique:staff_details,staff_number'.($staff ? ','.$staff->id : ''),
            'job_title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'employment_type' => 'nullable|in:full_time,part_time,contract,volunteer',
            'hire_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'address' => 'nullable|string',
            'status' => 'nullable|in:active,on_leave,suspended,resigned,terminated',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',

            // System access
            'create_account' => 'nullable|boolean',
            'password' => [$needsAccount ? 'required' : 'nullable', 'string', 'min:8'],
            'system_role' => [$needsAccount ? 'required' : 'nullable', 'in:'.implode(',', self::SYSTEM_ROLES)],
        ];
    }

    /**
     * Give a staff member a login with the requested system role.
     */
    private function attachAccountTo(StaffDetails $staff, string $password, string $role): void
    {
        $user = User::create([
            'name' => $staff->name,
            'email' => $staff->email,
            'password' => Hash::make($password),
        ]);
        $user->syncRoles($role);

        $staff->update(['user_id' => $user->id]);
    }

    /**
     * Ensure a non-admin viewer only manages staff from their currently
     * active institution.
     */
    private function authorizeAccessTo(StaffDetails $staff): void
    {
        if (isAdmin()) {
            return;
        }

        abort_unless($staff->institution_id === currentInstitution()?->id, 403);
    }
}
