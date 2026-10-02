<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CBC-specific curriculum structure fields on subjects.
 *
 * In the Competency Based Curriculum, subjects are organised as
 * Learning Areas (the broad discipline — "Mathematics"), broken into
 * Strands (thematic groupings — "Numbers"), then Sub-Strands (specific
 * topics — "Whole Numbers"). Formative assessment tasks are linked to a
 * Sub-Strand, so a teacher can record which curriculum strand an
 * observation or project covers.
 *
 * All three columns are nullable: 8-4-4 subjects carry none of them,
 * and a CBC school can populate them incrementally. They are purely
 * informational at this stage — report cards and assessment forms read
 * them for display; no business logic depends on their presence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            if (! Schema::hasColumn('subjects', 'learning_area')) {
                $table->string('learning_area', 100)->nullable()->after('code');
            }

            if (! Schema::hasColumn('subjects', 'strand')) {
                $table->string('strand', 100)->nullable()->after('learning_area');
            }

            if (! Schema::hasColumn('subjects', 'sub_strand')) {
                $table->string('sub_strand', 100)->nullable()->after('strand');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            foreach (['learning_area', 'strand', 'sub_strand'] as $column) {
                if (Schema::hasColumn('subjects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
