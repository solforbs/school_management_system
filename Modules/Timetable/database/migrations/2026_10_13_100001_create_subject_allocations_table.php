<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('subject_allocations');

        Schema::create('subject_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_id')->nullable()->constrained('curricula')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedTinyInteger('periods_per_week')->default(4);
            $table->unsignedTinyInteger('max_per_day')->default(1);
            $table->boolean('allow_double_periods')->default(false);
            $table->string('cognitive_weight')->default('medium'); // 'high', 'medium', 'low'
            $table->timestamps();

            $table->index(['institution_id', 'curriculum_id', 'subject_id'], 'sub_alloc_curr_idx');
            $table->index(['institution_id', 'class_id', 'subject_id'], 'sub_alloc_class_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_allocations');
    }
};
