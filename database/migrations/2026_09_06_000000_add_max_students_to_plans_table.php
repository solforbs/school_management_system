<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ceiling on how many students an institution on this plan may
     * enrol. NULL means "no ceiling" - the Enterprise case - rather than
     * zero, so a plan that forgets to set it isn't accidentally capped.
     *
     * Enforced server-side in Modules\Student\Actions\SaveStudent, the one
     * path every enrolment (form, import, API) goes through, so there is
     * nothing for a director to click past.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_students')->nullable()->after('features');
        });

        // Backfill the plans the seeder ships, matched by slug so an
        // environment that renamed them keeps whatever it already had.
        foreach (['standard' => 100, 'premium' => 5000] as $slug => $limit) {
            DB::table('plans')->where('slug', $slug)->whereNull('max_students')->update(['max_students' => $limit]);
        }

        // Enterprise stays uncapped (NULL) on purpose - left as-is.
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_students');
        });
    }
};
