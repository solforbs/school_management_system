<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which sitting an examination belongs to: the mid-term or the end-of-term.
 *
 * `term` already says which term, and `academic_year` which year, but a term
 * holds more than one round of papers and nothing recorded which. That's the
 * one fact an exam timetable is organised around.
 *
 * Deliberately nullable with no backfill: the existing titles ("Maths P1",
 * "Third Term Exams") carry no clue either way, and guessing would put
 * papers on the wrong timetable. Untyped examinations still print - they
 * just don't narrow to one sitting until someone says which they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('examinations', 'exam_type')) {
            return;
        }

        Schema::table('examinations', function (Blueprint $table) {
            $table->enum('exam_type', ['mid_term', 'end_term'])->nullable()->after('term');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('examinations', 'exam_type')) {
            return;
        }

        Schema::table('examinations', function (Blueprint $table) {
            $table->dropColumn('exam_type');
        });
    }
};
