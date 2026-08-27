<?php

namespace Modules\Parent\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
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
            SaveParent::create($validated, $this->unlinkedStudents());

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
     * Ensure a non-admin viewer only manages parents with a child in their
     * currently active institution.
     */
    private function authorizeAccessTo(User $parent): void
    {
        if (isAdmin()) {
            return;
        }

        $activeInstitutionId = currentInstitution()?->id;

        $children = StudentDetails::where('parent_id', $parent->id)->get();

        // A parent with no children at all isn't claimed by any institution
        // yet, so any Director can manage/link them. Otherwise at least one
        // child must be in the viewer's active institution.
        $accessible = $children->isEmpty() || $children->where('institution_id', $activeInstitutionId)->isNotEmpty();

        abort_unless($accessible, 403);
    }
}
