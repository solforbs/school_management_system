<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plan as the outside world sees it.
 *
 * This is the boundary of a public, unauthenticated endpoint, so every
 * field is named explicitly rather than dumped from the model. Anything
 * added to the plans table later stays private until someone deliberately
 * lists it here - which is the point. `is_active` in particular is absent
 * on purpose: the API only ever returns active plans, so publishing the
 * flag would only ever say "true".
 *
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            // Cast off the decimal string so the figure arrives as a JSON
            // number rather than "15000.00", which a frontend would have to
            // parse before it could format it.
            'price' => (float) $this->price,
            'currency' => Plan::CURRENCY,
            // True when the price is quoted rather than published, so a
            // frontend renders "Custom" instead of the (meaningless) figure.
            'is_custom_priced' => (bool) $this->is_custom_priced,
            'billing_cycle' => $this->billing_cycle,
            // Which card a pricing page badges as "Most popular".
            'is_featured' => (bool) $this->is_featured,
            // Both halves of what a plan grants, each carrying the key the
            // system uses alongside a label fit to print. The key is the
            // stable half: a frontend matching on it keeps working if the
            // wording is ever reworded.
            'modules' => $this->labelled($this->modules, fn (string $key) => Plan::moduleLabel($key)),
            'features' => $this->labelled($this->features, fn (string $key) => Plan::featureLabel($key)),
        ];
    }

    /**
     * @param  array<int, string>|null  $keys
     * @return array<int, array{key: string, label: string}>
     */
    private function labelled(?array $keys, callable $label): array
    {
        return collect($keys ?? [])
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->values()
            ->map(fn (string $key) => ['key' => $key, 'label' => $label($key)])
            ->all();
    }
}
