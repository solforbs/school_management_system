<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The plan a director picked while onboarding, before they have paid
     * for any period of it.
     *
     * Deliberately not `subscription_plan`: that column means "the plan
     * this school is entitled to", and Institution::subscriptionActive()
     * treats a plan with no expiry as unlimited. Writing the choice there
     * after a setup-fee-only payment would hand out the plan for free and
     * for ever. This column only remembers what they chose, so billing can
     * put it in front of them when the first subscription charge falls due.
     */
    public function up(): void
    {
        if (Schema::hasColumn('institutions', 'selected_plan_id')) {
            return;
        }

        Schema::table('institutions', function (Blueprint $table) {
            $table->foreignId('selected_plan_id')->nullable()->after('subscription_plan')
                ->constrained('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('institutions', 'selected_plan_id')) {
            return;
        }

        Schema::table('institutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_plan_id');
        });
    }
};
