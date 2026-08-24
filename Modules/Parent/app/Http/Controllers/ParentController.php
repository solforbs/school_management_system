<?php

namespace Modules\Parent\Http\Controllers;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Models\StudentDetails;

class ParentController extends Controller
{
    use Sortable;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();

        abort_unless($user->can('view parent'), 403);

        // Check if user is admin
        if (isAdmin()) {
            // Admin gets all parents from all institutions
            $parents = $this->applySort(
                User::role('Parent')
                    ->with(['children' => function ($query) {
                        $query->with('student', 'institution');
                    }, 'parent']),
                sortable: ['name'],
                defaultColumn: 'created_at',
                defaultDirection: 'desc',
            )->get();

            $institution = null;

            return view('parent::index', compact('parents', 'institution'));
        }

        // Regular user - get parents from their currently active institution
        $institution = currentInstitution();

        // Handle case where user has no institution
        if (! $institution) {
            return view('parent::index', [
                'parents' => collect(),
                'institution' => null,
            ])->with('error', 'No institution found for this user.');
        }

        $query = $institution->parents()
            ->with(['children' => function ($query) use ($institution) {
                $query->where('institution_id', $institution->id)
                    ->with('student');
            }, 'parent']);

        $search = request('search');
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhereHas('parent', function ($pq) use ($search) {
                        $pq->where('parent_phone', 'LIKE', "%{$search}%")
                            ->orWhere('parent_occupation', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('children', function ($cq) use ($search) {
                        $cq->where('admission_number', 'LIKE', "%{$search}%")
                            ->orWhere('student_number', 'LIKE', "%{$search}%")
                            ->orWhere('student_id', $search)
                            ->orWhereHas('student', function ($sq) use ($search) {
                                $sq->where('name', 'LIKE', "%{$search}%");
                            });
                    });
            });
        }

        $filter = request('filter');
        if ($filter === 'with_students') {
            $query->whereHas('children');
        } elseif ($filter === 'multi_child') {
            $query->has('children', '>', 1);
        }

        $parents = $this->applySort(
            $query,
            sortable: ['name'],
            defaultColumn: 'created_at',
            defaultDirection: 'desc',
        )->paginate(15)->withQueryString();

        return view('parent::index', compact('parents', 'institution'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(Auth::user()->can('create parent'), 403);

        $availableStudents = $this->unlinkedStudents()->get();

        return view('parent::create', compact('availableStudents'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create parent'), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'parent_phone' => 'nullable|string|max:20',
            'parent_occupation' => 'nullable|string|max:255',
            'children' => 'nullable|array',
            'children.*' => 'exists:student_details,student_id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $parent = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                ]);
                $parent->syncRoles('Parent');

                $parent->parent()->create([
                    'parent_id' => $parent->id,
                    'parent_phone' => $validated['parent_phone'] ?? null,
                    'parent_occupation' => $validated['parent_occupation'] ?? null,
                ]);

                if (! empty($validated['children'])) {
                    $this->unlinkedStudents()
                        ->whereIn('student_id', $validated['children'])
                        ->update(['parent_id' => $parent->id]);
                }
            });

            return redirect()->route('parent.index')->with('success', 'Parent created successfully!');
        } catch (\Exception $e) {
            Log::error('Parent creation failed: '.$e->getMessage());

            return redirect()->back()
                ->withInput()
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
    public function edit($id)
    {
        abort_unless(Auth::user()->can('edit parent'), 403);

        $parent = User::role('Parent')->with(['parent', 'children.student'])->findOrFail($id);

        $this->authorizeAccessTo($parent);

        $availableStudents = $this->unlinkedStudents()->get();

        return view('parent::edit', compact('parent', 'availableStudents'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('update parent'), 403);

        $parent = User::role('Parent')->findOrFail($id);

        $this->authorizeAccessTo($parent);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$parent->id,
            'parent_phone' => 'nullable|string|max:20',
            'parent_occupation' => 'nullable|string|max:255',
            'children' => 'nullable|array',
            'children.*' => 'exists:student_details,student_id',
        ]);

        try {
            DB::transaction(function () use ($parent, $validated) {
                $parent->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                ]);

                ParentDetails::updateOrCreate(
                    ['parent_id' => $parent->id],
                    [
                        'parent_phone' => $validated['parent_phone'] ?? null,
                        'parent_occupation' => $validated['parent_occupation'] ?? null,
                    ]
                );

                $selected = $validated['children'] ?? [];

                // Unlink children that were deselected.
                StudentDetails::where('parent_id', $parent->id)
                    ->whereNotIn('student_id', $selected)
                    ->update(['parent_id' => null]);

                // Link newly selected children (only ones that are currently
                // unlinked, so this can't steal a student from another parent).
                if (! empty($selected)) {
                    $this->unlinkedStudents()
                        ->whereIn('student_id', $selected)
                        ->update(['parent_id' => $parent->id]);
                }
            });

            return redirect()->route('parent.show', $parent->id)->with('success', 'Parent updated successfully!');
        } catch (\Exception $e) {
            Log::error('Parent update failed: '.$e->getMessage());

            return redirect()->back()
                ->withInput()
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
            $parent = User::findOrFail($parent->id)->first();
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
