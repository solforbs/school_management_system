<?php

namespace Modules\ReportCard\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportTemplate;
use Modules\ReportCard\Services\ReportCardPreviewService;
use Modules\ReportCard\Support\GradingScaleDefaults;

class ReportSettingsController extends Controller
{
    /**
     * Save the report template's prose fields.
     */
    public function updateTemplate(Request $request)
    {
        abort_unless(Auth::user()->can('edit reportcard'), 403);

        $institution = $this->resolveInstitution($request);
        abort_unless($institution, 404);

        $validated = $request->validate([
            'opening_text' => 'nullable|string',
            'closing_text' => 'nullable|string',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
        ]);

        ReportTemplate::updateOrCreate(['institution_id' => $institution->id], $validated);

        return redirect()->route('reportcard.settings', ['institution_id' => $institution->id])
            ->with('success', 'Report template saved.');
    }

    /**
     * Add a grading band.
     */
    public function storeGradingBand(Request $request)
    {
        abort_unless(Auth::user()->can('edit reportcard'), 403);

        $institution = $this->resolveInstitution($request);
        abort_unless($institution, 404);

        $validated = $request->validate([
            'curriculum_id' => 'nullable|exists:curricula,id',
            'min_percentage' => 'required|numeric|min:0|max:100|lt:max_percentage',
            'max_percentage' => 'required|numeric|min:0|max:100',
            'grade' => 'required|string|max:10',
            'points' => 'nullable|integer|min:0|max:100',
            'remark' => 'nullable|string|max:255',
        ]);

        $curriculum = $this->resolveCurriculum($institution, $validated['curriculum_id'] ?? null);

        $validated['institution_id'] = $institution->id;
        $validated['curriculum_id'] = $curriculum?->id;

        GradingBand::create($validated);

        return redirect()->route('reportcard.settings', $this->redirectParams($institution, $curriculum))
            ->with('success', 'Grading band added.');
    }

    /**
     * Fill a curriculum's scale in from the standard one for the system it
     * runs on - the full 8-4-4 A-E ladder, or CBC's expectation scale.
     *
     * Refuses to run over an existing scale: a school that has already
     * tuned its bands shouldn't lose that to a stray click.
     */
    public function loadDefaultGradingBands(Request $request)
    {
        abort_unless(Auth::user()->can('edit reportcard'), 403);

        $institution = $this->resolveInstitution($request);
        abort_unless($institution, 404);

        $validated = $request->validate([
            'curriculum_id' => 'nullable|exists:curricula,id',
            // Only used when no curriculum is chosen, i.e. for the
            // school-wide fallback scale.
            'system' => 'nullable|in:844,cbc',
        ]);

        $curriculum = $this->resolveCurriculum($institution, $validated['curriculum_id'] ?? null);

        $alreadyConfigured = GradingBand::where('institution_id', $institution->id)
            ->when(
                $curriculum,
                fn ($query) => $query->where('curriculum_id', $curriculum->id),
                fn ($query) => $query->whereNull('curriculum_id'),
            )
            ->exists();

        if ($alreadyConfigured) {
            return redirect()->route('reportcard.settings', $this->redirectParams($institution, $curriculum))
                ->with('error', 'This scale already has bands. Remove them first if you want to start from the standard one.');
        }

        $system = $curriculum?->system ?? ($validated['system'] ?? '844');

        foreach (GradingScaleDefaults::forSystem($system) as $band) {
            GradingBand::create($band + [
                'institution_id' => $institution->id,
                'curriculum_id' => $curriculum?->id,
            ]);
        }

        $label = GradingScaleDefaults::labelFor($system);

        return redirect()->route('reportcard.settings', $this->redirectParams($institution, $curriculum))
            ->with('success', 'The standard '.$label.' has been loaded. Edit any band that differs at your school.');
    }

    /**
     * A sample report card, rendered through the real template with the
     * school's own bands and wording, streamed inline so it opens in the
     * browser rather than landing in a downloads folder.
     *
     * Read-only: nothing is created, and nothing is written to disk.
     */
    public function preview(Request $request, ReportCardPreviewService $preview)
    {
        abort_unless(Auth::user()->can('edit reportcard'), 403);

        $institution = $this->resolveInstitution($request);
        abort_unless($institution, 404);

        $validated = $request->validate([
            'curriculum_id' => 'nullable|exists:curricula,id',
        ]);

        $curriculum = $this->resolveCurriculum($institution, $validated['curriculum_id'] ?? null);

        return Pdf::loadView(
            'reportcard::pdf.report-card',
            $preview->viewData($institution, $curriculum),
        )->setPaper('a4')->stream('sample-report-card.pdf');
    }

    /**
     * Remove a grading band.
     */
    public function destroyGradingBand(int $id)
    {
        abort_unless(Auth::user()->can('edit reportcard'), 403);

        $band = GradingBand::findOrFail($id);

        abort_unless(isAdmin() || $band->institution_id === currentInstitution()?->id, 403);

        $params = ['institution_id' => $band->institution_id];

        if ($band->curriculum_id) {
            $params['curriculum_id'] = $band->curriculum_id;
        }

        $band->delete();

        return redirect()->route('reportcard.settings', $params)->with('success', 'Grading band removed.');
    }

    private function resolveInstitution(Request $request): ?Institution
    {
        if (isAdmin()) {
            return $request->filled('institution_id') ? Institution::find($request->integer('institution_id')) : null;
        }

        // Non-admins (Director/Accountant) only ever manage settings for
        // whichever institution is currently active for them - never an
        // arbitrary one they merely own.
        return currentInstitution();
    }

    /**
     * The curriculum a band belongs to, refusing one from another school -
     * 'exists' alone would let a crafted request attach a band to it.
     */
    private function resolveCurriculum(Institution $institution, int|string|null $curriculumId): ?Curriculum
    {
        if (! $curriculumId) {
            return null;
        }

        $curriculum = Curriculum::findOrFail($curriculumId);

        abort_unless($curriculum->institution_id === $institution->id, 403);

        return $curriculum;
    }

    /**
     * @return array<string, int>
     */
    private function redirectParams(Institution $institution, ?Curriculum $curriculum): array
    {
        $params = ['institution_id' => $institution->id];

        if ($curriculum) {
            $params['curriculum_id'] = $curriculum->id;
        }

        return $params;
    }
}
