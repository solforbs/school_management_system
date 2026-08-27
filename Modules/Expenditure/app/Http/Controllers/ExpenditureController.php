<?php

namespace Modules\Expenditure\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Expenditure\Actions\SaveExpenditure;
use Modules\Expenditure\Models\Expenditure;

/**
 * What the school spends. Kept by the Accountant and read by the Director -
 * the outgoing counterpart to fee collection.
 */
class ExpenditureController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(Auth::user()->can('create expenditure'), 403);

        $validated = $request->validate(SaveExpenditure::rules());

        SaveExpenditure::handle($validated, $this->institutionId(), recordedBy: Auth::id());

        return redirect()->route('expenditure.index')->with('success', 'Expenditure recorded successfully!');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        abort_unless(Auth::user()->can('view expenditure'), 403);

        $expenditure = $this->scopedExpenditure($id);

        return view('expenditure::show', compact('expenditure'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        abort_unless(Auth::user()->can('edit expenditure') || Auth::user()->can('update expenditure'), 403);

        $expenditure = $this->scopedExpenditure($id);
        $validated = $request->validate(SaveExpenditure::rules());

        SaveExpenditure::handle($validated, $this->institutionId($expenditure), $expenditure);

        return redirect()->route('expenditure.show', $expenditure->id)->with('success', 'Expenditure updated!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        abort_unless(Auth::user()->can('delete expenditure'), 403);

        $expenditure = $this->scopedExpenditure($id);
        $expenditure->delete();

        return redirect()->route('expenditure.index')->with('success', 'Expenditure removed!');
    }

    /**
     * The school a spend is filed against. Never a client-submitted one -
     * it's always whichever institution is currently active for whoever is
     * recording it.
     */
    private function institutionId(?Expenditure $expenditure = null): int
    {
        $institutionId = isAdmin()
            ? ($expenditure?->institution_id ?? currentInstitution()?->id)
            : currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        return $institutionId;
    }

    private function scopeToViewer($query): void
    {
        if (isAdmin()) {
            return;
        }

        $query->where('institution_id', currentInstitution()?->id ?? 0);
    }

    private function scopedExpenditure(int $id): Expenditure
    {
        $query = Expenditure::with(['category', 'institution', 'recordedBy']);
        $this->scopeToViewer($query);

        return $query->findOrFail($id);
    }
}
