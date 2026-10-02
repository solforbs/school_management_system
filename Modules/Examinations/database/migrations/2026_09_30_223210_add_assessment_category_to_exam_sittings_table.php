<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sitting's assessment category: what kind of assessment event it is.
 *
 * The existing exam_type ('mid_term', 'end_term', 'mock', ...) answers
 * "which sitting in the term?" — this column answers "what kind of
 * assessment is this at all?"
 *
 * Null is intentionally allowed and means 'summative' for every row that
 * predates this column, so nothing existing breaks. New sittings always
 * carry a value. The model provides isSummative(), isFormative(), isSba(),
 * and isNational() helpers rather than checking the string directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('exam_sittings', 'assessment_category')) {
            return;
        }

        Schema::table('exam_sittings', function (Blueprint $table) {
            $table->string('assessment_category', 30)->nullable()->after('exam_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('exam_sittings', 'assessment_category')) {
            return;
        }

        Schema::table('exam_sittings', function (Blueprint $table) {
            $table->dropColumn('assessment_category');
        });
    }
};
