<?php

namespace Modules\Institution\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Institution\Actions\SaveInstitution;
use Modules\Institution\Models\Institution;

class InstitutionController extends Controller
{
    use Sortable;

    public User $user;

    public function __construct(
        private readonly SaveInstitution $saveInstitution,
    ) {
        $this->user = Auth::user();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_if(isAdmin(), 403);

        $data = $request->validate(SaveInstitution::createRules());

        $this->saveInstitution->create($data, $request->user(), $request->file('logo'));

        return redirect()->route('institution.index')
            ->with('success', 'Institution created successfully! It will be reviewed by an Admin before it becomes fully active.');
    }

    /**
     * Show the specified resource.
     */
    public function show(Institution $institution)
    {
        abort_unless(
            $this->user->can('view institution') && (isAdmin() || $institution->user_id === $this->user->id),
            403
        );

        return view('institution::show', compact('institution'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $institution = Institution::findOrFail($id);

        abort_unless(
            $this->user->can('update institution') && (isAdmin() || $institution->user_id === $this->user->id),
            403
        );

        $validated = $request->validate(SaveInstitution::updateRules($institution));

        try {
            $this->saveInstitution->update($institution, $validated, $request->file('logo'));

            return redirect()->route('institution.edit', $institution->id)
                ->with('success', 'Institution "'.$institution->name.'" has been updated successfully.');
        } catch (\Throwable $e) {
            // \Throwable, not just \Exception, so a logo upload failure of
            // any kind is surfaced instead of silently producing a save with
            // no logo and no visible error.
            Log::error('Institution update failed', [
                'institution_id' => $id,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return redirect()->back()->withInput()
                ->with('error', 'An error occurred while updating the institution: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Institution $institution)
    {
        // Only the platform owner may de-register a school.
        abort_unless(isAdmin() && $this->user->can('delete institution'), 403);

        $institution->delete();

        return redirect()->route('institution.index');
    }

    /**
     * Approve a self-created institution, unlocking full access for its
     * Director. Admins review and approve; they never create institutions
     * themselves.
     */
    public function approve(Institution $institution)
    {
        abort_unless(isAdmin(), 403);

        $institution->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by_id' => $this->user->id,
        ]);

        return back()->with('success', 'Institution "'.$institution->name.'" has been approved.');
    }

    /**
     * Set which of a Director's institutions the rest of the system
     * (dashboard, billing, modules) runs as for them. Remembered across
     * logins until they choose a different one.
     */
    public function choose(Institution $institution)
    {
        abort_unless($institution->user_id === $this->user->id, 403);

        $this->user->update(['active_institution_id' => $institution->id]);

        return redirect()->route('dashboard')
            ->with('success', 'Now managing "'.$institution->name.'".');
    }
}
