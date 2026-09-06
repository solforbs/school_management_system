<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use App\Models\PlanFeature;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The "Pro features" section of the plan create/edit form.
 *
 * The surrounding form is a plain POST, so this component owns none of the
 * saving: it keeps the chosen feature keys in sync and renders them as
 * hidden <input name="features[]"> fields the form submits like any other.
 * Ticking a feature, and defining a brand-new one, both happen here over
 * the wire without leaving the page.
 */
class PlanFeatureManager extends Component
{
    /** @var array<int, string> Feature keys currently on the plan. */
    public array $selected = [];

    /** The plan being edited, or null when creating. Drives delete safety. */
    public ?int $planId = null;

    /** Label for a feature the admin is defining on the spot. */
    public string $newLabel = '';

    /** The catalogue key the delete dialog is confirming. */
    public ?string $confirmingDeleteKey = null;

    /**
     * @param  array<int, string>|null  $features  Keys already on the plan.
     */
    public function mount(?int $planId = null, ?array $features = null): void
    {
        abort_unless(isAdmin(), 403);

        $this->planId = $planId;
        $this->selected = collect(Arr::wrap(old('features', $features ?? [])))
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** The label shown in the delete-feature confirmation dialog. */
    #[Computed]
    public function deleteTargetLabel(): ?string
    {
        if (! is_string($this->confirmingDeleteKey) || $this->confirmingDeleteKey === '') {
            return null;
        }

        return $this->catalog()[$this->confirmingDeleteKey] ?? $this->confirmingDeleteKey;
    }

    /**
     * key => label for everything that can be ticked: the catalogue table
     * plus the built-in fallbacks, and any key already on this plan that
     * has somehow lost its definition (so it still renders and can be
     * unticked rather than silently vanishing).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function catalog(): array
    {
        $catalog = PlanFeature::catalog();

        foreach ($this->selected as $key) {
            $catalog[$key] ??= Plan::featureLabel($key);
        }

        asort($catalog, SORT_NATURAL | SORT_FLAG_CASE);

        return $catalog;
    }

    public function toggle(string $key): void
    {
        if (in_array($key, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$key]));

            return;
        }

        if (array_key_exists($key, $this->catalog())) {
            $this->selected[] = $key;
        }
    }

    /**
     * Add a feature that doesn't exist yet: register it in the catalogue
     * (so its label resolves everywhere) and tick it on this plan.
     */
    public function addFeature(): void
    {
        abort_unless(isAdmin(), 403);

        $label = trim($this->newLabel);
        $key = PlanFeature::keyFor($label);

        $this->validate(
            ['newLabel' => ['required', 'string', 'min:2', 'max:100']],
            [],
            ['newLabel' => 'feature name'],
        );

        if ($key === '') {
            $this->addError('newLabel', 'Enter a name with at least one letter or number.');

            return;
        }

        PlanFeature::updateOrCreate(
            ['key' => $key],
            ['label' => $label, 'is_active' => true],
        );

        if (! in_array($key, $this->selected, true)) {
            $this->selected[] = $key;
        }

        $this->reset('newLabel');
        unset($this->catalog);
    }

    /**
     * Remove a feature from the catalogue entirely. Refused for the
     * built-in keys and for anything another plan still uses - those can
     * only be unticked from this plan.
     */
    public function deleteFeature(?string $key = null): void
    {
        abort_unless(isAdmin(), 403);

        $key ??= $this->confirmingDeleteKey;

        $this->confirmingDeleteKey = null;
        Flux::modal('confirm-delete-feature')->close();

        if (! is_string($key) || $key === '') {
            return;
        }

        $this->selected = array_values(array_diff($this->selected, [$key]));

        if (array_key_exists($key, Plan::FEATURES)) {
            return;
        }

        $usedElsewhere = Plan::query()
            ->when($this->planId, fn ($q) => $q->whereKeyNot($this->planId))
            ->get(['features'])
            ->contains(fn (Plan $plan): bool => in_array($key, Arr::wrap($plan->features), true));

        if ($usedElsewhere) {
            $this->addError('newLabel', 'That feature is still used by another plan, so it was only removed from this one.');

            return;
        }

        PlanFeature::where('key', $key)->delete();
        unset($this->catalog);
    }

    public function render(): View
    {
        return view('livewire.admin.plan-feature-manager');
    }
}
