<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A result used to take its subject from its examination. Now that an
     * examination can be a whole-school sitting with no subject of its own,
     * the subject a mark belongs to is recorded on the result itself.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('results', 'subject_id')) {
            Schema::table('results', function (Blueprint $table) {
                $table->foreignId('subject_id')->nullable()->after('class_id')
                    ->constrained('subjects')->nullOnDelete();
            });
        }

        // Backfill from the examination each result was entered under, so
        // existing report cards keep grouping by the same subject.
        DB::table('results')
            ->whereNull('subject_id')
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('examinations')
                ->whereColumn('examinations.id', 'results.examination_id')
                ->whereNotNull('examinations.subject_id'))
            ->update([
                'subject_id' => DB::raw('(select subject_id from examinations where examinations.id = results.examination_id)'),
            ]);

        $indexes = collect(Schema::getIndexes('results'))->pluck('name');

        // One result per student per paper becomes one per student per
        // paper per subject - a whole-school sitting holds many. Add the
        // wider unique first so the examination_id foreign key can lean on
        // it, then drop the narrow one.
        if (! $indexes->contains('results_examination_id_student_id_subject_id_unique')) {
            Schema::table('results', function (Blueprint $table) {
                $table->unique(['examination_id', 'student_id', 'subject_id']);
            });
        }

        if ($indexes->contains('results_examination_id_student_id_unique')) {
            Schema::table('results', function (Blueprint $table) {
                $table->dropUnique('results_examination_id_student_id_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->unique(['examination_id', 'student_id']);
        });

        Schema::table('results', function (Blueprint $table) {
            $table->dropUnique('results_examination_id_student_id_subject_id_unique');
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
