<?php

namespace Modules\Expenditure\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Expenditure\Models\Expenditure;
use Modules\Expenditure\Models\ExpenditureCategory;

/**
 * The headings a school files its spending under. Small enough to manage
 * from a single page: the list, an inline add form, and a one-click load of
 * the usual set.
 */
class ExpenditureCategoryController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create expenditure'), 403);

        $institutionId = $this->institutionId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        if ($this->nameTaken($institutionId, $validated['name'])) {
            return back()->withInput()->with('error', 'A category called "'.$validated['name'].'" already exists.');
        }

        ExpenditureCategory::create($validated + [
            'institution_id' => $institutionId,
            'is_active' => true,
        ]);

        return redirect()->route('expenditure.categories.index')->with('success', 'Category added.');
    }

    public function update(Request $request, int $id)
    {
        abort_unless(Auth::user()->can('edit expenditure') || Auth::user()->can('update expenditure'), 403);

        $category = $this->scopedCategory($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($this->nameTaken($category->institution_id, $validated['name'], $category->id)) {
            return back()->withInput()->with('error', 'A category called "'.$validated['name'].'" already exists.');
        }

        $category->update($validated + ['is_active' => (bool) $request->boolean('is_active')]);

        return redirect()->route('expenditure.categories.index')->with('success', 'Category updated.');
    }

    /**
     * Create whichever of the standard headings the school doesn't have
     * yet. Additive on purpose: run twice and nothing is duplicated, and a
     * school that has renamed or removed one keeps its own arrangement.
     */
    public function loadDefaults()
    {
        abort_unless(Auth::user()->can('create expenditure'), 403);

        $institutionId = $this->institutionId();

        $existing = ExpenditureCategory::where('institution_id', $institutionId)
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower($name));

        $added = 0;

        foreach (ExpenditureCategory::DEFAULTS as $name => $description) {
            if ($existing->contains(mb_strtolower($name))) {
                continue;
            }

            ExpenditureCategory::create([
                'institution_id' => $institutionId,
                'name' => $name,
                'description' => $description,
                'is_active' => true,
            ]);

            $added++;
        }

        return redirect()->route('expenditure.categories.index')
            ->with('success', $added > 0 ? $added.' categories added.' : 'Every standard category is already set up.');
    }

    public function destroy(int $id)
    {
        abort_unless(Auth::user()->can('delete expenditure'), 403);

        $category = $this->scopedCategory($id);

        // Spending already filed here keeps its history - the category is
        // retired instead of deleted so past records don't quietly lose
        // their heading.
        if (Expenditure::where('expenditure_category_id', $category->id)->exists()) {
            $category->update(['is_active' => false]);

            return redirect()->route('expenditure.categories.index')
                ->with('success', 'Category retired. Spending already filed under it keeps its heading.');
        }

        $category->delete();

        return redirect()->route('expenditure.categories.index')->with('success', 'Category removed.');
    }

    private function institutionId(): int
    {
        $institutionId = currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        return $institutionId;
    }

    private function nameTaken(int $institutionId, string $name, ?int $ignoring = null): bool
    {
        return ExpenditureCategory::where('institution_id', $institutionId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring))
            ->exists();
    }

    private function scopedCategory(int $id): ExpenditureCategory
    {
        $category = ExpenditureCategory::findOrFail($id);

        if (! isAdmin()) {
            abort_unless($category->institution_id === currentInstitution()?->id, 403);
        }

        return $category;
    }
}
