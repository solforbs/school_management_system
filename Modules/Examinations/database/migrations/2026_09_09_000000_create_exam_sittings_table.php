<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An examination sitting - "Mock Exam", "End Term" - is the event a school
 * runs. It comprises a paper per subject per class; those papers are the
 * existing `examinations` rows, which now hang off a sitting.
 *
 * Purely additive: every column the papers already carry (subject_id,
 * exam_date, total_marks, ...) stays put, so results, grading and report
 * cards are untouched.
 */
return new class extends Migration
{
    /**
     * Labels for the exam types that predate this change, used when naming
     * a sitting built from existing papers.
     *
     * @var array<string, string>
     */
    private const LEGACY_TYPE_LABELS = [
        'mid_term' => 'Mid Term',
        'end_term' => 'End Term',
    ];

    public function up(): void
    {
        Schema::create('exam_sittings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('term')->nullable();
            $table->string('exam_type')->nullable();
            $table->unsignedSmallInteger('academic_year')->nullable();
            // Null = the sitting runs for every class; set = it is targeted
            // at one class only.
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'academic_year', 'term']);
        });

        Schema::table('examinations', function (Blueprint $table) {
            $table->foreignId('sitting_id')->nullable()->after('institution_id')
                ->constrained('exam_sittings')->cascadeOnDelete();
        });

        // exam_type was a two-value enum; widen it so new labels ("mock",
        // "opening", "cat") aren't schema-locked. The label map lives on the
        // model now.
        Schema::table('examinations', function (Blueprint $table) {
            $table->string('exam_type')->nullable()->change();
        });

        $this->backfillSittings();
    }

    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sitting_id');
        });

        Schema::dropIfExists('exam_sittings');
    }

    /**
     * Fold the existing per-paper rows into one sitting each, grouped by
     * (institution, year, term, type). A group that turns out to sit a
     * single class is recorded as targeted at that class.
     */
    private function backfillSittings(): void
    {
        $groups = DB::table('examinations')
            ->whereNull('sitting_id')
            ->select('institution_id', 'academic_year', 'term', 'exam_type')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $match = function ($query) use ($group) {
                $query->where('institution_id', $group->institution_id);

                foreach (['academic_year', 'term', 'exam_type'] as $column) {
                    $value = $group->{$column};
                    $value === null
                        ? $query->whereNull($column)
                        : $query->where($column, $value);
                }
            };

            $classIds = DB::table('examinations')
                ->where($match)
                ->distinct()
                ->pluck('class_id');

            $targetClassId = $classIds->count() === 1 ? $classIds->first() : null;

            $sittingId = DB::table('exam_sittings')->insertGetId([
                'institution_id' => $group->institution_id,
                'title' => $group->exam_type
                    ? (self::LEGACY_TYPE_LABELS[$group->exam_type] ?? ucwords(str_replace('_', ' ', $group->exam_type)))
                    : ($group->term ?: 'Examination'),
                'term' => $group->term,
                'exam_type' => $group->exam_type,
                'academic_year' => $group->academic_year,
                'class_id' => $targetClassId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('examinations')->where($match)->update(['sitting_id' => $sittingId]);
        }
    }
};
