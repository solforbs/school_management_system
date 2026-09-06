<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function __construct()
    {
        abort_unless(isAdmin(), 403);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $plans = Plan::orderBy('name')->get();

        return view('layouts::admin.plans.index', compact('plans'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('layouts::admin.plans.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);

        Plan::create($data);

        return redirect()->route('admin.plans.index')->with('success', 'Plan created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Plan $plan)
    {
        return view('layouts::admin.plans.edit', compact('plan'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Plan $plan)
    {
        $data = $this->validateData($request, $plan);

        $plan->update($data);

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plan $plan)
    {
        $plan->delete();

        return redirect()->route('admin.plans.index')->with('success', 'Plan deleted successfully.');
    }

    protected function validateData(Request $request, ?Plan $plan = null): array
    {
        // The feature list is built by the Livewire plan-feature-manager,
        // which registers any new feature in the plan_features catalogue
        // before this form is submitted - so a valid key is one the
        // catalogue (or the built-in fallback) knows about.
        $knownFeatureKeys = array_keys(PlanFeature::catalog());

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:termly,monthly,yearly,lifetime',
            'max_students' => 'nullable|integer|min:1',
            'modules' => 'array',
            'modules.*' => 'string|in:'.implode(',', Plan::MODULES),
            'features' => 'array',
            'features.*' => ['string', Rule::in($knownFeatureKeys)],
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_custom_priced' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_custom_priced'] = $request->boolean('is_custom_priced');
        $validated['slug'] = $plan?->slug ?? Str::slug($validated['name']).'-'.Str::random(6);

        // Blank means "no ceiling" (Enterprise) - store NULL, not 0.
        $validated['max_students'] = $request->filled('max_students') ? (int) $request->integer('max_students') : null;

        // De-duplicate and drop blanks; the checkboxes can repeat a key.
        $validated['features'] = collect(Arr::wrap($validated['features'] ?? []))
            ->filter(fn ($key): bool => is_string($key) && $key !== '')
            ->unique()
            ->values()
            ->all();

        return $validated;
    }
}
