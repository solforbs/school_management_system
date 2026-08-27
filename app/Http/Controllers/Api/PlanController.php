<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The plans a prospective customer can read without signing in - what the
 * marketing site prices itself from.
 *
 * Read-only and deliberately unauthenticated: this is the same information
 * a pricing page publishes anyway. It is a separate controller from the
 * admin PlanController rather than a JSON branch inside it, so the public
 * surface can't accidentally inherit a field, a filter, or a write action
 * that was only ever meant for an administrator.
 */
class PlanController extends Controller
{
    /**
     * Every plan on sale, cheapest first.
     */
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(
            Plan::query()->active()->orderBy('price')->orderBy('name')->get()
        );
    }

    /**
     * One plan, by slug or by id.
     *
     * Both work because the two are useful in different places: a slug
     * reads well in a marketing URL, while an id is what the admin screens
     * already link by. A plan that isn't on sale is a 404 rather than a
     * 403 - the public has no business knowing it exists at all.
     */
    public function show(string $plan): PlanResource
    {
        $query = Plan::query()->active();

        $query->where(function ($builder) use ($plan) {
            $builder->where('slug', $plan);

            if (ctype_digit($plan)) {
                $builder->orWhere('id', (int) $plan);
            }
        });

        return new PlanResource($query->firstOrFail());
    }
}
