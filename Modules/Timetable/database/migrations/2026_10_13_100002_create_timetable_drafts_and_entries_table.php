<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('timetable_draft_entries');
        Schema::dropIfExists('timetable_drafts');

        Schema::create('timetable_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('academic_year')->nullable();
            $table->string('term')->nullable();
            $table->foreignId('structure_id')->nullable()->constrained('timetable_structures')->nullOnDelete();
            $table->string('status')->default('draft'); // 'draft', 'published', 'archived'
            $table->string('scope_type')->default('class'); // 'stream', 'class', 'whole_school'
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->json('generator_settings')->nullable();
            $table->decimal('generation_score', 5, 2)->nullable();
            $table->json('conflict_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'status'], 'tt_drafts_inst_status_idx');
        });

        Schema::create('timetable_draft_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_id')->constrained('timetable_drafts')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('period_number')->nullable();
            $table->string('room')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['draft_id', 'class_id', 'day_of_week'], 'tt_draft_entries_cls_day_idx');
            $table->index(['draft_id', 'teacher_id', 'day_of_week'], 'tt_draft_entries_tch_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_draft_entries');
        Schema::dropIfExists('timetable_drafts');
    }
};
