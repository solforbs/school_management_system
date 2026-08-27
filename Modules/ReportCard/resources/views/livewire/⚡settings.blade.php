<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Curriculum\Models\Curriculum;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;
use Modules\ReportCard\Models\ReportTemplate;
use Modules\ReportCard\Support\GradingScaleDefaults;

/**
 * Grading scales and the report card letter, both edited in place.
 *
 * A school running both curricula keeps a scale for each: pick the
 * curriculum, and classes on it are marked against its bands. The
 * school-wide scale is the fallback for classes with no curriculum set.
 */
new #[Title('Report Card Settings')] class extends Component
{
    #[Url(as: 'institution_id')]
    public string $institutionId = '';

    #[Url(as: 'curriculum_id')]
    public string $curriculumId = '';

    // New band
    public string $min_percentage = '';

    public string $max_percentage = '';

    public string $grade = '';

    public string $points = '';

    public string $remark = '';

    // Which standard scale the "load" button installs. Preselected from
    // the chosen curriculum, but always editable - a school may well want
    // to load a scale its curriculum record doesn't yet name.
    public string $defaultScale = GradingScaleDefaults::SCALE_844;

    // Template
    public string $opening_text = '';

    public string $closing_text = '';

    public string $signatory_name = '';

    public string $signatory_title = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('edit reportcard'), 403);

        // Non-admins only ever manage settings for whichever institution is
        // currently active for them - never an arbitrary one they own.
        if (! isAdmin()) {
            $this->institutionId = (string) (currentInstitution()?->id ?? '');
        }

        $this->loadTemplate();
    }

    public function updatedInstitutionId(): void
    {
        $this->curriculumId = '';
        $this->loadTemplate();
        $this->syncDefaultScale();
    }

    public function updatedCurriculumId(): void
    {
        $this->syncDefaultScale();
    }

    /**
     * Point the standard-scale picker at whatever the chosen curriculum
     * says it grades on, so loading it is one click. Left editable: a
     * curriculum whose system was guessed wrong on import shouldn't force
     * the wrong scale on the school.
     */
    private function syncDefaultScale(): void
    {
        unset($this->curriculum, $this->curricula, $this->gradingBands);

        $this->defaultScale = GradingScaleDefaults::keyFor($this->curriculum);
    }

    public function addBand(): void
    {
        abort_unless(auth()->user()->can('edit reportcard'), 403);

        $institution = $this->institution;
        abort_unless($institution, 404);

        $validated = $this->validate([
            'min_percentage' => 'required|numeric|min:0|max:100|lt:max_percentage',
            'max_percentage' => 'required|numeric|min:0|max:100',
            'grade' => 'required|string|max:10',
            'points' => 'nullable|integer|min:0|max:100',
            'remark' => 'nullable|string|max:255',
        ]);

        GradingBand::create([
            'institution_id' => $institution->id,
            'curriculum_id' => $this->curriculum?->id,
            'min_percentage' => $validated['min_percentage'],
            'max_percentage' => $validated['max_percentage'],
            'grade' => $validated['grade'],
            'points' => $validated['points'] ?: null,
            'remark' => $validated['remark'] ?: null,
        ]);

        $this->reset(['min_percentage', 'max_percentage', 'grade', 'points', 'remark']);
        unset($this->gradingBands);

        Flux::toast(text: 'Grading band added.', variant: 'success');
    }

    /**
     * Fill the selected scale in from one of the standard ones. Refuses to
     * run over an existing scale: a school that has already tuned its bands
     * shouldn't lose that to a stray click.
     */
    public function loadDefaults(): void
    {
        abort_unless(auth()->user()->can('edit reportcard'), 403);

        $institution = $this->institution;
        abort_unless($institution, 404);

        if ($this->gradingBands->isNotEmpty()) {
            Flux::toast(
                text: 'This scale already has bands. Remove them first if you want to start from the standard one.',
                variant: 'danger',
            );

            return;
        }

        $curriculum = $this->curriculum;

        foreach (GradingScaleDefaults::forKey($this->defaultScale) as $band) {
            GradingBand::create($band + [
                'institution_id' => $institution->id,
                'curriculum_id' => $curriculum?->id,
            ]);
        }

        unset($this->gradingBands);

        $label = GradingScaleDefaults::labelForKey($this->defaultScale);

        Flux::toast(
            text: 'Loaded the '.$label.' scale. Edit any band that differs at your school.',
            variant: 'success',
        );
    }

    public function removeBand(int $id): void
    {
        abort_unless(auth()->user()->can('edit reportcard'), 403);

        $band = GradingBand::findOrFail($id);

        abort_unless(isAdmin() || $band->institution_id === currentInstitution()?->id, 403);

        $band->delete();

        unset($this->gradingBands);

        Flux::toast(text: 'Grading band removed.', variant: 'success');
    }

    public function saveTemplate(): void
    {
        abort_unless(auth()->user()->can('edit reportcard'), 403);

        $institution = $this->institution;
        abort_unless($institution, 404);

        $validated = $this->validate([
            'opening_text' => 'nullable|string',
            'closing_text' => 'nullable|string',
            'signatory_name' => 'nullable|string|max:255',
            'signatory_title' => 'nullable|string|max:255',
        ]);

        ReportTemplate::updateOrCreate(['institution_id' => $institution->id], $validated);

        Flux::toast(text: 'Report template saved.', variant: 'success');
    }

    #[Computed]
    public function institution(): ?Institution
    {
        if (! isAdmin()) {
            return currentInstitution();
        }

        return $this->institutionId === '' ? null : Institution::find($this->institutionId);
    }

    /**
     * @return Collection<int, Institution>
     */
    #[Computed]
    public function institutions(): Collection
    {
        return isAdmin() ? Institution::all() : collect([currentInstitution()])->filter();
    }

    /**
     * @return Collection<int, Curriculum>
     */
    #[Computed]
    public function curricula(): Collection
    {
        $institution = $this->institution;

        return $institution
            ? Curriculum::where('institution_id', $institution->id)->orderBy('name')->get()
            : collect();
    }

    #[Computed]
    public function curriculum(): ?Curriculum
    {
        return $this->curriculumId === ''
            ? null
            : $this->curricula->firstWhere('id', (int) $this->curriculumId);
    }

    /**
     * @return Collection<int, GradingBand>
     */
    #[Computed]
    public function gradingBands(): Collection
    {
        $institution = $this->institution;

        if (! $institution) {
            return collect();
        }

        $curriculum = $this->curriculum;

        return GradingBand::where('institution_id', $institution->id)
            ->when(
                $curriculum,
                fn ($query) => $query->where('curriculum_id', $curriculum->id),
                fn ($query) => $query->whereNull('curriculum_id'),
            )
            ->orderByDesc('min_percentage')
            ->get();
    }

    private function loadTemplate(): void
    {
        unset($this->institution, $this->curricula, $this->curriculum, $this->gradingBands);

        $institution = $this->institution;

        if (! $institution) {
            return;
        }

        $template = ReportTemplate::firstOrNew(['institution_id' => $institution->id]);

        $this->fill([
            'opening_text' => (string) $template->opening_text,
            'closing_text' => (string) $template->closing_text,
            'signatory_name' => (string) $template->signatory_name,
            'signatory_title' => (string) $template->signatory_title,
        ]);
    }
}; ?>

<div class="p-4">
    @if (isAdmin())
        <flux:card class="mb-6">
            <flux:select wire:model.live="institutionId" label="Institution">
                <flux:select.option value="">Select Institution</flux:select.option>
                @foreach ($this->institutions as $inst)
                    <flux:select.option value="{{ $inst->id }}">{{ $inst->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:card>
    @endif

    @if (! $this->institution)
        <flux:card class="py-10 text-center">
            <flux:text class="text-zinc-500">
                @if (isAdmin())
                    Select an institution above to manage its report card settings.
                @else
                    No institution found for your account.
                @endif
            </flux:text>
        </flux:card>
    @else
        <flux:card class="mb-6">
            @php
                $previewParams = array_filter([
                    'institution_id' => $this->institution->id,
                    'curriculum_id' => $this->curriculum?->id,
                ]);
            @endphp

            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">Grading Scale</flux:heading>

                {{-- Opens in its own tab: it is a streamed PDF, so it can't
                     render inside the Livewire page. --}}
                <flux:button href="{{ route('reportcard.settings.preview', $previewParams) }}" target="_blank"
                    icon="document-magnifying-glass" size="sm">
                    Preview report card
                </flux:button>
            </div>
            <flux:text class="mb-4 text-zinc-500">
                The percentage bands each result's grade is worked out from. 8-4-4 marks A to E. CBC marks against
                expectations on the eight KJSEA levels, and each level names its band — EE1 and EE2 are both EE — so a
                CBC report states both. Each curriculum keeps its own scale: pick one below and classes on it are
                marked against its bands. The school-wide scale is the fallback for classes with no curriculum set.
            </flux:text>

            <div class="mb-4 flex flex-wrap items-end gap-2">
                <flux:select wire:model.live="curriculumId" label="Scale">
                    <flux:select.option value="">School-wide (no curriculum)</flux:select.option>
                    @foreach ($this->curricula as $option)
                        <flux:select.option value="{{ $option->id }}">
                            {{ $option->name }} — {{ $option->gradingLabel() }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                @if ($this->curricula->isEmpty())
                    <flux:text class="text-xs text-zinc-500">
                        No curricula set up yet.
                        <a href="{{ route('curriculum.index') }}" class="underline" wire:navigate>Add one</a> to keep
                        separate 8-4-4 and CBC scales.
                    </flux:text>
                @endif
            </div>

            @if ($this->curriculum)
                <div class="mb-4">
                    <flux:badge color="blue">
                        {{ $this->curriculum->name }} · {{ $this->curriculum->gradingLabel() }}
                    </flux:badge>

                    @if ($this->curriculum->looksMisconfigured())
                        <flux:callout variant="warning" class="mt-3">
                            <flux:callout.text>
                                “{{ $this->curriculum->name }}” is named for one system but is set to grade on
                                <strong>{{ $this->curriculum->systemLabel() }}</strong>. Marks on it will be graded
                                {{ $this->curriculum->isCbc() ? 'against expectations' : 'A to E' }}. If that is not
                                what you want, change its system on the
                                <a href="{{ route('curriculum.index') }}" class="underline" wire:navigate>curriculum
                                    page</a>.
                            </flux:callout.text>
                        </flux:callout>
                    @endif
                </div>
            @endif

            @if ($this->gradingBands->isNotEmpty())
                <flux:table class="mb-4">
                    <flux:table.columns>
                        <flux:table.column>Min %</flux:table.column>
                        <flux:table.column>Max %</flux:table.column>
                        <flux:table.column>Grade</flux:table.column>
                        <flux:table.column>Points</flux:table.column>
                        <flux:table.column>Remark</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->gradingBands as $band)
                            <flux:table.row :key="$band->id">
                                <flux:table.cell>{{ $band->min_percentage }}</flux:table.cell>
                                <flux:table.cell>{{ $band->max_percentage }}</flux:table.cell>
                                <flux:table.cell>{{ $band->grade }}</flux:table.cell>
                                <flux:table.cell>{{ $band->points ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $band->remark ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:button type="button" size="sm" icon="trash" variant="ghost"
                                        wire:click="removeBand({{ $band->id }})"
                                        wire:confirm="Remove this grading band?">delete</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <div class="mb-4 rounded-md border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:text class="mb-3 block text-zinc-500">
                        No bands on this scale yet. Load the standard one and edit whatever differs at your school.
                    </flux:text>

                    <div class="flex flex-wrap items-end gap-2">
                        <flux:select wire:model.live="defaultScale" label="Standard scale" class="min-w-72">
                            @foreach (\Modules\ReportCard\Support\GradingScaleDefaults::options() as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:button type="button" variant="primary" icon="sparkles" wire:click="loadDefaults">
                            Load into
                            {{ $this->curriculum ? $this->curriculum->name : 'the school-wide scale' }}
                        </flux:button>
                    </div>
                </div>
            @endif

            <form wire:submit="addBand" class="grid grid-cols-1 items-end gap-3 md:grid-cols-6">
                <flux:input type="number" step="0.01" min="0" max="100" label="Min %"
                    wire:model="min_percentage" />
                <flux:input type="number" step="0.01" min="0" max="100" label="Max %"
                    wire:model="max_percentage" />
                @php $isCbc = $this->curriculum?->isCbc() ?? false; @endphp
                <flux:input label="Grade" maxlength="10" wire:model="grade"
                    placeholder="{{ $isCbc ? 'e.g. ME1' : 'e.g. B+' }}"
                    description="{{ $isCbc ? 'The KJSEA level. Its band (ME) is read off it.' : '' }}" />
                <flux:input type="number" min="0" label="Points" wire:model="points"
                    description="{{ $isCbc ? 'Achievement level 8-1.' : 'Grade points, e.g. A = 12.' }}" />
                <flux:input label="Remark" wire:model="remark" placeholder="e.g. Excellent" />
                <flux:button type="submit" variant="primary">Add Band</flux:button>
            </form>
        </flux:card>

        <flux:card>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">Report Card Template</flux:heading>

                <flux:button href="{{ route('reportcard.settings.preview', $previewParams) }}" target="_blank"
                    icon="document-magnifying-glass" size="sm">
                    Preview report card
                </flux:button>
            </div>
            <flux:text class="mb-4 text-zinc-500">
                Customize the opening and closing text on generated report cards. Available placeholders:
                <code>@{{student_name}}</code>, <code>@{{institution_name}}</code>,
                <code>@{{class_name}}</code>, <code>@{{term}}</code>,
                <code>@{{mean_percentage}}</code>, <code>@{{mean_grade}}</code>.
            </flux:text>

            <form wire:submit="saveTemplate" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <flux:textarea label="Opening Text" rows="3" wire:model="opening_text" />
                </div>
                <div class="md:col-span-2">
                    <flux:textarea label="Closing Text" rows="3" wire:model="closing_text" />
                </div>
                <flux:input label="Signatory Name" wire:model="signatory_name" placeholder="e.g. Jane Doe" />
                <flux:input label="Signatory Title" wire:model="signatory_title" placeholder="e.g. Principal" />

                <div class="flex justify-end md:col-span-2">
                    <flux:button type="submit" variant="primary">
                        <span wire:loading.remove wire:target="saveTemplate">Save Template</span>
                        <span wire:loading wire:target="saveTemplate">Saving…</span>
                    </flux:button>
                </div>
            </form>
        </flux:card>
    @endif
</div>
