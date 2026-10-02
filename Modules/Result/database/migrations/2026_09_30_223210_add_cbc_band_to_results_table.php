<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A directly-entered CBC expectation band on a result row.
 *
 * On 8-4-4, a teacher enters marks and the system derives a grade
 * (A-E) from the percentage. On CBC formative / SBA assessments, there
 * are no marks — the teacher observes a learner and ticks a band
 * (EE / ME / AE / BE) directly. This column holds that direct entry.
 *
 * The existing `grade` column already stores the band string for CBC
 * classes (the grading scale produces 'EE', 'ME', etc.), so `cbc_band`
 * is a parallel signal that makes it unambiguous: null means "derived
 * from marks", a value means "entered directly by the teacher".
 *
 * `marks_obtained` is left null on direct-band results. Null here on
 * 8-4-4 results is correct and expected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('results', 'cbc_band')) {
            return;
        }

        Schema::table('results', function (Blueprint $table) {
            $table->string('cbc_band', 10)->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('results', 'cbc_band')) {
            return;
        }

        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('cbc_band');
        });
    }
};
