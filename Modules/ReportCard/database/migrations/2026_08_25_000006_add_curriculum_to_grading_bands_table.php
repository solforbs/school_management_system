<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grading bands stop being one flat scale per school: a school running
     * both curricula needs an A-E scale and a CBC four-band rubric side by
     * side. Bands left with a null curriculum_id stay as the school-wide
     * fallback, so scales configured before this keep working untouched.
     *
     * `points` carries the 8-4-4 grade points (A = 12 ... E = 1) used for
     * mean-grade arithmetic; it stays null on CBC bands, which aren't
     * aggregated that way.
     */
    public function up(): void
    {
        Schema::table('grading_bands', function (Blueprint $table) {
            if (! Schema::hasColumn('grading_bands', 'curriculum_id')) {
                $table->foreignId('curriculum_id')->nullable()->after('institution_id')
                    ->constrained('curricula')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('grading_bands', 'points')) {
                $table->unsignedTinyInteger('points')->nullable()->after('grade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('grading_bands', function (Blueprint $table) {
            if (Schema::hasColumn('grading_bands', 'curriculum_id')) {
                $table->dropConstrainedForeignId('curriculum_id');
            }

            if (Schema::hasColumn('grading_bands', 'points')) {
                $table->dropColumn('points');
            }
        });
    }
};
