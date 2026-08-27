<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The top tier: every module the system has, and every pro capability
 * within them.
 *
 * Written as a seeder rather than typed into the admin screen once so the
 * same plan exists in every environment, and so re-running it can't create
 * a second copy. Its inclusions are read from the Plan constants rather
 * than listed here, which means a module added to the system later joins
 * Premium by re-running this instead of by someone remembering to tick a
 * new box.
 */
class PremiumPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::firstOrNew(['name' => 'Premium']);

        // Only ever assigned once: the slug is what a public URL is built
        // from, so re-seeding must not move it.
        $plan->slug ??= 'premium-'.Str::random(6);

        $plan->fill([
            'description' => 'Every module and every feature, for schools running their whole operation here.',
            'modules' => Plan::MODULES,
            'features' => array_keys(Plan::FEATURES),
            'is_active' => true,
        ]);

        // Priced on application. Left this way deliberately rather than
        // guessed at: the tier sits above Standard, and inventing a figure
        // would publish a rate to the pricing page and the public API that
        // nobody in the business agreed to. Untick "Quoted price" on the
        // plan's admin screen once there is a real number for it.
        if (! $plan->exists) {
            $plan->price = 0;
            $plan->billing_cycle = 'monthly';
            $plan->is_custom_priced = true;
            $plan->is_featured = false;
        }

        $plan->save();
    }
}
