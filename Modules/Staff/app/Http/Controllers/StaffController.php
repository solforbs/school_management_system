<?php

namespace Modules\Staff\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Institution\Models\Institution;
use Modules\Staff\Actions\SaveStaff;
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
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create staff'), 403);

        $wantsAccount = $request->boolean('create_account');
        $validated = $request->validate(SaveStaff::rules($wantsAccount));

        $institutionId = isAdmin()
            ? ($request->integer('institution_id') ?: currentInstitution()?->id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        try {
            SaveStaff::create($validated, $institutionId, $wantsAccount);

            return redirect()->route('staff.index')->with('success', 'Staff member created successfully!');
        } catch (\Exception $e) {
            Log::error('Staff creation failed: '.$e->getMessage());

            return redirect()->back()->withInput()
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
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StaffDetails $staff)
    {
        abort_unless(Auth::user()->can('update staff'), 403);

        $this->authorizeAccessTo($staff);

        $wantsAccount = $request->boolean('create_account');
        $validated = $request->validate(SaveStaff::rules($wantsAccount, $staff));

        $institutionId = isAdmin()
            ? ($request->integer('institution_id') ?: $staff->institution_id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        try {
            SaveStaff::update($staff, $validated, $institutionId, $wantsAccount);

            return redirect()->route('staff.show', $staff)->with('success', 'Staff member updated successfully!');
        } catch (\Exception $e) {
            Log::error('Staff update failed: '.$e->getMessage());

            return redirect()->back()->withInput()
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
