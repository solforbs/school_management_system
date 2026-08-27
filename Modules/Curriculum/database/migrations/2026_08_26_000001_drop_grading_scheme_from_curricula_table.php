<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CBC turned out not to need a scheme chosen at all.
     *
     * The four-band rubric and KJSEA's eight levels aren't alternatives: the
     * levels nest exactly inside the bands - EE1 and EE2 are both EE, ME1
     * and ME2 are both ME - so a curriculum graded on the eight levels is
     * already graded on the four bands, and a report can state both from a
     * single scale. Asking a school to pick one was asking a question with
     * no real answer.
     *
     * Bands themselves are untouched: `grading_bands` still holds whatever
     * each school configured, and the previous migration's backfill only
     * ever wrote to this column.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('curricula', 'grading_scheme')) {
            return;
        }

        Schema::table('curricula', function (Blueprint $table) {
            $table->dropColumn('grading_scheme');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('curricula', 'grading_scheme')) {
            return;
        }

        Schema::table('curricula', function (Blueprint $table) {
            $table->string('grading_scheme')->nullable()->after('system');
        });
    }
};
