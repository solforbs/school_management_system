<?php

namespace Modules\Parent\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Export\TableExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Parent\Actions\SaveParent;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Models\StudentDetails;

class ParentController extends Controller
{
    use Sortable;

    private const HEADERS = ['Parent Name', 'Email', 'Phone', 'Occupation', 'Linked Students', 'Verified'];

    /**
     * Export the parent/guardian directory (Excel/PDF) with the same
     * filters and institution scoping the Livewire index component applies.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('export parent'), 403);

        $filter = $request->string('filter')->value();

        $query = User::role('Parent')
            ->with(['parent', 'children.student', 'children.institution'])
            ->when(! isAdmin(), function ($q) {
                // Owned by this school, or with a child enrolled here.
                $institutionId = currentInstitutionId();

                $q->where(function ($sq) use ($institutionId) {
                    $sq->whereHas('parent', fn ($pq) => $pq->where('institution_id', $institutionId))
                        ->orWhereHas('children', fn ($cq) => $cq->where('institution_id', $institutionId));
                });
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->value().'%';

                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('parent', fn ($pq) => $pq->where('parent_phone', 'like', $term)->orWhere('parent_occupation', 'like', $term))
                        ->orWhereHas('children.student', fn ($sq) => $sq->where('name', 'like', $term)->orWhere('admission_number', 'like', $term));
                });
            })
            ->when($filter === 'with_students', fn ($q) => $q->has('children'))
            ->when($filter === 'no_students', fn ($q) => $q->doesntHave('children'))
            ->when($filter === 'multi_child', fn ($q) => $q->has('children', '>', 1))
            ->orderBy('name');

        $rows = $query->get()->map(fn (User $parent) => $this->toExportRow($parent));

        $filename = 'parents-'.now()->format('Y-m-d');
        $format = $request->string('format')->lower()->value();

        return $format === 'pdf'
            ? $exporter->pdf($rows, self::HEADERS, $filename, 'Parent Directory')
            : $exporter->excel($rows, self::HEADERS, $filename, 'Parent Directory');
    }

    /**
     * @return array<int, string>
     */
    private function toExportRow(User $parent): array
    {
        $studentNames = $parent->children->map(fn ($c) => $c->student?->name)->filter()->join(', ');

        return [
            $parent->name,
            $parent->email,
            $parent->parent?->parent_phone ?? 'N/A',
            $parent->parent?->parent_occupation ?? 'Guardian',
            $studentNames !== '' ? $studentNames : 'None',
            $parent->email_verified_at ? 'Verified' : 'Unverified',
        ];
    }

    /**
     * Display a listing of the resource.
     */
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create parent'), 403);

        $validated = $request->validate(SaveParent::createRules());

        try {
            SaveParent::create($validated, $this->unlinkedStudents(), currentInstitution()?->id);

            return redirect()->route('parent.index')->with('success', 'Parent created successfully!');
        } catch (\Exception $e) {
            Log::error('Parent creation failed: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to create parent: '.$e->getMessage());
        }
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view parent'), 403);

        $parent = User::role('Parent')
            ->with(['parent', 'children.student', 'children.institution'])
            ->findOrFail($id);

        $this->authorizeAccessTo($parent);

        $parent->setRelation('children', $this->sortCollection(
            $parent->children,
            sortable: [
                'name' => fn ($child) => $child->student?->name,
                'admission_number' => 'admission_number',
                'institution' => fn ($child) => $child->institution?->name,
            ],
            defaultColumn: 'admission_number',
            defaultDirection: 'asc',
        ));

        return view('parent::show', compact('parent'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update parent'), 403);

        $parent = User::role('Parent')->findOrFail($id);
        $this->authorizeAccessTo($parent);

        $validated = $request->validate(SaveParent::updateRules($parent));

        try {
            SaveParent::update($parent, $validated, $this->unlinkedStudents());

            return redirect()->route('parent.show', $parent->id)->with('success', 'Parent updated successfully!');
        } catch (\Exception $e) {
            Log::error('Parent update failed: '.$e->getMessage());

            return redirect()->back()->withInput()
                ->with('error', 'Failed to update parent: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete parent'), 403);

        $parent = User::role('Parent')->findOrFail($id);

        $this->authorizeAccessTo($parent);

        DB::transaction(function () use ($parent) {
            // Unlink children rather than deleting them.
            StudentDetails::where('parent_id', $parent->id)->update(['parent_id' => null]);

            ParentDetails::where('parent_id', $parent->id)->delete();
            $parent->delete();
        });

        return redirect()->route('parent.index')->with('success', 'Parent removed successfully!');
    }

    /**
     * Students not currently linked to any parent, scoped to the viewer's
     * institution(s) unless they're an Admin.
     */
    private function unlinkedStudents()
    {
        $query = StudentDetails::whereNull('parent_id')->with(['student', 'institution']);

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query;
    }

    /**
     * Ensure a non-admin viewer only manages parents belonging to their
     * currently active institution.
     *
     * Ownership is the institution stamped on the parent's own row, or any
     * school one of their children is enrolled at. It is deliberately not
     * "nobody owns them, so anybody may": that let every Director read, edit
     * and delete any parent who had no student linked yet.
     */
    private function authorizeAccessTo(User $parent): void
    {
        if (isAdmin()) {
            return;
        }

        $activeInstitutionId = currentInstitution()?->id;

        if ($activeInstitutionId === null) {
            abort(403);
        }

        $ownsRow = ParentDetails::where('parent_id', $parent->id)
            ->where('institution_id', $activeInstitutionId)
            ->exists();

        $hasChildHere = StudentDetails::where('parent_id', $parent->id)
            ->where('institution_id', $activeInstitutionId)
            ->exists();

        abort_unless($ownsRow || $hasChildHere, 403);
    }
}
