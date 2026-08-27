<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `system` says which curriculum a class is taught on; it isn't enough
     * to grade by on its own. CBC is marked two different ways depending on
     * the level: the classroom four-band rubric (EE/ME/AE/BE), and the
     * eight-level KJSEA achievement scale (EE1...BE2) junior school reports
     * against from 2025. Both are CBC - one is not a replacement for the
     * other - so the scheme is a second axis rather than another `system`.
     *
     * Null on 8-4-4, where there is nothing to choose: A-E is the only way
     * it's graded. Existing CBC rows take the rubric, which is the scale
     * they were already loaded with.
     */
    public function up(): void
    {
        if (Schema::hasColumn('curricula', 'grading_scheme')) {
            return;
        }

        Schema::table('curricula', function (Blueprint $table) {
            $table->string('grading_scheme')->nullable()->after('system');
        });

        DB::table('curricula')->where('system', 'cbc')->update(['grading_scheme' => 'rubric']);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('curricula', 'grading_scheme')) {
            return;
        }

        Schema::table('curricula', function (Blueprint $table) {
            $table->dropColumn('grading_scheme');
        });
    }
};
