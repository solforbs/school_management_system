<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('timetable_entry_id')->constrained('timetable_entries')->cascadeOnDelete();
            $table->date('lesson_date');
            $table->enum('status', ['attended', 'not_attended', 'recovered'])->default('not_attended');
            $table->text('remarks')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One attendance record per timetable period per calendar date.
            $table->unique(['timetable_entry_id', 'lesson_date']);
            $table->index(['institution_id', 'class_id', 'lesson_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
