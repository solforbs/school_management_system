<?php

namespace Modules\Expenditure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Export\TableExportService;
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
    private const HEADERS = ['Date', 'Item', 'Category', 'Payee', 'Amount', 'Method', 'Status'];

    /**
     * Export expenditure records (Excel or PDF) with the same filters and
     * institution scoping the index screen applies.
     */
    public function export(Request $request, TableExportService $exporter)
    {
        abort_unless(Auth::user()->can('export expenditure'), 403);

        $query = Expenditure::query()->with(['category', 'recordedBy']);

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        $search = (string) $request->query('search', '');
        $categoryId = (string) $request->query('category_id', '');
        $status = (string) $request->query('status', '');
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        $query->when($search !== '', function ($q) use ($search) {
            $term = '%'.$search.'%';

            $q->where(fn ($sub) => $sub->where('title', 'like', $term)
                ->orWhere('payee', 'like', $term)
                ->orWhere('reference', 'like', $term));
        })
            ->when($categoryId !== '', fn ($q) => $q->where('expenditure_category_id', $categoryId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($from !== '', fn ($q) => $q->whereDate('spent_on', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('spent_on', '<=', $to))
            ->orderByDesc('spent_on');

        $rows = $query->get()->map(fn (Expenditure $expenditure) => $this->toExportRow($expenditure));
        $filename = 'expenditure-'.now()->format('Y-m-d');

        return $request->string('format')->lower()->value() === 'pdf'
            ? $exporter->pdf($rows, self::HEADERS, $filename, 'Expenditure')
            : $exporter->excel($rows, self::HEADERS, $filename, 'Expenditure');
    }

    /**
     * @return array<int, string>
     */
    private function toExportRow(Expenditure $expenditure): array
    {
        return [
            $expenditure->spent_on?->format('Y-m-d') ?? '',
            $expenditure->title,
            $expenditure->category?->name ?? '—',
            $expenditure->payee ?? '—',
            number_format((float) $expenditure->amount, 2),
            ucfirst(str_replace('_', ' ', (string) $expenditure->payment_method)),
            ucfirst((string) $expenditure->status),
        ];
    }

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
