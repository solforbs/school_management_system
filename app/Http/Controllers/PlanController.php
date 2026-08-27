<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly,lifetime',
            'modules' => 'array',
            'modules.*' => 'string|in:'.implode(',', Plan::MODULES),
            'features' => 'array',
            'features.*' => 'string|in:'.implode(',', array_keys(Plan::FEATURES)),
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_custom_priced' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_custom_priced'] = $request->boolean('is_custom_priced');
        $validated['slug'] = $plan?->slug ?? Str::slug($validated['name']).'-'.Str::random(6);

        return $validated;
    }
}
