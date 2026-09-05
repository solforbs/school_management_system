<?php

namespace Modules\Institution\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Export\TableExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Institution\Actions\SaveInstitution;
use Modules\Institution\Models\Institution;

class InstitutionController extends Controller
{
    use Sortable;

    private const SORTABLE = ['name', 'code', 'phone', 'email', 'created_at'];

    private const HEADERS = ['Name', 'Code', 'Email', 'Phone', 'City', 'Students', 'Teachers', 'Status', 'Created At'];

    public User $user;

    public function __construct(
        private readonly SaveInstitution $saveInstitution,
    ) {
        $this->user = Auth::user();
    }

    /**
     * Export the institutions list (Excel/PDF), mirroring the same
     * Admin-sees-all vs Director-sees-own-institution branching the
     * Livewire index component applies.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless($this->user->can('export institution'), 403);

        $sort = $request->string('sort')->value();
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'created_at';
        $direction = $request->string('direction')->lower()->value() === 'asc' ? 'asc' : 'desc';
        $search = $request->string('search')->value();

        $query = Institution::withCount(['studentDetails', 'teacherDetails']);

        if (! isAdmin()) {
            $query->where('user_id', $this->user->id);
        }

        $query->when($search !== '', function ($q) use ($search) {
            $term = '%'.$search.'%';

            $q->where(fn ($q2) => $q2->where('name', 'like', $term)
                ->orWhere('code', 'like', $term)
                ->orWhere('email', 'like', $term));
        })->orderBy($sort, $direction);

        $rows = $query->get()->map(fn (Institution $institution) => $this->toExportRow($institution));

        $filename = 'institutions-'.now()->format('Y-m-d');
        $format = $request->string('format')->lower()->value();

        return $format === 'pdf'
            ? $exporter->pdf($rows, self::HEADERS, $filename, 'Institutions')
            : $exporter->excel($rows, self::HEADERS, $filename, 'Institutions');
    }

    /**
     * Flatten an institution into the row shape shared by every export
     * format (Excel, PDF).
     *
     * @return array<int, string>
     */
    private function toExportRow(Institution $institution): array
    {
        return [
            $institution->name,
            $institution->code ?? 'N/A',
            $institution->email ?? 'N/A',
            $institution->phone ?? 'N/A',
            $institution->city ?? $institution->county ?? 'N/A',
            (string) ($institution->student_details_count ?? 0),
            (string) ($institution->teacher_details_count ?? 0),
            $institution->is_approved ? 'Approved' : 'Pending',
            $institution->created_at?->format('Y-m-d') ?? '',
        ];
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
     * Revoke a previously-approved institution, locking its Director back
     * out of the dashboard (EnsureInstitutionApproved) until an Admin
     * approves it again. For a school that turns out not to have been
     * vetted properly, or needs to be paused - not a substitute for
     * destroy(), which is permanent and Admin-only for a different reason.
     */
    public function disapprove(Institution $institution)
    {
        abort_unless(isAdmin(), 403);

        $institution->update([
            'is_approved' => false,
            'approved_at' => null,
            'approved_by_id' => null,
        ]);

        return back()->with('success', 'Institution "'.$institution->name.'" is no longer approved. Its Director will be locked out of the dashboard until it is approved again.');
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
